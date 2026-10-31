<?php

declare(strict_types=1);

namespace App\Services\Faculty;

use App\Contracts\Faculty\FacultyLeadershipServiceInterface;
use App\Contracts\Faculty\FacultyPositionServiceInterface;
use App\Contracts\Shared\AuditServiceInterface;
use App\Contracts\Shared\SlugServiceInterface;
use App\DTOs\Faculty\FacultyCouncilDTO;
use App\DTOs\Faculty\FacultyPositionDTO;
use App\Enums\PublicationStatus;
use App\Models\Cms\CmsTargetContent;
use App\Models\Faculty\Faculty;
use App\Models\Media\MediaAsset;
use App\Models\Person\FacultyMember;
use App\Models\Person\Person;
use App\Support\MediaUrlResolver;
use DateTimeInterface;
use Illuminate\Support\Str;

final class FacultyLeadershipService implements FacultyLeadershipServiceInterface
{
    private const DEPARTMENT_TYPES = ['specialization', 'section'];

    /** @var array<string, string> */
    private const FACULTY_ALIASES = [
        'ai' => 'artificial-intelligence',
        'ai-engineering' => 'artificial-intelligence',
        'construction' => 'building-construction-engineering',
        'business' => 'business-administration',
    ];

    public function __construct(
        private readonly AuditServiceInterface $auditService,
        private readonly SlugServiceInterface $slugService,
        private readonly FacultyPositionServiceInterface $positionService,
    ) {}

    public function isCouncilTarget(string $targetKey): bool
    {
        return $this->facultySlugFromTarget($targetKey, 'council') !== null;
    }

    public function defaultCouncilContent(string $facultySlug, string $locale): array
    {
        $faculty = $this->faculty($facultySlug);
        $positions = [];
        $dean = $faculty instanceof Faculty ? $this->leadershipDean($faculty) : null;

        if ($dean instanceof Person) {
            $positions[] = [
                'id' => 'position-dean',
                'positionSlug' => FacultyPositionServiceInterface::DEAN,
                'memberId' => (int) $dean->faculty_member_id,
            ];
        }

        return [
            'positions' => $positions,
            'includeDepartmentHeads' => true,
        ];
    }

    public function council(string $facultySlug, string $locale, ?array $content = null): FacultyCouncilDTO
    {
        $faculty = $this->faculty($facultySlug);

        if (! $faculty instanceof Faculty) {
            return new FacultyCouncilDTO(null, [], [], []);
        }

        $content ??= $this->councilContent($faculty, $locale);
        $positions = $this->positions($content, $locale);
        $heads = ($content['includeDepartmentHeads'] ?? true) !== false
            ? array_values(array_filter($this->departmentHeads($faculty, $locale), static fn (array $head): bool => $head['onCouncil']))
            : [];
        $cards = $this->publicCards($faculty, $locale, array_values(array_unique([
            ...array_column($positions, 'memberId'),
            ...array_column($heads, 'memberId'),
        ])));

        // The dean leads; everyone else has one card per place (leadership or the
        // council list) with every position they hold there.
        $dean = null;
        $leadership = [];
        $members = [];

        foreach ($positions as $position) {
            $card = $cards[$position['memberId']] ?? null;

            if ($card === null) {
                continue;
            }

            if ($position['slug'] === FacultyPositionServiceInterface::DEAN) {
                $dean ??= ['memberId' => $position['memberId'], ...$card, 'title' => $position['name']];

                continue;
            }

            if ($position['placement'] === 'leadership') {
                $leadership[$position['memberId']] ??= ['memberId' => $position['memberId'], ...$card, 'titles' => []];
                $leadership[$position['memberId']]['titles'] = array_values(array_unique([...$leadership[$position['memberId']]['titles'], $position['name']]));

                continue;
            }

            $members[$position['memberId']] ??= ['memberId' => $position['memberId'], ...$card, 'roles' => []];
            $members[$position['memberId']]['roles'] = array_values(array_unique([...$members[$position['memberId']]['roles'], $position['name']]));
        }

        $publicHeads = [];

        foreach ($heads as $head) {
            $card = $cards[$head['memberId']] ?? null;

            if ($card !== null) {
                $publicHeads[] = [
                    'department' => $head['department'],
                    'type' => $head['type'],
                    'memberId' => $head['memberId'],
                    ...$card,
                    'title' => $head['title'],
                ];
            }
        }

        return new FacultyCouncilDTO($dean, array_values($leadership), array_values($members), $publicHeads);
    }

