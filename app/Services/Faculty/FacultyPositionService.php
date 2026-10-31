<?php

declare(strict_types=1);

namespace App\Services\Faculty;

use App\Contracts\Auth\AdminAccessServiceInterface;
use App\Contracts\Cms\CmsTargetRegistryInterface;
use App\Contracts\Faculty\FacultyPositionServiceInterface;
use App\Contracts\Shared\AuditServiceInterface;
use App\Contracts\Shared\CacheServiceInterface;
use App\DTOs\Faculty\FacultyPositionDTO;
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

    /** @var Collection<int, FacultyPositionDTO>|null */
    private ?Collection $positions = null;

    public function __construct(
        private readonly AdminAccessServiceInterface $access,
        private readonly CmsTargetRegistryInterface $targets,
        private readonly AuditServiceInterface $auditService,
        private readonly CacheServiceInterface $cacheService,
    ) {}

    public function all(): Collection
    {
        if ($this->positions instanceof Collection) {
            return $this->positions;
        }

        $rows = FacultyPosition::query()->with('translations')->get()->keyBy('slug');
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

        return $this->positions = collect([...$positions, ...$custom->values()->all()]);
    }

    public function find(string $slug): ?FacultyPositionDTO
    {
        return $this->all()->first(fn (FacultyPositionDTO $position): bool => $position->slug === $slug);
    }

    public function councilOptions(string $locale): array
    {
        return $this->all()
            ->reject(fn (FacultyPositionDTO $position): bool => $position->slug === self::HEAD_OF_DEPARTMENT)
            ->mapWithKeys(fn (FacultyPositionDTO $position): array => [$position->slug => $locale === 'ar' ? $position->nameAr : $position->nameEn])
            ->all();
    }

    public function save(?string $slug, string $nameAr, string $nameEn, string $placement, string $contextTargetKey, int $userId): FacultyPositionDTO
    {
        $this->authorize($contextTargetKey, $userId);

        $nameAr = trim($nameAr);
        $nameEn = trim($nameEn);
        $existing = $slug !== null ? $this->find($slug) : null;

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

        // Two positions with the same name would be told apart by nobody.
        foreach ($this->all() as $position) {
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

        $row = DB::transaction(function () use ($slug, $nameAr, $nameEn, $placement): FacultyPosition {
            $row = FacultyPosition::query()->firstOrNew(['slug' => $slug]);

            if (! $row->exists) {
                $row->sort_order = ((int) FacultyPosition::query()->max('sort_order')) + 1;
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
            'target_key' => $contextTargetKey,
        ]);

        // A renamed position shows on every faculty's council page and on its holders' profiles.
        $this->cacheService->flushTags(['public-pages', 'facilities', 'about']);
        $this->positions = null;

        $saved = $this->find($slug);

        if (! $saved instanceof FacultyPositionDTO) {
            throw new \RuntimeException('The position was not saved.');
        }

        return $saved;
    }

    private function authorize(string $targetKey, int $userId): void
    {
        $user = User::query()->find($userId);
        $target = $this->targets->find($targetKey);

        if (! $user instanceof User || (bool) $user->is_locked || $target === null || ! str_ends_with($targetKey, '.council') || ! $this->access->canManageTarget($user, $target, 'update')) {
            throw new AuthorizationException('This user is not authorized to manage faculty positions.');
        }
    }

    private function rowName(?FacultyPosition $row, string $locale): ?string
    {
        $name = trim((string) ($row?->translations->firstWhere('locale', $locale)?->name ?? ''));

        return $name !== '' ? $name : null;
    }
}
