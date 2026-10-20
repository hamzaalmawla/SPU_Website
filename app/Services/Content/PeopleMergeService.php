<?php

declare(strict_types=1);

namespace App\Services\Content;

use App\Contracts\Auth\AdminAccessServiceInterface;
use App\Contracts\Content\PeopleMergeServiceInterface;
use App\Contracts\Shared\AuditServiceInterface;
use App\Contracts\Shared\CacheServiceInterface;
use App\DTOs\Content\PeopleMergeResultDTO;
use App\Enums\PublicationStatus;
use App\Models\Cms\CmsDraft;
use App\Models\Person\CouncilMember;
use App\Models\Person\FacultyMember;
use App\Models\Person\FacultyMemberTranslation;
use App\Models\Person\Person;
use App\Models\Person\PersonAppointment;
use App\Models\Person\PersonAppointmentTranslation;
use App\Models\Research\Researcher;
use App\Models\Research\ResearchPublication;
use App\Models\User\User;
use App\Support\PersonName;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Merges records that are one person: researchers entered twice, faculty
 * members listed twice in the same faculty, and faculty members who also
 * have a leadership profile. Nothing is hard-deleted: a merged-away record is
 * soft-deleted, and a merged profile's address redirects to the kept one.
 *
 * Someone listed in two different faculties is left alone: that is usually
 * one person teaching in both.
 */
final class PeopleMergeService implements PeopleMergeServiceInterface
{
    public const APPROVAL_TOKEN = 'people-merge-20261019';

    public function __construct(
        private readonly AuditServiceInterface $auditService,
        private readonly CacheServiceInterface $cacheService,
        private readonly AdminAccessServiceInterface $accessService,
    ) {}

    public function merge(bool $write, ?string $approval): PeopleMergeResultDTO
    {
        if ($write && $approval !== self::APPROVAL_TOKEN) {
            throw new InvalidArgumentException('Writing requires --approve='.self::APPROVAL_TOKEN.'.');
        }

        $researcherGroups = $this->researcherGroups();
        [$memberGroups, $skipped] = $this->memberGroups();
        $links = $this->memberPersonLinks();
        $lines = [];

        foreach ($researcherGroups as $group) {
            $lines[] = 'Researcher: keep R'.$group['keep']->getKey().' '.$group['keep']->full_name
                .' <- '.$group['merge']->map(fn (Researcher $researcher): string => 'R'.$researcher->getKey().' '.$researcher->full_name)->implode(', ');
        }
        foreach ($memberGroups as $group) {
            $lines[] = 'Faculty member: keep M'.$group['keep']->getKey().' /'.$group['keep']->slug
                .' <- '.$group['merge']->map(fn (FacultyMember $member): string => 'M'.$member->getKey().' /'.$member->slug)->implode(', ');
        }
        foreach ($skipped as $line) {
            $lines[] = 'Left alone (different faculties): '.$line;
        }
        foreach ($links as $link) {
            $lines[] = 'Link faculty member M'.$link['member']->getKey().' /'.$link['member']->slug.' to leadership profile P'.$link['person']->getKey().' /'.$link['person']->slug;
        }

        if ($write) {
            DB::transaction(function () use ($researcherGroups, $memberGroups, $links): void {
                foreach ($researcherGroups as $group) {
                    foreach ($group['merge'] as $duplicate) {
                        $this->mergeResearcher($group['keep'], $duplicate);
                    }
                }
                foreach ($memberGroups as $group) {
                    foreach ($group['merge'] as $duplicate) {
                        $this->mergeMember($group['keep'], $duplicate);
                    }
                }
                foreach ($links as $link) {
                    $this->linkMemberToPerson($link['member'], $link['person']);
                }
            });

            $this->auditService->log('people.merged', null, Person::class, null, [
                'batch' => self::APPROVAL_TOKEN,
                'researchers' => $researcherGroups->sum(fn (array $group): int => $group['merge']->count()),
                'faculty_members' => $memberGroups->sum(fn (array $group): int => $group['merge']->count()),
                'links' => $links->count(),
            ]);
            $this->cacheService->flushTags(['public-pages', 'public-shell', 'about', 'facilities', 'research', 'seo', 'sitemap', 'continuity']);
        }

        return new PeopleMergeResultDTO(
            written: $write,
            researchersMerged: $researcherGroups->sum(fn (array $group): int => $group['merge']->count()),
            membersMerged: $memberGroups->sum(fn (array $group): int => $group['merge']->count()),
            membersLinked: $links->count(),
            leftAlone: count($skipped),
            lines: $lines,
        );
    }

