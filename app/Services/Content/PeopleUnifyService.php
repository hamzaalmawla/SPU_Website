<?php

declare(strict_types=1);

namespace App\Services\Content;

use App\Contracts\Content\PeopleUnifyServiceInterface;
use App\Contracts\Shared\AuditServiceInterface;
use App\Contracts\Shared\CacheServiceInterface;
use App\Contracts\Shared\SlugServiceInterface;
use App\DTOs\Content\PeopleUnifyResultDTO;
use App\Enums\PublicationStatus;
use App\Models\Cms\CmsDraft;
use App\Models\Faculty\Faculty;
use App\Models\Person\CouncilMember;
use App\Models\Person\FacultyMember;
use App\Models\Person\FacultyMemberEducation;
use App\Models\Person\FacultyMemberTranslation;
use App\Models\Person\Person;
use App\Models\Person\PersonAppointment;
use App\Models\Person\PersonEducation;
use App\Models\Person\PersonTranslation;
use App\Models\Research\ResearchPublication;
use App\Support\PersonName;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;

/**
 * One profile per person. Each leadership row gets the faculty profile that
 * is the same person and keeps only its role; the profile becomes the one
 * place their name, photo and bio are edited.
 *
 * Faculty-member data is never overwritten or deleted: a value the profile
 * already has stays, and only empty fields are filled from the leadership
 * row. A profile with an unpublished draft is not filled at all, and a
 * leadership row with an unpublished draft is left for an editor. Before
 * writing, every row it touches is saved to a JSON snapshot.
 */
final class PeopleUnifyService implements PeopleUnifyServiceInterface
{
    public const APPROVAL_TOKEN = 'people-unify-20261023';

    private const SNAPSHOT_DIRECTORY = 'private/people-unify';

    /** @var list<string> */
    private const COPIED_COLUMNS = ['email', 'phone', 'office_location', 'photo_media_id', 'cv_media_id', 'legacy_photo_path', 'legacy_cv_path', 'legacy_ar_cv_path'];

    public function __construct(
        private readonly AuditServiceInterface $auditService,
        private readonly CacheServiceInterface $cacheService,
        private readonly SlugServiceInterface $slugService,
    ) {}

    public function unify(bool $write, ?string $approval): PeopleUnifyResultDTO
    {
        if ($write && $approval !== self::APPROVAL_TOKEN) {
            throw new InvalidArgumentException('Writing requires --approve='.self::APPROVAL_TOKEN.'.');
        }

        $persons = Person::query()
            ->with(['translations', 'educations.translations', 'appointments'])
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();
        $members = FacultyMember::query()->with(['translations', 'educations'])->get();
        $draftTargets = CmsDraft::query()
            ->whereIn('status', [PublicationStatus::Draft->value, PublicationStatus::Scheduled->value])
            ->where(fn ($query) => $query->where('target_key', 'like', 'entity.person.%')->orWhere('target_key', 'like', 'entity.faculty-member.%'))
            ->pluck('target_key')
            ->flip()
            ->all();

        $plans = [];
        $skipped = [];
        foreach ($persons as $person) {
            $label = 'P'.$person->getKey().' /'.$person->slug.($person->category ? ' ('.$person->category.')' : '');

            if (isset($draftTargets['entity.person.'.$person->getKey()])) {
                $skipped[] = $label.': has an unpublished draft. Publish or discard it, then run again.';

                continue;
            }

            $plan = $this->plan($person, $members, $draftTargets);
            if (is_string($plan)) {
                $skipped[] = $label.': '.$plan;

                continue;
            }

            $plans[] = ['label' => $label, ...$plan];
        }

        $lines = [];
        foreach ($plans as $plan) {
            $lines[] = $plan['label'].' -> '.$plan['target'].' ['.$plan['how'].']';
            foreach ($plan['fills'] as $fill) {
                $lines[] = '    fill: '.$fill;
            }
            foreach ($plan['differs'] as $difference) {
                $lines[] = '    differs (profile kept): '.$difference;
            }
            if ($plan['fillsSkipped'] !== null) {
                $lines[] = '    not filled: '.$plan['fillsSkipped'];
            }
        }

        $snapshotPath = null;
        if ($write && $plans !== []) {
            $snapshotPath = $this->snapshot($plans);
            DB::transaction(function () use ($plans): void {
                foreach ($plans as $plan) {
                    $this->apply($plan);
                }
            });
            if (! $this->cacheService->flushTags(['public-pages', 'public-shell', 'about', 'facilities', 'research', 'seo', 'sitemap', 'continuity'])) {
                $this->cacheService->flushAll();
            }
        }

        $count = static fn (string $how): int => count(array_filter($plans, static fn (array $plan): bool => $plan['how'] === $how));

        return new PeopleUnifyResultDTO(
            written: $write,
            linkedExisting: $count('existing link'),
            linkedByName: $count('same name'),
            profilesCreated: $count('new profile'),
            alreadyUnified: $count('already unified'),
            fieldsFilled: array_sum(array_map(static fn (array $plan): int => count($plan['fills']), $plans)),
            lines: $lines,
            skipped: $skipped,
            snapshotPath: $snapshotPath,
        );
    }

