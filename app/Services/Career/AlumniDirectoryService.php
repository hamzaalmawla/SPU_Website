<?php

declare(strict_types=1);

namespace App\Services\Career;

use App\Contracts\Career\AlumniDirectoryServiceInterface;
use App\DTOs\Career\AlumniDirectoryPageDTO;
use App\Models\Career\Alumni;
use App\Models\Career\AlumniTranslation;
use App\Models\Faculty\Department;
use App\Models\Faculty\DepartmentTranslation;
use App\Models\Faculty\Faculty;
use App\Models\Faculty\FacultyTranslation;
use App\Models\Media\MediaAsset;
use App\Models\Shared\MigrationLog;
use App\Support\MediaUrlResolver;
use Illuminate\Database\Eloquent\Builder;

final class AlumniDirectoryService implements AlumniDirectoryServiceInterface
{
    private const PER_PAGE = 12;

    public function getDirectory(string $locale, array $filters = []): ?AlumniDirectoryPageDTO
    {
        $locale = in_array($locale, ['ar', 'en'], true) ? $locale : 'ar';
        $records = $this->records($locale);

        if ($records === []) {
            return null;
        }

        $filterOptions = $this->filterOptions($records);
        $normalizedFilters = $this->normalizeFilters($filters, $filterOptions);
        $filtered = collect($records)->filter(function (array $record) use ($normalizedFilters): bool {
            if ($normalizedFilters['q'] !== '' && ! str_contains(
                mb_strtolower((string) $record['_searchText']),
                mb_strtolower($normalizedFilters['q']),
            )) {
                return false;
            }

            if ($normalizedFilters['year'] !== '' && (string) ($record['graduationYear'] ?? '') !== $normalizedFilters['year']) {
                return false;
            }

            if ($normalizedFilters['faculty'] !== '' && $record['_facultySlug'] !== $normalizedFilters['faculty']) {
                return false;
            }

            return $normalizedFilters['department'] === '' || $record['_departmentSlug'] === $normalizedFilters['department'];
        })->values();

        $total = $filtered->count();
        $totalPages = max(1, (int) ceil($total / self::PER_PAGE));
        $currentPage = min($normalizedFilters['page'], $totalPages);
        $offset = ($currentPage - 1) * self::PER_PAGE;
        $items = $filtered->slice($offset, self::PER_PAGE)
            ->map(fn (array $record): array => $this->publicItem($record))
            ->values()
            ->all();

        return new AlumniDirectoryPageDTO(
            locale: $locale,
            direction: $locale === 'ar' ? 'rtl' : 'ltr',
            items: $items,
            filters: [...$normalizedFilters, 'page' => $currentPage],
            filterOptions: $filterOptions,
            pagination: [
                'current_page' => $currentPage,
                'per_page' => self::PER_PAGE,
                'total_items' => $total,
                'total_pages' => $totalPages,
                'from' => $total === 0 ? 0 : $offset + 1,
                'to' => min($total, $offset + self::PER_PAGE),
            ],
            seoTitle: $locale === 'ar' ? 'دليل الخريجين | الجامعة السورية الخاصة' : 'Alumni Directory | Syrian Private University',
            seoDescription: $locale === 'ar'
                ? 'استكشف دليل خريجي الجامعة السورية الخاصة حسب الكلية والقسم وسنة التخرج.'
                : 'Explore the Syrian Private University alumni directory by faculty, department, and graduation year.',
            seoImage: '/images/uni-main-place.JPG',
        );
    }

    public function isAvailable(): bool
    {
        return Alumni::query()
            ->enabled()
            ->whereHas('faculty', fn (Builder $query): Builder => $query->enabled())
            ->whereHas('translations', fn (Builder $query): Builder => $query
                ->whereNotNull('full_name')
                ->where('full_name', '<>', ''))
            ->exists();
    }

    /** @return array<int, array<string, mixed>> */
    private function records(string $locale): array
    {
        $alumni = Alumni::query()
            ->enabled()
            ->whereHas('faculty', fn (Builder $query): Builder => $query->enabled())
            ->whereHas('translations', fn (Builder $query): Builder => $query
                ->whereNotNull('full_name')
                ->where('full_name', '<>', ''))
            ->select([
                'id',
                'faculty_id',
                'department_id',
                'degree',
                'graduation_year',
                'photo_media_id',
                'legacy_photo_path',
            ])
            ->with([
                'translations:id,alumni_id,locale,full_name',
                'faculty:id,slug,public_slug,is_enabled',
                'faculty.translations:id,faculty_id,locale,name,catalog_title',
                'department:id,slug,is_enabled',
                'department.translations:id,department_id,locale,name',
                'photoMedia:id,disk,path,webp_path',
            ])
            ->orderByDesc('graduation_year')
            ->orderByDesc('id')
            ->get();
        $metadataByTargetId = $this->migrationMetadataByTargetId($alumni->pluck('id')->all());

        return $alumni
            ->map(function (Alumni $alumni) use ($locale, $metadataByTargetId): ?array {
                $translation = $this->alumniTranslation($alumni, $locale);
                $faculty = $alumni->faculty;

                if (! $translation instanceof AlumniTranslation || ! $faculty instanceof Faculty) {
                    return null;
                }

                $facultyTranslation = $this->facultyTranslation($faculty, $locale);
                $facultyName = $facultyTranslation?->name;
                $facultySlug = (string) ($faculty->public_slug ?: $faculty->slug);

                if ($facultyName === null || trim((string) $facultyName) === '' || $facultySlug === '') {
                    return null;
                }

                $department = $alumni->department;
                $departmentTranslation = $department instanceof Department
                    ? $this->departmentTranslation($department, $locale)
                    : null;
                $departmentSlug = $department instanceof Department && (bool) $department->is_enabled
                    ? (string) $department->slug
                    : '';
                $departmentName = $departmentSlug !== '' && is_string($departmentTranslation?->name) && trim($departmentTranslation->name) !== ''
                    ? trim($departmentTranslation->name)
                    : null;
                $legacyPhoto = $alumni->legacy_photo_path
                    ?? ($metadataByTargetId[(int) $alumni->getKey()]['legacy_photo'] ?? null);
                $title = trim((string) $translation->full_name);

                if ($title === '') {
                    return null;
                }

                return [
                    'title' => $title,
                    'graduationYear' => $alumni->graduation_year,
                    'department' => $departmentName !== null && trim((string) $departmentName) !== '' ? (string) $departmentName : null,
                    'faculty' => (string) $facultyName,
                    'degree' => $alumni->degree,
                    'image' => $alumni->photoMedia instanceof MediaAsset
                        ? MediaUrlResolver::resolveImage($alumni->photoMedia->webp_path, $alumni->photoMedia->path, $alumni->photoMedia->disk)
                        : MediaUrlResolver::resolveLegacy($legacyPhoto),
                    '_facultySlug' => $facultySlug,
                    '_departmentSlug' => $departmentSlug,
                    '_searchText' => implode(' ', array_filter([
                        $title,
                        $alumni->graduation_year,
                        $departmentName,
                        $facultyName,
                        $alumni->degree,
                    ], static fn (mixed $value): bool => $value !== null && $value !== '')),
                ];
            })
            ->filter()
            ->values()
            ->all();
    }