    public function leadershipOptions(string $search = ''): array
    {
        $search = trim($search);

        return Person::query()
            ->with('translations:id,person_id,locale,name')
            ->when($search !== '', fn ($query) => $query->where(fn ($inner) => $inner
                ->where('slug', 'like', '%'.$search.'%')
                ->orWhereHas('translations', fn ($translation) => $translation->where('name', 'like', '%'.$search.'%'))))
            ->orderBy('sort_order')
            ->limit(50)
            ->get()
            ->mapWithKeys(function (Person $person): array {
                $names = $person->translations->pluck('name')->filter()->unique()->implode(' / ');

                return [(int) $person->getKey() => trim($names.' (/'.$person->slug.($person->category ? ', '.$person->category : '').')')];
            })
            ->all();
    }

    public function linkFacultyMember(int $memberId, int $personId, int $userId): void
    {
        $member = FacultyMember::query()->with('translations', 'faculty')->findOrFail($memberId);
        $this->authorizeLinking($member, $userId);
        $person = Person::query()->with('translations')->find($personId);

        if (! $person instanceof Person) {
            throw new InvalidArgumentException('The leadership profile does not exist.');
        }

        if ($member->person_id !== null && (int) $member->person_id !== $personId) {
            throw new InvalidArgumentException('This faculty member is already linked to another leadership profile. Unlink it first.');
        }

        DB::transaction(fn () => $this->linkMemberToPerson($member, $person));

        $this->auditService->log('faculty_member.linked_to_person', $userId, FacultyMember::class, $memberId, ['person_id' => $personId, 'person_slug' => $person->slug]);
        $this->flushPublicCaches();
    }

    public function unlinkFacultyMember(int $memberId, int $userId): void
    {
        $member = FacultyMember::query()->with('faculty')->findOrFail($memberId);
        $this->authorizeLinking($member, $userId);
        $personId = $member->person_id;

        if ($personId === null) {
            return;
        }

        DB::transaction(function () use ($member, $personId): void {
            if ($member->faculty_id !== null) {
                PersonAppointment::query()
                    ->where('person_id', $personId)
                    ->where('type', 'faculty_member')
                    ->where('faculty_id', $member->faculty_id)
                    ->update(['is_enabled' => false]);
            }

            $member->forceFill(['person_id' => null])->save();
        });

        $this->auditService->log('faculty_member.unlinked_from_person', $userId, FacultyMember::class, $memberId, ['person_id' => (int) $personId]);
        $this->flushPublicCaches();
    }

    private function authorizeLinking(FacultyMember $member, int $userId): void
    {
        $user = User::query()->find($userId);
        $faculty = $member->faculty;
        $facultySlug = $faculty === null ? null : (string) ($faculty->public_slug ?: $faculty->slug);

        if (! $user instanceof User
            || ! $this->accessService->can($user, 'faculty_members.update')
            || ! $this->accessService->can($user, 'people.update')
            || ! $this->accessService->withinFacultyLimit($user, $facultySlug)) {
            throw new AuthorizationException('You may not link this faculty member to a leadership profile.');
        }
    }

    private function flushPublicCaches(): void
    {
        if (! $this->cacheService->flushTags(['public-pages', 'public-shell', 'about', 'facilities', 'research', 'seo', 'sitemap'])) {
            $this->cacheService->flushAll();
        }
    }

    /** @return Collection<int, array{keep: Researcher, merge: Collection<int, Researcher>}> */
    private function researcherGroups(): Collection
    {
        $researchers = Researcher::query()->withCount('publications')->get();

        return $this->groupBySameName($researchers, fn (Researcher $researcher): array => [(string) $researcher->full_name])
            ->map(function (Collection $group): array {
                // The one with more papers, then the fuller name.
                $sorted = $group->sortByDesc(fn (Researcher $researcher): array => [$researcher->publications_count, mb_strlen(trim($researcher->full_name))])->values();

                return ['keep' => $sorted->first(), 'merge' => $sorted->slice(1)->values()];
            });
    }

    /** @return array{0: Collection<int, array{keep: FacultyMember, merge: Collection<int, FacultyMember>}>, 1: list<string>} */
    private function memberGroups(): array
    {
        $members = FacultyMember::query()->with(['translations', 'educations'])->withCount(['researchPublications', 'councilMemberships'])->get();
        $skipped = [];

        $groups = $this->groupBySameName($members, fn (FacultyMember $member): array => $member->translations->pluck('full_name')->filter()->map(fn (mixed $name): string => (string) $name)->all())
            ->filter(function (Collection $group) use (&$skipped): bool {
                if ($group->pluck('faculty_id')->unique()->count() > 1) {
                    $skipped[] = $group->map(fn (FacultyMember $member): string => 'M'.$member->getKey().' /'.$member->slug)->implode(', ');

                    return false;
                }

                return true;
            })
            ->map(function (Collection $group): array {
                $sorted = $group->sortByDesc(fn (FacultyMember $member): array => [$this->completeness($member), $this->nameLength($member)])->values();

                return ['keep' => $sorted->first(), 'merge' => $sorted->slice(1)->values()];
            })
            ->values();

        return [$groups, $skipped];
    }