    public function memberPositions(string $facultySlug, string $locale): array
    {
        $faculty = $this->faculty($facultySlug);

        if (! $faculty instanceof Faculty) {
            return [];
        }

        $held = [];

        // Only a published council page: until then a profile keeps the position typed on it.
        $published = $this->publishedLocaleContent('facilities.'.$faculty->public_slug.'.council', $locale) ?? [];

        foreach ($this->positions($published, $locale) as $position) {
            $held[$position['memberId']][] = $position['name'];
        }

        // A head of department heads it whether or not they sit on the council.
        foreach ($this->departmentHeads($faculty, $locale) as $head) {
            $held[$head['memberId']][] = $head['role'];
        }

        return array_map(static fn (array $names): array => array_values(array_unique($names)), $held);
    }

    public function positionsOfMember(int $memberId, string $locale): array
    {
        $facultyId = FacultyMember::query()->whereKey($memberId)->value('faculty_id');
        $facultySlug = is_numeric($facultyId) ? Faculty::query()->whereKey((int) $facultyId)->value('public_slug') : null;

        return is_string($facultySlug) && $facultySlug !== '' ? ($this->memberPositions($facultySlug, $locale)[$memberId] ?? []) : [];
    }

    public function deanProfileSlug(string $facultySlug): ?string
    {
        $faculty = $this->faculty($facultySlug);
        $content = $faculty instanceof Faculty ? $this->publishedLocaleContent('facilities.'.$faculty->public_slug.'.council', 'ar') : null;

        if (! is_array($content)) {
            return null;
        }

        $dean = collect($this->positions($content, 'ar'))->firstWhere('slug', FacultyPositionServiceInterface::DEAN);

        if (! is_array($dean)) {
            return null;
        }

        $slug = FacultyMember::query()->public()->whereKey($dean['memberId'])->where('faculty_id', $faculty->getKey())->value('slug');

        return is_string($slug) && $slug !== '' ? $slug : null;
    }

    public function managesDean(?string $facultyScopeSlug): bool
    {
        $facultyScopeSlug = trim((string) $facultyScopeSlug);

        return $facultyScopeSlug !== '' && $this->deanProfileSlug($facultyScopeSlug) !== null;
    }

    public function departmentsValidationErrors(string $targetKey, array $payload): array
    {
        $faculty = $this->faculty((string) $this->facultySlugFromTarget($targetKey, 'departments'));

        if (! $faculty instanceof Faculty) {
            return [];
        }

        $errors = [];
        $shared = [];
        $staffIds = $this->staffIds($faculty, $this->payloadMemberIds($payload, 'items', 'headMemberId'));

        foreach (['ar', 'en'] as $locale) {
            $mapping = [];

            foreach ($this->localeList($payload, $locale, 'items') as $item) {
                $slug = trim((string) ($item['slug'] ?? ''));
                $type = $item['type'] ?? null;
                $studyPlanId = trim((string) ($item['studyPlanDepartmentId'] ?? ''));
                $headId = $this->memberId($item['headMemberId'] ?? null);

                if ($type !== null && ! in_array($type, self::DEPARTMENT_TYPES, true)) {
                    $errors[$locale][] = 'A department is either a specialization or a section.';
                }

                if ($type === 'specialization' && $studyPlanId === '') {
                    $errors[$locale][] = 'A specialization must link to its study plan program.';
                }

                if ($type === 'section' && $studyPlanId !== '') {
                    $errors[$locale][] = 'A section has no study plan.';
                }

                if ($headId !== null && ! isset($staffIds[$headId])) {
                    $errors[$locale][] = 'A department head must be one of this faculty\'s academic staff.';
                }

                if ($slug !== '') {
                    $mapping[$slug] = [$type, $headId, $headId !== null && ($item['headOnCouncil'] ?? true) !== false];
                }
            }

            ksort($mapping);
            $shared[$locale] = $mapping;
        }

        // A department listed in both languages has the same type, head and council seat in each.
        $inBoth = array_intersect_key($shared['ar'], $shared['en']);

        if ($inBoth !== array_intersect_key($shared['en'], $shared['ar'])) {
            $errors['translations'][] = 'Each department must have the same type, head and council seat in Arabic and English.';
        }

        return $this->uniqueErrors($errors);
    }