    /** @param array<int, array<string, mixed>> $records @return array<string, mixed> */
    private function filterOptions(array $records): array
    {
        return [
            'years' => collect($records)
                ->pluck('graduationYear')
                ->filter(fn (mixed $year): bool => $year !== null && $year !== '')
                ->map(fn (mixed $year): string => (string) $year)
                ->unique()
                ->sortDesc()
                ->values()
                ->all(),
            'faculties' => collect($records)
                ->map(fn (array $record): array => ['value' => $record['_facultySlug'], 'label' => $record['faculty']])
                ->unique('value')
                ->sortBy('label')
                ->values()
                ->all(),
            'departments' => collect($records)
                ->filter(fn (array $record): bool => $record['_departmentSlug'] !== '' && ($record['department'] ?? '') !== '')
                ->map(fn (array $record): array => ['value' => $record['_departmentSlug'], 'label' => $record['department']])
                ->unique('value')
                ->sortBy('label')
                ->values()
                ->all(),
        ];
    }

    /** @param array<string, mixed> $filters @param array<string, mixed> $options @return array{q: string, year: string, faculty: string, department: string, page: int} */
    private function normalizeFilters(array $filters, array $options): array
    {
        $q = is_scalar($filters['q'] ?? null) ? trim((string) $filters['q']) : '';
        $year = is_scalar($filters['year'] ?? null) ? trim((string) $filters['year']) : '';
        $faculty = is_scalar($filters['faculty'] ?? null) ? trim((string) $filters['faculty']) : '';
        $department = is_scalar($filters['department'] ?? null) ? trim((string) $filters['department']) : '';
        $facultyValues = collect($options['faculties'] ?? [])->pluck('value')->map('strval')->all();
        $departmentValues = collect($options['departments'] ?? [])->pluck('value')->map('strval')->all();
        $years = array_map('strval', is_array($options['years'] ?? null) ? $options['years'] : []);

        return [
            'q' => mb_substr($q, 0, 120),
            'year' => in_array($year, $years, true) ? $year : '',
            'faculty' => in_array($faculty, $facultyValues, true) ? $faculty : '',
            'department' => in_array($department, $departmentValues, true) ? $department : '',
            'page' => max(1, min(500, (int) ($filters['page'] ?? 1))),
        ];
    }

    /** @param array<string, mixed> $record @return array<string, mixed> */
    private function publicItem(array $record): array
    {
        return [
            'title' => $record['title'],
            'graduationYear' => $record['graduationYear'],
            'department' => $record['department'],
            'faculty' => $record['faculty'],
            'degree' => $record['degree'],
            'image' => $record['image'],
        ];
    }

    /** @param array<int, mixed> $targetIds @return array<int, array<string, mixed>> */
    private function migrationMetadataByTargetId(array $targetIds): array
    {
        $targetIds = array_values(array_filter(array_map('intval', $targetIds)));

        if ($targetIds === []) {
            return [];
        }

        return MigrationLog::query()
            ->where('target_table', 'alumni')
            ->where('status', 'success')
            ->whereIn('target_id', $targetIds)
            ->get(['target_id', 'metadata'])
            ->mapWithKeys(fn (MigrationLog $log): array => [
                (int) $log->target_id => is_array($log->metadata) ? $log->metadata : [],
            ])
            ->all();
    }

    private function alumniTranslation(Alumni $alumni, string $locale): ?AlumniTranslation
    {
        return $alumni->translations->firstWhere('locale', $locale)
            ?? $alumni->translations->firstWhere('locale', 'ar')
            ?? $alumni->translations->firstWhere('locale', 'en');
    }

    private function facultyTranslation(Faculty $faculty, string $locale): ?FacultyTranslation
    {
        return $faculty->translations->firstWhere('locale', $locale)
            ?? $faculty->translations->firstWhere('locale', 'ar')
            ?? $faculty->translations->firstWhere('locale', 'en');
    }

    private function departmentTranslation(Department $department, string $locale): ?DepartmentTranslation
    {
        return $department->translations->firstWhere('locale', $locale)
            ?? $department->translations->firstWhere('locale', 'ar')
            ?? $department->translations->firstWhere('locale', 'en');
    }
}
