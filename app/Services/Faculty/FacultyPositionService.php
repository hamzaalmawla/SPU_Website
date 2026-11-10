<?php

declare(strict_types=1);

namespace App\Services\Faculty;

use App\Contracts\Auth\AdminAccessServiceInterface;
use App\Contracts\Cms\CmsTargetRegistryInterface;
use App\Contracts\Faculty\FacultyPositionServiceInterface;
use App\Contracts\Shared\AuditServiceInterface;
use App\Contracts\Shared\CacheServiceInterface;
use App\DTOs\Faculty\FacultyPositionDTO;
use App\Models\Cms\CmsDraft;
use App\Models\Cms\CmsTargetContent;
use App\Models\Faculty\Faculty;
use App\Models\Faculty\FacultyPosition;
use App\Models\User\User;
use App\Support\SearchTextNormalizer;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class FacultyPositionService implements FacultyPositionServiceInterface
{
    /** @var array<string, array{ar: string, en: string, placement: string, sort: int}> */
    private const BUILT_IN = [
        self::DEAN => ['ar' => 'عميد الكلية', 'en' => 'Dean', 'placement' => 'leadership', 'sort' => 1],
        self::VICE_DEAN => ['ar' => 'نائب العميد', 'en' => 'Vice Dean', 'placement' => 'leadership', 'sort' => 2],
        self::HEAD_OF_DEPARTMENT => ['ar' => 'رئيس القسم', 'en' => 'Head of Department', 'placement' => 'heads', 'sort' => 3],
    ];

    /** @var array<string, string> */
    private const FACULTY_ALIASES = [
        'ai' => 'artificial-intelligence',
        'ai-engineering' => 'artificial-intelligence',
        'construction' => 'building-construction-engineering',
        'business' => 'business-administration',
    ];

    /** @var array<string, array{positions: Collection<int, FacultyPositionDTO>, shared: array<string, true>}> by faculty */
    private array $catalogs = [];

    public function __construct(
        private readonly AdminAccessServiceInterface $access,
        private readonly CmsTargetRegistryInterface $targets,
        private readonly AuditServiceInterface $auditService,
        private readonly CacheServiceInterface $cacheService,
    ) {}

    public function all(string $facultySlug): Collection
    {
        $faculty = $this->faculty($facultySlug);
        // Read fresh: a page that starts using a row of no faculty offers it from then on.
        $used = $faculty instanceof Faculty ? $this->sharedSlugsUsedBy($faculty) : [];

        // A row of no faculty is offered only where a council page uses it; the others belong to other faculties now.
        return $this->catalog($facultySlug)['positions']
            ->reject(fn (FacultyPositionDTO $position): bool => ! $position->isBuiltIn && isset($this->catalog($facultySlug)['shared'][$position->slug]) && ! isset($used[$position->slug]))
            ->values();
    }

    public function find(string $facultySlug, string $slug): ?FacultyPositionDTO
    {
        // Any row of no faculty still reads, so a page saved with it keeps its name.
        return $this->catalog($facultySlug)['positions']->first(fn (FacultyPositionDTO $position): bool => $position->slug === $slug);
    }

    /**
     * The faculty's positions, every row of no faculty included, and the slugs of those rows of no faculty.
     *
     * @return array{positions: Collection<int, FacultyPositionDTO>, shared: array<string, true>}
     */
    private function catalog(string $facultySlug): array
    {
        $faculty = $this->faculty($facultySlug);
        $key = $faculty instanceof Faculty ? (string) $faculty->public_slug : '';

        if (isset($this->catalogs[$key])) {
            return $this->catalogs[$key];
        }

        // The faculty's own rows win over a row saved before positions belonged to a faculty.
        $rows = FacultyPosition::query()
            ->with('translations')
            ->where(fn ($query) => $query->whereNull('faculty_id')->when($faculty instanceof Faculty, fn ($own) => $own->orWhere('faculty_id', $faculty->getKey())))
            ->orderByRaw('case when faculty_id is null then 1 else 0 end')
            ->get()
            ->unique('slug')
            ->keyBy('slug');
        $positions = [];

        foreach (self::BUILT_IN as $slug => $builtIn) {
            $row = $rows->get($slug);
            $positions[] = new FacultyPositionDTO(
                slug: $slug,
                nameAr: $this->rowName($row, 'ar') ?? $builtIn['ar'],
                nameEn: $this->rowName($row, 'en') ?? $builtIn['en'],
                // Only the vice dean may move; the dean always leads and heads have their own cards.
                placement: $slug === self::VICE_DEAN && $row instanceof FacultyPosition && in_array($row->placement, self::PLACEMENTS, true) ? $row->placement : $builtIn['placement'],
                isBuiltIn: true,
                sortOrder: $builtIn['sort'],
            );
        }

        $custom = $rows
            ->reject(fn (FacultyPosition $row): bool => isset(self::BUILT_IN[$row->slug]))
            ->sortBy([['sort_order', 'asc'], ['id', 'asc']])
            ->map(fn (FacultyPosition $row): FacultyPositionDTO => new FacultyPositionDTO(
                slug: (string) $row->slug,
                nameAr: $this->rowName($row, 'ar') ?? (string) $row->slug,
                nameEn: $this->rowName($row, 'en') ?? $this->rowName($row, 'ar') ?? (string) $row->slug,
                placement: in_array($row->placement, self::PLACEMENTS, true) ? (string) $row->placement : 'council',
                isBuiltIn: false,
                sortOrder: (int) $row->sort_order,
            ));

        $shared = $rows
            ->filter(fn (FacultyPosition $row): bool => $row->faculty_id === null)
            ->mapWithKeys(fn (FacultyPosition $row): array => [(string) $row->slug => true])
            ->all();

        return $this->catalogs[$key] = [
            'positions' => collect([...$positions, ...$custom->values()->all()]),
            'shared' => $shared,
        ];
    }

    public function councilOptions(string $facultySlug, string $locale): array
    {
        return $this->all($facultySlug)
            ->reject(fn (FacultyPositionDTO $position): bool => $position->slug === self::HEAD_OF_DEPARTMENT)
            ->mapWithKeys(fn (FacultyPositionDTO $position): array => [$position->slug => $locale === 'ar' ? $position->nameAr : $position->nameEn])
            ->all();
    }

    public function save(?string $slug, string $nameAr, string $nameEn, string $placement, string $contextTargetKey, int $userId): FacultyPositionDTO
    {
        $faculty = $this->authorize($contextTargetKey, $userId);
        $facultySlug = (string) $faculty->public_slug;

        $nameAr = trim($nameAr);
        $nameEn = trim($nameEn);
        $existing = $slug !== null ? $this->find($facultySlug, $slug) : null;

        if ($slug !== null && ! $existing instanceof FacultyPositionDTO) {
            throw ValidationException::withMessages(['position' => __('admin.faculty_workspace.editor.position_errors.unknown')]);
        }

        $errors = [];

        if ($nameAr === '') {
            $errors['nameAr'] = __('admin.faculty_workspace.editor.position_errors.name_required');
        }

        if ($nameEn === '') {
            $errors['nameEn'] = __('admin.faculty_workspace.editor.position_errors.name_required');
        }

        // Two positions of one faculty with the same name would be told apart by nobody.
        foreach ($this->all($facultySlug) as $position) {
            if ($position->slug === $slug) {
                continue;
            }

            if ($nameAr !== '' && SearchTextNormalizer::normalize($position->nameAr) === SearchTextNormalizer::normalize($nameAr)) {
                $errors['nameAr'] = __('admin.faculty_workspace.editor.position_errors.name_taken');
            }

            if ($nameEn !== '' && Str::lower($position->nameEn) === Str::lower($nameEn)) {
                $errors['nameEn'] = __('admin.faculty_workspace.editor.position_errors.name_taken');
            }
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        $placement = in_array($placement, self::PLACEMENTS, true) ? $placement : 'council';
        $slug ??= 'position-'.Str::lower(Str::random(10));

        // Editing a position of no faculty (or a built-in one) gives this faculty its own row
        // with the same slug, so its council page keeps pointing at it and no other faculty changes.
        $row = DB::transaction(function () use ($faculty, $slug, $existing, $nameAr, $nameEn, $placement): FacultyPosition {
            $row = FacultyPosition::query()->firstOrNew(['faculty_id' => $faculty->getKey(), 'slug' => $slug]);

            if (! $row->exists) {
                $row->sort_order = $existing instanceof FacultyPositionDTO && ! $existing->isBuiltIn
                    ? $existing->sortOrder
                    : ((int) FacultyPosition::query()->where('faculty_id', $faculty->getKey())->max('sort_order')) + 1;
            }

            $row->placement = $placement;
            $row->save();

            foreach (['ar' => $nameAr, 'en' => $nameEn] as $locale => $name) {
                $row->translations()->updateOrCreate(['locale' => $locale], ['name' => $name]);
            }

            return $row;
        });

        $this->auditService->log($existing instanceof FacultyPositionDTO ? 'faculty.position.updated' : 'faculty.position.created', $userId, FacultyPosition::class, (int) $row->getKey(), [
            'slug' => $slug,
            'name_ar' => $nameAr,
            'name_en' => $nameEn,
            'placement' => $placement,
            'previous' => $existing instanceof FacultyPositionDTO ? ['name_ar' => $existing->nameAr, 'name_en' => $existing->nameEn, 'placement' => $existing->placement] : null,
            'faculty' => $facultySlug,
            'target_key' => $contextTargetKey,
        ]);

        // A renamed position shows on the faculty's council page and on its holders' profiles.
        $this->cacheService->flushTags(['public-pages', 'facilities', 'about']);
        $this->catalogs = [];

        $saved = $this->find($facultySlug, $slug);

        if (! $saved instanceof FacultyPositionDTO) {
            throw new \RuntimeException('The position was not saved.');
        }

        return $saved;
    }

    private function authorize(string $targetKey, int $userId): Faculty
    {
        $user = User::query()->find($userId);
        $target = $this->targets->find($targetKey);
        $parts = explode('.', $targetKey);
        $faculty = count($parts) === 3 && $parts[0] === 'facilities' && $parts[2] === 'council' ? $this->faculty($parts[1]) : null;

        if (! $user instanceof User || (bool) $user->is_locked || $target === null || ! $faculty instanceof Faculty || ! $this->access->canManageTarget($user, $target, 'update')) {
            throw new AuthorizationException('This user is not authorized to manage faculty positions.');
        }

        return $faculty;
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

        return $faculty instanceof Faculty && is_string($faculty->public_slug) && $faculty->public_slug !== '' ? $faculty : null;
    }

    /**
     * The positions of no faculty that this faculty's council page uses, published or in a draft.
     *
     * @return array<string, true>
     */
    private function sharedSlugsUsedBy(Faculty $faculty): array
    {
        $targetKey = 'facilities.'.$faculty->public_slug.'.council';
        $payloads = [
            CmsTargetContent::query()->where('target_key', $targetKey)->value('payload_json'),
            ...CmsDraft::query()->where('target_key', $targetKey)->pluck('payload_json')->all(),
        ];
        $slugs = [];

        foreach ($payloads as $payload) {
            $payload = is_string($payload) ? json_decode($payload, true) : $payload;

            foreach (['ar', 'en'] as $locale) {
                $positions = is_array($payload['translations'][$locale]['positions'] ?? null) ? $payload['translations'][$locale]['positions'] : [];

                foreach ($positions as $position) {
                    $slug = is_array($position) ? trim((string) ($position['positionSlug'] ?? '')) : '';

                    if ($slug !== '') {
                        $slugs[$slug] = true;
                    }
                }
            }
        }

        return $slugs;
    }

    private function rowName(?FacultyPosition $row, string $locale): ?string
    {
        $name = trim((string) ($row?->translations->firstWhere('locale', $locale)?->name ?? ''));

        return $name !== '' ? $name : null;
    }
}