    public function councilValidationErrors(string $targetKey, array $payload): array
    {
        $faculty = $this->faculty((string) $this->facultySlugFromTarget($targetKey, 'council'));

        if (! $faculty instanceof Faculty) {
            return [];
        }

        $errors = [];
        $shared = [];
        $councilIds = $this->payloadMemberIds($payload, 'positions', 'memberId');
        $staffIds = $this->staffIds($faculty, $councilIds);
        // A hidden profile would drop off the page, and the About › Leadership dean row
        // would fall back to its old copy (the previous dean's name).
        $publicIds = $this->staffIds($faculty, $councilIds, publicOnly: true);
        $options = $this->positionService->councilOptions('en');

        foreach (['ar', 'en'] as $locale) {
            $translation = $this->localeContent($payload, $locale);
            $deans = 0;
            $rows = [];
            $held = [];

            foreach ($this->localeList($payload, $locale, 'positions') as $position) {
                $slug = $this->positionSlug($position);
                $memberId = $this->memberId($position['memberId'] ?? null);

                if ($slug === null || ! isset($options[$slug])) {
                    $errors[$locale][] = 'Every position must be picked from the positions list.';
                }

                if ($memberId === null) {
                    $errors[$locale][] = 'Every position needs a staff member.';
                } elseif (! isset($staffIds[$memberId])) {
                    $errors[$locale][] = 'Every position holder must be one of this faculty\'s academic staff.';
                } elseif (! isset($publicIds[$memberId])) {
                    $errors[$locale][] = 'Every position holder needs a published profile.';
                }

                if ($slug !== null && $memberId !== null) {
                    if (isset($held[$slug.':'.$memberId])) {
                        $errors[$locale][] = 'A person holds the same position twice.';
                    }

                    $held[$slug.':'.$memberId] = true;
                }

                $deans += $slug === FacultyPositionServiceInterface::DEAN ? 1 : 0;
                $rows[] = [(string) ($position['id'] ?? ''), $slug, $memberId];
            }

            if ($deans !== 1) {
                $errors[$locale][] = 'The faculty must have exactly one dean.';
            }

            $shared[$locale] = [$rows, ($translation['includeDepartmentHeads'] ?? true) !== false];
        }

        if ($shared['ar'] !== $shared['en']) {
            $errors['translations'][] = 'Positions must be the same in Arabic and English.';
        }

        return $this->uniqueErrors($errors);
    }

    public function publishTarget(string $targetKey, array $payload, DateTimeInterface $publishedAt, int $userId): void
    {
        $faculty = $this->faculty((string) $this->facultySlugFromTarget($targetKey, 'council'));

        if (! $faculty instanceof Faculty) {
            return;
        }

        $deanByLocale = [];
        foreach (['ar', 'en'] as $locale) {
            $deanByLocale[$locale] = collect($this->positions($this->localeContent($payload, $locale), $locale))->firstWhere('slug', FacultyPositionServiceInterface::DEAN);
        }

        $memberId = is_array($deanByLocale['ar']) ? $deanByLocale['ar']['memberId'] : null;
        $member = $memberId !== null
            ? FacultyMember::query()->with('translations')->whereKey($memberId)->where('faculty_id', $faculty->getKey())->first()
            : null;

        if (! $member instanceof FacultyMember) {
            return;
        }

        // The dean row shown today (public, linked to this faculty's staff), else a public dean row of
        // the faculty, else a hidden one: a hidden former dean's row is never picked over a public row.
        $person = $this->leadershipDean($faculty)
            ?? $this->leadershipDean($faculty, linkedOnly: false)
            ?? $this->leadershipDean($faculty, publicOnly: false, linkedOnly: false);

        if ($person instanceof Person) {
            if ((int) $person->faculty_member_id === (int) $member->getKey()) {
                return;
            }

            $previous = $person->faculty_member_id;
            $person->forceFill(['faculty_member_id' => (int) $member->getKey()])->save();
            $this->auditService->log('faculty.council.dean_synced', $userId, Person::class, (int) $person->getKey(), [
                'target_key' => $targetKey,
                'previous_faculty_member_id' => $previous,
                'faculty_member_id' => (int) $member->getKey(),
            ]);

            return;
        }

        // The faculty has no dean on About › Leadership yet: add one for the published dean.
        $faculty->loadMissing('translations');
        $englishName = trim((string) ($member->translations->firstWhere('locale', 'en')?->full_name ?? $member->slug));
        $slug = $this->slugService->generate($englishName !== '' ? $englishName.' dean' : 'dean', Person::class, 'en');
        $person = Person::query()->create([
            'faculty_member_id' => (int) $member->getKey(),
            'slug' => $slug,
            'category' => 'dean',
            'faculty_scope_slug' => (string) ($faculty->faculty_scope_slug ?: $faculty->public_slug),
            'profile_url' => '/about/profile/'.$slug,
            'sort_order' => ((int) Person::query()->max('sort_order')) + 1,
            'is_enabled' => true,
            'publication_status' => PublicationStatus::Published->value,
            'published_at' => $publishedAt,
        ]);

        foreach (['ar', 'en'] as $locale) {
            $name = trim((string) ($member->translations->firstWhere('locale', $locale)?->full_name
                ?? $member->translations->first()?->full_name ?? $member->slug));
            $facultyName = trim((string) ($faculty->translations->firstWhere('locale', $locale)?->name ?? ''));
            $person->translations()->create([
                'locale' => $locale,
                'name' => $name,
                'role' => $facultyName === '' ? $this->positionName(FacultyPositionServiceInterface::DEAN, $locale) : ($locale === 'ar' ? 'عميد '.$facultyName : 'Dean of the '.$facultyName),
            ]);
        }

        $this->auditService->log('faculty.council.dean_synced', $userId, Person::class, (int) $person->getKey(), [
            'target_key' => $targetKey,
            'previous_faculty_member_id' => null,
            'faculty_member_id' => (int) $member->getKey(),
        ]);
    }