    /**
     * @param  Collection<int, FacultyMember>  $members
     * @param  array<string, int>  $draftTargets
     * @return array<string, mixed>|string The plan, or why the row is left alone
     */
    private function plan(Person $person, Collection $members, array $draftTargets): array|string
    {
        $names = $this->personNames($person);
        if ($names === []) {
            return 'has no name in either language.';
        }

        $member = null;
        $how = null;
        if ($person->faculty_member_id !== null) {
            $member = $members->firstWhere('id', (int) $person->faculty_member_id);
            $how = 'already unified';
        }
        if ($member === null) {
            $member = $members->where('person_id', (int) $person->getKey())->sortByDesc('updated_at')->first();
            $how = $member !== null ? 'existing link' : null;
        }
        if ($member === null) {
            $matches = $members->filter(fn (FacultyMember $candidate): bool => $this->sameName($names, $this->memberNames($candidate)));
            if ($matches->count() > 1) {
                $facultyId = $this->personFacultyId($person);
                $narrowed = $facultyId !== null ? $matches->where('faculty_id', $facultyId) : collect();
                if ($narrowed->count() !== 1) {
                    return 'several profiles have this name ('.$matches->map(fn (FacultyMember $candidate): string => 'M'.$candidate->getKey().' /'.$candidate->slug)->implode(', ').'). Pick one in the leadership editor.';
                }
                $matches = $narrowed;
            }
            $member = $matches->first();
            $how = $member !== null ? 'same name' : 'new profile';

            // A profile whose name is probably theirs (a title or a middle
            // name apart) is not guessed at, and no second profile is made
            // beside it: an editor picks it in the leadership editor.
            if ($member === null) {
                $likely = $members->filter(fn (FacultyMember $candidate): bool => $this->likelySameName($names, $this->memberNames($candidate)));
                if ($likely->isNotEmpty()) {
                    return 'probably one of '.$likely->map(fn (FacultyMember $candidate): string => 'M'.$candidate->getKey().' /'.$candidate->slug.' "'.($this->memberNames($candidate)[0] ?? '').'"')->implode(', ').'. Pick the profile in the leadership editor.';
                }
            }
        }

        if (! $member instanceof FacultyMember) {
            return [
                'how' => 'new profile',
                'target' => 'new profile /'.$this->newProfileSlug($person),
                'person' => $person,
                'member' => null,
                'fills' => ['everything (new profile from the leadership row)'],
                'differs' => [],
                'fillsSkipped' => null,
                'changes' => null,
            ];
        }

        $target = 'M'.$member->getKey().' /'.$member->slug;
        $differs = $this->differences($person, $member);

        if (isset($draftTargets['entity.faculty-member.'.$member->getKey()])) {
            return [
                'how' => $how,
                'target' => $target,
                'person' => $person,
                'member' => $member,
                'fills' => [],
                'differs' => $differs,
                'fillsSkipped' => 'the profile has an unpublished draft, so nothing is copied into it.',
                'changes' => ['columns' => [], 'translations' => [], 'educations' => false, 'social_links' => null],
            ];
        }

        $changes = $this->fillChanges($person, $member);

        return [
            'how' => $how,
            'target' => $target,
            'person' => $person,
            'member' => $member,
            'fills' => $this->describeFills($changes),
            'differs' => $differs,
            'fillsSkipped' => null,
            'changes' => $changes,
        ];
    }