    /** @return Collection<int, array{member: FacultyMember, person: Person}> */
    private function memberPersonLinks(): Collection
    {
        $people = Person::query()->with('translations')->get();

        return FacultyMember::query()->whereNull('person_id')->with('translations')->get()
            ->map(function (FacultyMember $member) use ($people): ?array {
                $names = $member->translations->pluck('full_name')->filter()->map(fn (mixed $name): string => (string) $name)->all();
                $matches = $people->filter(fn (Person $person): bool => $this->sameName($names, $person->translations->pluck('name')->filter()->map(fn (mixed $name): string => (string) $name)->all()));

                return $matches->count() === 1 ? ['member' => $member, 'person' => $matches->first()] : null;
            })
            ->filter()
            ->values();
    }

    private function mergeResearcher(Researcher $keep, Researcher $duplicate): void
    {
        foreach ($duplicate->publications()->get() as $publication) {
            if (! $keep->publications()->whereKey($publication->getKey())->exists()) {
                $keep->publications()->attach($publication->getKey(), [
                    'role' => $publication->pivot->getAttribute('role'),
                    'sort_order' => $publication->pivot->getAttribute('sort_order'),
                    'affiliation_snapshot' => $publication->pivot->getAttribute('affiliation_snapshot'),
                ]);
            }
        }
        $duplicate->publications()->detach();

        if (mb_strlen(trim($duplicate->full_name)) > mb_strlen(trim($keep->full_name))) {
            $keep->full_name = trim($duplicate->full_name);
        }
        foreach (['email', 'phone', 'affiliation', 'faculty_id', 'faculty_member_id', 'notes'] as $field) {
            $keep->{$field} ??= $duplicate->{$field};
        }
        if ($duplicate->registration_status === 'registered') {
            $keep->registration_status = 'registered';
        }
        $keep->save();

        $duplicate->forceFill(['merged_into_id' => $keep->getKey()])->save();
        $duplicate->delete();
    }

    private function mergeMember(FacultyMember $keep, FacultyMember $duplicate): void
    {
        foreach (['email', 'phone', 'office_location', 'department_id', 'photo_media_id', 'cv_media_id', 'legacy_photo_path', 'legacy_cv_path', 'legacy_ar_cv_path', 'person_id'] as $field) {
            $keep->{$field} ??= $duplicate->{$field};
        }
        $keep->social_links = array_filter([...(is_array($duplicate->social_links) ? $duplicate->social_links : []), ...(is_array($keep->social_links) ? $keep->social_links : [])]) ?: null;
        $keep->save();

        foreach ($duplicate->translations as $translation) {
            $kept = $keep->translations()->firstOrNew(['locale' => $translation->locale]);
            // The fuller name wins; other text only fills what is empty.
            if (mb_strlen(trim((string) $translation->full_name)) > mb_strlen(trim((string) $kept->full_name))) {
                $kept->full_name = $translation->full_name;
            }
            foreach (['title', 'position', 'bio'] as $field) {
                if (blank($kept->{$field})) {
                    $kept->{$field} = $translation->{$field};
                }
            }
            if (blank($kept->specializations)) {
                $kept->specializations = $translation->specializations;
            }
            $kept->save();
        }

        if ($keep->educations()->doesntExist()) {
            $duplicate->educations()->update(['faculty_member_id' => $keep->getKey()]);
        }
        ResearchPublication::query()->where('faculty_member_id', $duplicate->getKey())->update(['faculty_member_id' => $keep->getKey()]);
        CouncilMember::query()->where('faculty_member_id', $duplicate->getKey())->update(['faculty_member_id' => $keep->getKey()]);
        Researcher::query()->where('faculty_member_id', $duplicate->getKey())->update(['faculty_member_id' => $keep->getKey()]);

        // A pending draft of the merged-away profile must not bring it back.
        CmsDraft::query()
            ->where('target_key', 'entity.faculty-member.'.$duplicate->getKey())
            ->whereIn('status', [PublicationStatus::Draft->value, PublicationStatus::Scheduled->value])
            ->update(['status' => PublicationStatus::Superseded->value, 'scheduled_at' => null]);

        foreach (['ar', 'en'] as $locale) {
            $this->redirect('/'.$locale.'/about/profile/'.$duplicate->slug, '/'.$locale.'/about/profile/'.$keep->slug, $locale);
        }

        $duplicate->delete();
    }