    /** The faculty's dean row on About › Leadership: public and pointing at this faculty's staff unless asked otherwise. */
    private function leadershipDean(Faculty $faculty, bool $publicOnly = true, bool $linkedOnly = true): ?Person
    {
        $scopes = array_values(array_unique(array_filter([(string) $faculty->faculty_scope_slug, (string) $faculty->public_slug, (string) $faculty->slug])));
        $query = Person::query()
            ->with('translations')
            ->where('category', 'dean')
            ->whereIn('faculty_scope_slug', $scopes)
            ->orderBy('sort_order')
            ->orderBy('id');

        if ($publicOnly) {
            $query->public();
        }

        if ($linkedOnly) {
            $query->whereNotNull('faculty_member_id')
                ->whereHas('profileMember', fn ($member) => $member->where('faculty_id', $faculty->getKey()));
        }

        $person = $query->first();

        return $person instanceof Person ? $person : null;
    }

    /**
     * Heads of the published departments page, in page order. `title` is the head's own
     * title or the head of department position's name; `role` adds the department for
     * a profile. `onCouncil` is off for a head the departments page keeps off the council.
     *
     * @return list<array{memberId: int, department: string, type: string, title: string, role: string, onCouncil: bool}>
     */
    private function departmentHeads(Faculty $faculty, string $locale): array
    {
        $content = $this->publishedLocaleContent('facilities.'.$faculty->public_slug.'.departments', $locale) ?? [];
        $headName = $this->positionName(FacultyPositionServiceInterface::HEAD_OF_DEPARTMENT, $locale);
        $heads = [];

        foreach ($this->listOfArrays($content['items'] ?? []) as $item) {
            $memberId = $this->memberId($item['headMemberId'] ?? null);
            $department = trim((string) ($item['title'] ?? ''));

            if ($memberId === null || $department === '') {
                continue;
            }

            $headTitle = trim((string) ($item['headTitle'] ?? ''));
            $type = ($item['type'] ?? null) === 'specialization' || trim((string) ($item['studyPlanDepartmentId'] ?? '')) !== '' ? 'specialization' : 'section';
            $heads[] = [
                'memberId' => $memberId,
                'department' => $department,
                'type' => $type,
                'title' => $headTitle !== '' ? $headTitle : $headName,
                'role' => $headTitle !== '' ? $headTitle : $this->headRole($headName, $department, $locale),
                'onCouncil' => ($item['headOnCouncil'] ?? true) !== false,
            ];
        }

        return $heads;
    }

    /**
     * The council page content to show: the published page, or the default before one is.
     *
     * @return array<string, mixed>
     */
    private function councilContent(Faculty $faculty, string $locale): array
    {
        return $this->publishedLocaleContent('facilities.'.$faculty->public_slug.'.council', $locale)
            ?? $this->defaultCouncilContent((string) $faculty->public_slug, $locale);
    }