    /**
     * What the leadership row would add to the profile: only fields the
     * profile leaves empty.
     *
     * @return array{columns: array<string, mixed>, translations: array<string, array{create: bool, fields: array<string, mixed>}>, educations: bool, social_links: array<string, string>|null}
     */
    private function fillChanges(Person $person, FacultyMember $member): array
    {
        $columns = [];
        foreach (self::COPIED_COLUMNS as $column) {
            $value = $person->getAttribute($column);
            if (blank($member->getAttribute($column)) && filled($value)) {
                $columns[$column] = $value;
            }
        }
        // A photo kept only as a path in the old image column.
        if (blank($member->photo_media_id) && blank($member->legacy_photo_path) && ! isset($columns['photo_media_id']) && ! isset($columns['legacy_photo_path']) && filled($person->image)) {
            $columns['legacy_photo_path'] = $person->image;
        }

        $links = $this->personLinks($person);
        $memberLinks = is_array($member->social_links) ? $member->social_links : [];
        $newLinks = array_diff_key($links, array_filter($memberLinks, 'filled'));
        $socialLinks = $newLinks !== [] ? [...$memberLinks, ...$newLinks] : null;

        $translations = [];
        foreach ($person->translations as $translation) {
            $locale = (string) $translation->locale;
            $existing = $member->translations->firstWhere('locale', $locale);
            $source = [
                'full_name' => $translation->name,
                'title' => $translation->title,
                'bio' => $translation->bio,
                'specializations' => $translation->specializations,
            ];

            if (! $existing instanceof FacultyMemberTranslation) {
                if (filled($translation->name)) {
                    $translations[$locale] = ['create' => true, 'fields' => array_filter($source, 'filled')];
                }

                continue;
            }

            $fields = [];
            foreach ($source as $field => $value) {
                if (blank($existing->getAttribute($field)) && filled($value)) {
                    $fields[$field] = $value;
                }
            }
            if ($fields !== []) {
                $translations[$locale] = ['create' => false, 'fields' => $fields];
            }
        }

        return [
            'columns' => $columns,
            'translations' => $translations,
            'educations' => $member->educations->isEmpty() && $person->educations->isNotEmpty(),
            'social_links' => $socialLinks,
        ];
    }

    /**
     * @param  array{columns: array<string, mixed>, translations: array<string, array{create: bool, fields: array<string, mixed>}>, educations: bool, social_links: array<string, string>|null}  $changes
     * @return list<string>
     */
    private function describeFills(array $changes): array
    {
        $fills = array_map(static fn (string $column): string => $column, array_keys($changes['columns']));
        foreach ($changes['translations'] as $locale => $translation) {
            $fills[] = $translation['create']
                ? $locale.' text (the profile has none in '.$locale.')'
                : $locale.' '.implode(', ', array_keys($translation['fields']));
        }
        if ($changes['educations']) {
            $fills[] = 'education';
        }
        if ($changes['social_links'] !== null) {
            $fills[] = 'links';
        }

        return $fills;
    }

    /** @return list<string> Fields both records fill in differently */
    private function differences(Person $person, FacultyMember $member): array
    {
        $differs = [];
        foreach ($person->translations as $translation) {
            $memberTranslation = $member->translations->firstWhere('locale', $translation->locale);
            if (! $memberTranslation instanceof FacultyMemberTranslation) {
                continue;
            }

            foreach (['full_name' => 'name', 'title' => 'title', 'bio' => 'bio'] as $memberField => $personField) {
                $mine = trim((string) $memberTranslation->getAttribute($memberField));
                $theirs = trim((string) $translation->getAttribute($personField));
                if ($mine !== '' && $theirs !== '' && $mine !== $theirs) {
                    $differs[] = $translation->locale.' '.$personField.($personField === 'bio' ? '' : ': "'.$mine.'" (profile) / "'.$theirs.'" (leadership)');
                }
            }
        }
        if ($member->photo_media_id !== null && $person->photo_media_id !== null && (int) $member->photo_media_id !== (int) $person->photo_media_id) {
            $differs[] = 'photo';
        }

        return $differs;
    }

