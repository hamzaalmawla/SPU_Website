<?php

declare(strict_types=1);

namespace App\Services\Faculty;

use App\Contracts\Faculty\FacultyLeadershipServiceInterface;
use App\Contracts\Shared\AuditServiceInterface;
use App\Contracts\Shared\SlugServiceInterface;
use App\DTOs\Faculty\FacultyCouncilDTO;
use App\Enums\PublicationStatus;
use App\Models\Cms\CmsTargetContent;
use App\Models\Faculty\Faculty;
use App\Models\Media\MediaAsset;
use App\Models\Person\FacultyMember;
use App\Models\Person\Person;
use App\Support\MediaUrlResolver;
use DateTimeInterface;

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
            $role = trim((string) ($dean->translations->firstWhere('locale', $locale)?->role ?? ''));
            $positions[] = [
                'id' => 'position-dean',
                'type' => 'dean',
                'memberId' => (int) $dean->faculty_member_id,
                'title' => $role !== '' ? $role : $this->defaultTitle('dean', $locale),
                'onCouncil' => true,
            ];
        }

        return [
            'positions' => $positions,
            'councilMembers' => [],
            'includeDepartmentHeads' => true,
        ];
    }

    public function council(string $facultySlug, string $locale, ?array $content = null): FacultyCouncilDTO
    {
        $faculty = $this->faculty($facultySlug);

        if (! $faculty instanceof Faculty) {
            return new FacultyCouncilDTO([], []);
        }

        $content ??= $this->publishedLocaleContent('facilities.'.$faculty->public_slug.'.council', $locale)
            ?? $this->defaultCouncilContent((string) $faculty->public_slug, $locale);
        $positions = $this->positions($content);
        $extraMembers = $this->councilMembers($content);
        $heads = ($content['includeDepartmentHeads'] ?? true) !== false ? $this->departmentHeads($faculty, $locale) : [];

        $memberIds = array_values(array_unique([
            ...array_column($positions, 'memberId'),
            ...array_column($heads, 'memberId'),
            ...array_column($extraMembers, 'memberId'),
        ]));
        $cards = $this->publicCards($faculty, $locale, $memberIds);

        $typeOrder = array_flip(self::POSITION_TYPES);
        $orderedPositions = $positions;
        usort($orderedPositions, static fn (array $a, array $b): int => $typeOrder[$a['type']] <=> $typeOrder[$b['type']]);

        $publicPositions = [];
        foreach ($orderedPositions as $position) {
            $card = $cards[$position['memberId']] ?? null;

            if ($card === null) {
                continue;
            }

            $publicPositions[] = [
                'type' => $position['type'],
                'title' => $position['title'] !== '' ? $position['title'] : $this->defaultTitle($position['type'], $locale),
                'memberId' => $position['memberId'],
                ...$card,
            ];
        }

        // One entry per person: the dean chairs, then the other positions on the
        // council, the department heads and the extra members, in that order.
        $members = [];
        $addRole = function (int $memberId, string $role, bool $isChair = false) use (&$members, $cards): void {
            if (! isset($cards[$memberId])) {
                return;
            }

            $members[$memberId] ??= ['memberId' => $memberId, ...$cards[$memberId], 'roles' => [], 'isChair' => false];

            if ($role !== '' && ! in_array($role, $members[$memberId]['roles'], true)) {
                $members[$memberId]['roles'][] = $role;
            }

            $members[$memberId]['isChair'] = $members[$memberId]['isChair'] || $isChair;
        };

        foreach ($orderedPositions as $position) {
            // The dean always chairs the council.
            if ($position['onCouncil'] || $position['type'] === 'dean') {
                $title = $position['title'] !== '' ? $position['title'] : $this->defaultTitle($position['type'], $locale);
                $addRole($position['memberId'], $title, $position['type'] === 'dean');
            }
        }

        foreach ($heads as $head) {
            $addRole($head['memberId'], $head['role']);
        }

        foreach ($extraMembers as $member) {
            $addRole($member['memberId'], $member['role'] !== '' ? $member['role'] : ($locale === 'ar' ? 'عضو' : 'Member'));
        }

        return new FacultyCouncilDTO($publicPositions, array_values($members));
    }

    public function deanProfileSlug(string $facultySlug): ?string
    {
        $faculty = $this->faculty($facultySlug);
        $content = $faculty instanceof Faculty ? $this->publishedLocaleContent('facilities.'.$faculty->public_slug.'.council', 'ar') : null;

        if (! is_array($content)) {
            return null;
        }

        $dean = collect($this->positions($content))->firstWhere('type', 'dean');

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
        $councilIds = [
            ...$this->payloadMemberIds($payload, 'positions', 'memberId'),
            ...$this->payloadMemberIds($payload, 'councilMembers', 'memberId'),
        ];
        $staffIds = $this->staffIds($faculty, $councilIds);
        // A hidden profile would drop off the page, and the About › Leadership dean row
        // would fall back to its old copy (the previous dean's name).
        $publicIds = $this->staffIds($faculty, $councilIds, publicOnly: true);

        foreach (['ar', 'en'] as $locale) {
            $translation = is_array($payload['translations'][$locale] ?? null) ? $payload['translations'][$locale] : [];
            $deans = 0;
            $positions = [];
            $members = [];

            foreach ($this->localeList($payload, $locale, 'positions') as $position) {
                $type = $position['type'] ?? null;
                $memberId = $this->memberId($position['memberId'] ?? null);

                if (! in_array($type, self::POSITION_TYPES, true)) {
                    $errors[$locale][] = 'A position is the dean, a vice dean or a custom position.';
                }

                if ($memberId === null) {
                    $errors[$locale][] = 'Every position needs a staff member.';
                } elseif (! isset($staffIds[$memberId])) {
                    $errors[$locale][] = 'Every position holder must be one of this faculty\'s academic staff.';
                } elseif (! isset($publicIds[$memberId])) {
                    $errors[$locale][] = 'Every position holder needs a published profile.';
                }

                if ($type === 'custom' && trim((string) ($position['title'] ?? '')) === '') {
                    $errors[$locale][] = 'A custom position needs a title.';
                }

                $deans += $type === 'dean' ? 1 : 0;
                $positions[] = [(string) ($position['id'] ?? ''), $type, $memberId, (bool) ($position['onCouncil'] ?? false)];
            }

            if ($deans !== 1) {
                $errors[$locale][] = 'The faculty must have exactly one dean.';
            }

            foreach ($this->localeList($payload, $locale, 'councilMembers') as $member) {
                $memberId = $this->memberId($member['memberId'] ?? null);

                if ($memberId === null || ! isset($staffIds[$memberId])) {
                    $errors[$locale][] = 'Every council member must be one of this faculty\'s academic staff.';
                } elseif (! isset($publicIds[$memberId])) {
                    $errors[$locale][] = 'Every council member needs a published profile.';
                }

                $members[] = [(string) ($member['id'] ?? ''), $memberId];
            }

            $shared[$locale] = [$positions, $members, ($translation['includeDepartmentHeads'] ?? true) !== false];
        }

        if ($shared['ar'] !== $shared['en']) {
            $errors['translations'][] = 'Positions and council members must be the same in Arabic and English.';
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
            $deanByLocale[$locale] = collect($this->positions($this->localeContent($payload, $locale)))->firstWhere('type', 'dean');
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
                'role' => $facultyName === '' ? $this->defaultTitle('dean', $locale) : ($locale === 'ar' ? 'عميد '.$facultyName : 'Dean of the '.$facultyName),
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
     * Heads of the published departments page, in page order. A head switched off the
     * council there is left out; a head's own title replaces the default role.
     *
     * @return list<array{memberId: int, role: string}>
     */
    private function departmentHeads(Faculty $faculty, string $locale): array
    {
        $content = $this->publishedLocaleContent('facilities.'.$faculty->public_slug.'.departments', $locale) ?? [];
        $heads = [];

        foreach ($this->listOfArrays($content['items'] ?? []) as $item) {
            $memberId = $this->memberId($item['headMemberId'] ?? null);
            $title = trim((string) ($item['title'] ?? ''));

            if ($memberId === null || $title === '' || ($item['headOnCouncil'] ?? true) === false) {
                continue;
            }

            $headTitle = trim((string) ($item['headTitle'] ?? ''));
            $heads[] = [
                'memberId' => $memberId,
                'role' => match (true) {
                    $headTitle !== '' => $headTitle,
                    $locale === 'ar' => str_starts_with($title, 'قسم') ? 'رئيس '.$title : 'رئيس قسم '.$title,
                    default => 'Head, '.$title,
                },
            ];
        }

        return $heads;
    }

    /**
     * @param  array<string, mixed>  $content
     * @return list<array{id: string, type: string, memberId: int, title: string, onCouncil: bool}>
     */
    private function positions(array $content): array
    {
        $positions = [];

        foreach ($this->listOfArrays($content['positions'] ?? []) as $position) {
            $memberId = $this->memberId($position['memberId'] ?? null);
            $type = $position['type'] ?? null;

            if ($memberId === null || ! in_array($type, self::POSITION_TYPES, true)) {
                continue;
            }

            $positions[] = [
                'id' => (string) ($position['id'] ?? ''),
                'type' => $type,
                'memberId' => $memberId,
                'title' => trim((string) ($position['title'] ?? '')),
                'onCouncil' => (bool) ($position['onCouncil'] ?? false),
            ];
        }

        return $positions;
    }

    /**
     * @param  array<string, mixed>  $content
     * @return list<array{memberId: int, role: string}>
     */
    private function councilMembers(array $content): array
    {
        $members = [];

        foreach ($this->listOfArrays($content['councilMembers'] ?? []) as $member) {
            $memberId = $this->memberId($member['memberId'] ?? null);

            if ($memberId !== null) {
                $members[] = ['memberId' => $memberId, 'role' => trim((string) ($member['role'] ?? ''))];
            }
        }

        return $members;
    }

    /**
     * Public cards of this faculty's staff, by profile id. A hidden profile, or one of
     * another faculty, is left out.
     *
     * @param  list<int>  $memberIds
     * @return array<int, array{name: string, image: ?string, url: string}>
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

    private function defaultTitle(string $type, string $locale): string
    {
        return match ($type) {
            'dean' => $locale === 'ar' ? 'عميد الكلية' : 'Dean',
            'vice_dean' => $locale === 'ar' ? 'نائب العميد' : 'Vice Dean',
            default => '',
        };
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
