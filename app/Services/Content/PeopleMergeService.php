<?php

declare(strict_types=1);

namespace App\Services\Content;

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
use App\Models\Research\Researcher;
use App\Models\Research\ResearchPublication;
use App\Support\PersonName;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Merges records that are one person: researchers entered twice and faculty
 * members listed twice in the same faculty. Nothing is hard-deleted: a
 * merged-away record is soft-deleted, and a merged profile's address
 * redirects to the kept one. Leadership rows are tied to their faculty
 * profile by people:unify (PeopleUnifyServiceInterface), not here.
 *
 * Someone listed in two different faculties is left alone: that is usually
 * one person teaching in both. A profile with no faculty (a leader's, made
 * from the leadership row) joins the faculty profile of the same name, which
 * is kept, and the leadership row moves to it.
 */
final class PeopleMergeService implements PeopleMergeServiceInterface
{
    public const APPROVAL_TOKEN = 'people-merge-20261024';

    public function __construct(
        private readonly AuditServiceInterface $auditService,
        private readonly CacheServiceInterface $cacheService,
    ) {}

    public function merge(bool $write, ?string $approval): PeopleMergeResultDTO
    {
        if ($write && $approval !== self::APPROVAL_TOKEN) {
            throw new InvalidArgumentException('Writing requires --approve='.self::APPROVAL_TOKEN.'.');
        }

        $researcherGroups = $this->researcherGroups();
        [$memberGroups, $skipped] = $this->memberGroups();
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

        if ($write) {
            DB::transaction(function () use ($researcherGroups, $memberGroups): void {
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
            });

            $this->auditService->log('people.merged', null, Person::class, null, [
                'batch' => self::APPROVAL_TOKEN,
                'researchers' => $researcherGroups->sum(fn (array $group): int => $group['merge']->count()),
                'faculty_members' => $memberGroups->sum(fn (array $group): int => $group['merge']->count()),
            ]);
            $this->cacheService->flushTags(['public-pages', 'public-shell', 'about', 'facilities', 'research', 'seo', 'sitemap', 'continuity']);
        }

        return new PeopleMergeResultDTO(
            written: $write,
            researchersMerged: $researcherGroups->sum(fn (array $group): int => $group['merge']->count()),
            membersMerged: $memberGroups->sum(fn (array $group): int => $group['merge']->count()),
            leftAlone: count($skipped),
            lines: $lines,
        );
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
                if ($group->pluck('faculty_id')->filter()->unique()->count() > 1) {
                    $skipped[] = $group->map(fn (FacultyMember $member): string => 'M'.$member->getKey().' /'.$member->slug)->implode(', ');

                    return false;
                }

                return true;
            })
            ->map(function (Collection $group): array {
                $sorted = $group->sortByDesc(fn (FacultyMember $member): array => [$member->faculty_id !== null, $this->completeness($member), $this->nameLength($member)])->values();

                return ['keep' => $sorted->first(), 'merge' => $sorted->slice(1)->values()];
            })
            ->values();

        return [$groups, $skipped];
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
        Person::query()->where('faculty_member_id', $duplicate->getKey())->update(['faculty_member_id' => $keep->getKey()]);
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