    /** @param array<string, mixed> $plan */
    private function apply(array $plan): void
    {
        /** @var Person $person */
        $person = $plan['person'];
        $member = $plan['member'];
        $created = false;

        if (! $member instanceof FacultyMember) {
            $member = $this->createProfile($person);
            $created = true;
        } else {
            $this->fill($member, $person, $plan['changes']);
        }

        $person->forceFill(['faculty_member_id' => $member->getKey()])->save();

        $papers = ResearchPublication::query()->where('person_id', $person->getKey())->whereNull('faculty_member_id')->update(['faculty_member_id' => $member->getKey()]);
        $councils = CouncilMember::query()->where('person_id', $person->getKey())->whereNull('faculty_member_id')->update(['faculty_member_id' => $member->getKey()]);

        $this->auditService->log('people.unified', null, Person::class, (int) $person->getKey(), [
            'batch' => self::APPROVAL_TOKEN,
            'faculty_member_id' => (int) $member->getKey(),
            'how' => $plan['how'],
            'profile_created' => $created,
            'filled' => $plan['fills'],
            'papers_repointed' => $papers,
            'council_seats_repointed' => $councils,
        ]);
    }

    /** @param array{columns: array<string, mixed>, translations: array<string, array{create: bool, fields: array<string, mixed>}>, educations: bool, social_links: array<string, string>|null} $changes */
    private function fill(FacultyMember $member, Person $person, array $changes): void
    {
        // Re-read and fill only what is still empty, so nothing an editor saved
        // since the dry run is overwritten.
        $member->refresh();
        foreach ($changes['columns'] as $column => $value) {
            if (blank($member->getAttribute($column))) {
                $member->setAttribute($column, $value);
            }
        }
        if ($changes['social_links'] !== null) {
            $current = is_array($member->social_links) ? $member->social_links : [];
            $member->setAttribute('social_links', [...$changes['social_links'], ...array_filter($current, 'filled')]);
        }
        $member->save();

        foreach ($changes['translations'] as $locale => $translation) {
            $existing = FacultyMemberTranslation::query()->where('faculty_member_id', $member->getKey())->where('locale', $locale)->first();
            if (! $existing instanceof FacultyMemberTranslation) {
                FacultyMemberTranslation::query()->create(['faculty_member_id' => $member->getKey(), 'locale' => $locale, ...$translation['fields']]);

                continue;
            }
            foreach ($translation['fields'] as $field => $value) {
                if (blank($existing->getAttribute($field))) {
                    $existing->setAttribute($field, $value);
                }
            }
            $existing->save();
        }

        if ($changes['educations'] && FacultyMemberEducation::query()->where('faculty_member_id', $member->getKey())->doesntExist()) {
            $this->copyEducations($person, $member);
        }
    }

    private function createProfile(Person $person): FacultyMember
    {
        $appointment = $person->appointments->first(fn (PersonAppointment $item): bool => $item->type === 'faculty_member' && $item->is_enabled && $item->faculty_id !== null);

        $member = FacultyMember::query()->create([
            'slug' => $this->newProfileSlug($person),
            'faculty_id' => $appointment?->faculty_id,
            'department_id' => $appointment?->department_id,
            'email' => $person->email,
            'phone' => $person->phone,
            'office_location' => $person->office_location,
            'social_links' => $this->personLinks($person) ?: null,
            'photo_media_id' => $person->photo_media_id,
            'cv_media_id' => $person->cv_media_id,
            'legacy_photo_path' => $person->legacy_photo_path ?: ($person->photo_media_id === null ? $person->image : null),
            'legacy_cv_path' => $person->legacy_cv_path,
            'legacy_ar_cv_path' => $person->legacy_ar_cv_path,
            'sort_order' => (int) FacultyMember::query()->max('sort_order') + 10,
            'is_enabled' => (bool) $person->is_enabled,
            'publication_status' => $person->publication_status ?? PublicationStatus::Draft->value,
            'published_at' => $person->published_at,
        ]);

        foreach ($person->translations as $translation) {
            if (blank($translation->name)) {
                continue;
            }
            FacultyMemberTranslation::query()->create([
                'faculty_member_id' => $member->getKey(),
                'locale' => $translation->locale,
                'full_name' => $translation->name,
                'title' => $translation->title,
                'bio' => $translation->bio,
                'specializations' => $translation->specializations,
            ]);
        }

        $this->copyEducations($person, $member);

        return $member;
    }

    private function copyEducations(Person $person, FacultyMember $member): void
    {
        foreach ($person->educations as $education) {
            /** @var PersonEducation $education */
            $copy = FacultyMemberEducation::query()->create([
                'faculty_member_id' => $member->getKey(),
                'sort_order' => (int) $education->sort_order,
                'is_enabled' => (bool) $education->is_enabled,
            ]);
            foreach ($education->translations as $translation) {
                $copy->translations()->create([
                    'locale' => $translation->locale,
                    'degree' => $translation->degree,
                    'institution' => $translation->institution,
                    'field_of_study' => $translation->field_of_study,
                    'year_start' => $translation->year_start,
                    'year_end' => $translation->year_end,
                    'description' => $translation->description,
                ]);
            }
        }
    }