    /** The faculty member's profile becomes the leadership profile, which keeps their faculty placement. */
    private function linkMemberToPerson(FacultyMember $member, Person $person): void
    {
        // Nothing is copied onto the leadership profile: since 2026-10-20 its page
        // shows the linked member's content as it is built (ProfilePageService),
        // so later edits to the member show too. Copying the member's position
        // also used to hide the leadership role ("Dean") behind "Professor".

        if ($member->faculty_id !== null && PersonAppointment::query()->where('person_id', $person->getKey())->where('type', 'faculty_member')->where('faculty_id', $member->faculty_id)->doesntExist()) {
            $appointment = PersonAppointment::query()->create([
                'person_id' => $person->getKey(),
                'type' => 'faculty_member',
                'faculty_id' => $member->faculty_id,
                'department_id' => $member->department_id,
                'sort_order' => $member->sort_order,
                'is_enabled' => $member->is_enabled,
            ]);
            foreach ($member->translations as $translation) {
                $role = $translation->position ?: $translation->title;
                if (filled($role)) {
                    PersonAppointmentTranslation::query()->create(['person_appointment_id' => $appointment->getKey(), 'locale' => $translation->locale, 'role_override' => $role]);
                }
            }
        }

        ResearchPublication::query()->where('faculty_member_id', $member->getKey())->whereNull('person_id')->update(['person_id' => $person->getKey()]);
        CouncilMember::query()->where('faculty_member_id', $member->getKey())->whereNull('person_id')->update(['person_id' => $person->getKey()]);
        $member->forceFill(['person_id' => $person->getKey()])->save();
    }

    private function redirect(string $from, string $to, string $locale): void
    {
        $existing = DB::table('legacy_exact_redirects')->whereRaw('LOWER(legacy_path) = ?', [mb_strtolower($from)])->first();
        $payload = [
            'legacy_path' => $from,
            'destination_url' => $to,
            'status_code' => 301,
            'locale' => $locale,
            'is_active' => true,
            'notes' => 'Duplicate faculty member profile merged ('.self::APPROVAL_TOKEN.').',
            'updated_at' => now(),
        ];

        if ($existing !== null) {
            DB::table('legacy_exact_redirects')->where('id', $existing->id)->update($payload);

            return;
        }

        DB::table('legacy_exact_redirects')->insert([...$payload, 'hit_count' => 0, 'created_at' => now()]);
    }

    private function completeness(FacultyMember $member): int
    {
        $translations = $member->translations;

        return ($member->photo_media_id !== null || filled($member->legacy_photo_path) ? 4 : 0)
            + ($member->cv_media_id !== null || filled($member->legacy_cv_path) || filled($member->legacy_ar_cv_path) ? 3 : 0)
            + ($translations->contains(fn (FacultyMemberTranslation $translation): bool => filled($translation->bio)) ? 3 : 0)
            + ($member->publication_status === PublicationStatus::Published->value ? 2 : 0)
            + min(3, $member->educations->count())
            + (int) $member->research_publications_count
            + (int) $member->council_memberships_count
            + (filled($member->email) ? 1 : 0);
    }

    private function nameLength(FacultyMember $member): int
    {
        return (int) $member->translations->map(fn (FacultyMemberTranslation $translation): int => mb_strlen(trim((string) $translation->full_name)))->max();
    }

    /**
     * @template T of \Illuminate\Database\Eloquent\Model
     *
     * @param  Collection<int, T>  $records
     * @param  callable(T): list<string>  $names
     * @return Collection<int, Collection<int, T>>
     */
    private function groupBySameName(Collection $records, callable $names): Collection
    {
        $records = $records->values();
        $taken = [];
        $groups = [];

        foreach ($records as $index => $record) {
            if (isset($taken[$index])) {
                continue;
            }

            $group = [$record];
            foreach ($records as $other => $candidate) {
                if ($other > $index && ! isset($taken[$other]) && $this->sameName($names($record), $names($candidate))) {
                    $group[] = $candidate;
                    $taken[$other] = true;
                }
            }

            if (count($group) > 1) {
                $groups[] = collect($group);
            }
        }

        return collect($groups);
    }

    /**
     * @param  list<string>  $first
     * @param  list<string>  $second
     */
    private function sameName(array $first, array $second): bool
    {
        foreach ($first as $a) {
            foreach ($second as $b) {
                if (PersonName::same($a, $b)) {
                    return true;
                }
            }
        }

        return false;
    }
}