    /**
     * The positions held, each with its name and placement from the positions list. A page
     * saved before the list existed still reads: its dean and vice dean become those
     * positions (keeping a title typed then), a custom position its title in the leadership
     * cards, and an extra council member their role in the council list.
     *
     * @param  array<string, mixed>  $content
     * @return list<array{id: string, slug: string, memberId: int, name: string, placement: string}>
     */
    private function positions(array $content, string $locale): array
    {
        $positions = [];

        foreach ($this->listOfArrays($content['positions'] ?? []) as $position) {
            $memberId = $this->memberId($position['memberId'] ?? null);
            $slug = $this->positionSlug($position);
            $legacyTitle = isset($position['positionSlug']) ? '' : trim((string) ($position['title'] ?? ''));
            $catalog = $slug !== null ? $this->positionService->find($slug) : null;

            if ($memberId === null) {
                continue;
            }

            if ($catalog instanceof FacultyPositionDTO && $catalog->slug !== FacultyPositionServiceInterface::HEAD_OF_DEPARTMENT) {
                $positions[] = [
                    'id' => (string) ($position['id'] ?? ''),
                    'slug' => $catalog->slug,
                    'memberId' => $memberId,
                    'name' => $legacyTitle !== '' ? $legacyTitle : ($locale === 'ar' ? $catalog->nameAr : $catalog->nameEn),
                    'placement' => $catalog->placement,
                ];
            } elseif ($slug === null && ($position['type'] ?? null) === 'custom' && $legacyTitle !== '') {
                $positions[] = ['id' => (string) ($position['id'] ?? ''), 'slug' => '', 'memberId' => $memberId, 'name' => $legacyTitle, 'placement' => 'leadership'];
            }
        }

        foreach ($this->listOfArrays($content['councilMembers'] ?? []) as $member) {
            $memberId = $this->memberId($member['memberId'] ?? null);
            $role = trim((string) ($member['role'] ?? ''));

            if ($memberId !== null) {
                $positions[] = ['id' => (string) ($member['id'] ?? ''), 'slug' => '', 'memberId' => $memberId, 'name' => $role !== '' ? $role : ($locale === 'ar' ? 'عضو' : 'Member'), 'placement' => 'council'];
            }
        }

        return $positions;
    }

    /**
     * A council row's position: its pick from the list, or the dean or vice dean of a page
     * saved before the list existed.
     *
     * @param  array<string, mixed>  $position
     */
    private function positionSlug(array $position): ?string
    {
        $slug = trim((string) ($position['positionSlug'] ?? ''));

        if ($slug !== '') {
            return $slug;
        }

        $type = $position['type'] ?? null;

        return in_array($type, [FacultyPositionServiceInterface::DEAN, FacultyPositionServiceInterface::VICE_DEAN], true) ? $type : null;
    }

    /** "رئيس قسم الجراحة" / "Head, Department of Surgery" while the position keeps its built-in name. */
    private function headRole(string $headName, string $department, string $locale): string
    {
        return match (true) {
            $locale === 'ar' && $headName === 'رئيس القسم' => str_starts_with($department, 'قسم') ? 'رئيس '.$department : 'رئيس قسم '.$department,
            $locale !== 'ar' && $headName === 'Head of Department' => 'Head, '.$department,
            default => $headName.($locale === 'ar' ? '، ' : ', ').$department,
        };
    }

    private function positionName(string $slug, string $locale): string
    {
        $position = $this->positionService->find($slug);

        return $position === null ? $slug : ($locale === 'ar' ? $position->nameAr : $position->nameEn);
    }