    /**
     * Every row the run touches, as it was, so it can be put back.
     *
     * @param  list<array<string, mixed>>  $plans
     */
    private function snapshot(array $plans): string
    {
        $personIds = array_map(static fn (array $plan): int => (int) $plan['person']->getKey(), $plans);
        $memberIds = array_values(array_filter(array_map(static fn (array $plan): ?int => $plan['member'] instanceof FacultyMember ? (int) $plan['member']->getKey() : null, $plans)));

        $data = [
            'batch' => self::APPROVAL_TOKEN,
            'taken_at' => now()->toIso8601String(),
            'persons' => DB::table('persons')->whereIn('id', $personIds)->get(),
            'person_translations' => DB::table('person_translations')->whereIn('person_id', $personIds)->get(),
            'faculty_members' => DB::table('faculty_members')->whereIn('id', $memberIds)->get(),
            'faculty_member_translations' => DB::table('faculty_member_translations')->whereIn('faculty_member_id', $memberIds)->get(),
            'faculty_member_educations' => DB::table('faculty_member_educations')->whereIn('faculty_member_id', $memberIds)->get(),
            'research_publications' => DB::table('research_publications')->whereIn('person_id', $personIds)->get(['id', 'person_id', 'faculty_member_id']),
            'council_members' => DB::table('council_members')->whereIn('person_id', $personIds)->get(['id', 'person_id', 'faculty_member_id']),
        ];

        $path = self::SNAPSHOT_DIRECTORY.'/snapshot-'.now()->format('Ymd-His').'.json';
        Storage::disk('local')->put($path, (string) json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));

        // The local disk's root differs by environment, so report where the file really is.
        return str_replace([base_path().DIRECTORY_SEPARATOR, '\\'], ['', '/'], Storage::disk('local')->path($path));
    }

    private function newProfileSlug(Person $person): string
    {
        $slug = (string) $person->slug;
        if ($slug !== '' && FacultyMember::withTrashed()->where('slug', $slug)->doesntExist()) {
            return $slug;
        }

        $englishName = (string) ($person->translations->firstWhere('locale', 'en')?->name ?? '');

        return $this->slugService->generate($englishName !== '' ? $englishName : 'faculty-member', FacultyMember::class, 'en');
    }

    /** @return array<string, string> */
    private function personLinks(Person $person): array
    {
        $links = array_filter(is_array($person->social_links) ? $person->social_links : [], 'filled');
        if (filled($person->orcid_url)) {
            $links['orcid'] ??= (string) $person->orcid_url;
        }
        if (filled($person->scholar_url)) {
            $links['scholar'] ??= (string) $person->scholar_url;
        }

        return array_map('strval', $links);
    }

    private function personFacultyId(Person $person): ?int
    {
        $appointment = $person->appointments->first(fn (PersonAppointment $item): bool => $item->faculty_id !== null);
        if ($appointment instanceof PersonAppointment) {
            return (int) $appointment->faculty_id;
        }

        $scope = (string) $person->faculty_scope_slug;
        $id = $scope !== '' ? Faculty::query()->where(fn ($query) => $query->where('faculty_scope_slug', $scope)->orWhere('public_slug', $scope)->orWhere('slug', $scope))->value('id') : null;

        return $id !== null ? (int) $id : null;
    }

    /** @return list<string> */
    private function personNames(Person $person): array
    {
        return $person->translations->map(fn (PersonTranslation $translation): string => trim((string) $translation->name))->filter()->values()->all();
    }

    /** @return list<string> */
    private function memberNames(FacultyMember $member): array
    {
        return $member->translations->map(fn (FacultyMemberTranslation $translation): string => trim((string) $translation->full_name))->filter()->values()->all();
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

    /**
     * @param  list<string>  $first
     * @param  list<string>  $second
     */
    private function likelySameName(array $first, array $second): bool
    {
        foreach ($first as $a) {
            foreach ($second as $b) {
                if (PersonName::likelySame($a, $b)) {
                    return true;
                }
            }
        }

        return false;
    }
}