    /**
     * Public cards of this faculty's staff, by profile id. A hidden profile, or one of
     * another faculty, is left out.
     *
     * @param  list<int>  $memberIds
     * @return array<int, array{name: string, image: ?string, url: string, bio: string}>
     */
    private function publicCards(Faculty $faculty, string $locale, array $memberIds): array
    {
        if ($memberIds === []) {
            return [];
        }

        $cards = [];
        $members = FacultyMember::query()
            ->public()
            ->whereKey($memberIds)
            ->where('faculty_id', $faculty->getKey())
            ->with(['translations', 'photoMedia'])
            ->get();

        foreach ($members as $member) {
            $translation = $member->translations->firstWhere('locale', $locale)
                ?? $member->translations->firstWhere('locale', 'ar')
                ?? $member->translations->firstWhere('locale', 'en');
            $name = trim((string) ($translation?->full_name ?? ''));

            if ($name === '') {
                continue;
            }

            $cards[(int) $member->getKey()] = [
                'name' => $name,
                'image' => $member->photoMedia instanceof MediaAsset
                    ? MediaUrlResolver::resolveImage($member->photoMedia->webp_path, $member->photoMedia->path, $member->photoMedia->disk)
                    : MediaUrlResolver::resolveLegacy($member->legacy_photo_path),
                'url' => '/'.$locale.'/about/profile/'.$member->slug,
                // The start of the profile's bio, for the dean's card.
                'bio' => Str::limit(trim((string) preg_replace('/\s+/u', ' ', strip_tags((string) ($translation?->bio ?? '')))), 260),
            ];
        }

        return $cards;
    }

    /**
     * The ids of the given profiles that belong to this faculty (and are public, when asked).
     *
     * @param  list<int>  $memberIds
     * @return array<int, true>
     */
    private function staffIds(Faculty $faculty, array $memberIds, bool $publicOnly = false): array
    {
        if ($memberIds === []) {
            return [];
        }

        return array_fill_keys(
            FacultyMember::query()
                ->whereKey(array_values(array_unique($memberIds)))
                ->where('faculty_id', $faculty->getKey())
                ->when($publicOnly, fn ($query) => $query->public())
                ->pluck('id')
                ->map(fn (mixed $id): int => (int) $id)
                ->all(),
            true,
        );
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return list<int>
     */
    private function payloadMemberIds(array $payload, string $list, string $field): array
    {
        $ids = [];

        foreach (['ar', 'en'] as $locale) {
            foreach ($this->localeList($payload, $locale, $list) as $item) {
                $id = $this->memberId($item[$field] ?? null);

                if ($id !== null) {
                    $ids[] = $id;
                }
            }
        }

        return $ids;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return list<array<string, mixed>>
     */
    private function localeList(array $payload, string $locale, string $list): array
    {
        return $this->listOfArrays($this->localeContent($payload, $locale)[$list] ?? []);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function localeContent(array $payload, string $locale): array
    {
        return is_array($payload['translations'][$locale] ?? null) ? $payload['translations'][$locale] : [];
    }

    /** @return array<string, mixed>|null */
    private function publishedLocaleContent(string $targetKey, string $locale): ?array
    {
        $published = CmsTargetContent::query()
            ->where('target_key', $targetKey)
            ->where('status', PublicationStatus::Published->value)
            ->first();

        if (! $published instanceof CmsTargetContent || ! is_array($published->payload_json)) {
            return null;
        }

        $content = $published->payload_json['translations'][$locale] ?? null;

        return is_array($content) ? $content : null;
    }

    private function faculty(string $facultySlug): ?Faculty
    {
        $facultySlug = self::FACULTY_ALIASES[$facultySlug] ?? $facultySlug;

        if ($facultySlug === '') {
            return null;
        }

        $faculty = Faculty::query()
            ->where(fn ($query) => $query->where('public_slug', $facultySlug)->orWhere('faculty_scope_slug', $facultySlug)->orWhere('slug', $facultySlug))
            ->orderByRaw('case when public_slug = ? then 0 else 1 end', [$facultySlug])
            ->first();

        return $faculty instanceof Faculty ? $faculty : null;
    }

    private function facultySlugFromTarget(string $targetKey, string $subpageSlug): ?string
    {
        $parts = explode('.', $targetKey);

        return count($parts) === 3 && $parts[0] === 'facilities' && $parts[2] === $subpageSlug && $parts[1] !== '' ? $parts[1] : null;
    }

    private function memberId(mixed $value): ?int
    {
        return is_numeric($value) && (int) $value > 0 ? (int) $value : null;
    }

    /** @return list<array<string, mixed>> */
    private function listOfArrays(mixed $items): array
    {
        return is_array($items) ? array_values(array_filter($items, 'is_array')) : [];
    }

    /**
     * @param  array<string, array<int, string>>  $errors
     * @return array<string, array<int, string>>
     */
    private function uniqueErrors(array $errors): array
    {
        return array_map(static fn (array $messages): array => array_values(array_unique($messages)), $errors);
    }
}
