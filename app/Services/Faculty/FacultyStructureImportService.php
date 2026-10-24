<?php

declare(strict_types=1);

namespace App\Services\Faculty;

use App\Contracts\Faculty\FacultyStructureImportServiceInterface;
use App\Contracts\Shared\CacheServiceInterface;
use App\DTOs\Faculty\FacultyStructureImportResultDTO;
use App\Models\Faculty\Department;
use App\Models\Faculty\Faculty;
use App\Models\Faculty\FacultyLab;
use App\Models\Faculty\FacultyPage;
use App\Models\Faculty\FacultySubpageCard;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Throwable;

/**
 * Loads the old site's real departments and labs per faculty (replacing the
 * redesign's invented ones) and the subpages the old site had that the
 * redesign lacked. Invented records are switched off, never deleted, so an
 * editor can bring one back from the admin.
 */
final class FacultyStructureImportService implements FacultyStructureImportServiceInterface
{
    private const LOCALES = ['ar', 'en'];

    private const DEPARTMENT_KINDS = ['basic_sciences', 'academic', 'clinical'];

    public function __construct(
        private readonly CacheServiceInterface $cacheService,
    ) {}

    public function import(array $source, bool $write): FacultyStructureImportResultDTO
    {
        $faculties = is_array($source['faculties'] ?? null) ? $source['faculties'] : [];

        if ($faculties === []) {
            throw new InvalidArgumentException('The data file has no "faculties".');
        }

        $report = [];
        $warnings = [];

        DB::beginTransaction();

        try {
            foreach ($faculties as $facultySlug => $data) {
                $faculty = Faculty::query()->where('public_slug', (string) $facultySlug)->first();

                if (! $faculty instanceof Faculty || ! is_array($data)) {
                    $warnings[] = 'Unknown faculty '.$facultySlug.': skipped.';

                    continue;
                }

                $report[(string) $facultySlug] = $this->importFaculty($faculty, $data);
            }

            $write ? DB::commit() : DB::rollBack();
        } catch (Throwable $exception) {
            DB::rollBack();

            throw $exception;
        }

        if ($write && ! $this->cacheService->flushTags(['facilities', 'public-pages', 'seo', 'sitemap'])) {
            $this->cacheService->flushAll();
        }

        return new FacultyStructureImportResultDTO($write, $report, $warnings);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{departments: int, departmentsCreated: int, departmentsDisabled: int, labs: int|null, labsCreated: int, labsDisabled: int, labsCardHidden: bool, pages: list<string>}
     */
    private function importFaculty(Faculty $faculty, array $data): array
    {
        $publicSlug = (string) $faculty->public_slug;
        $departments = $this->listOf($data['departments'] ?? []);
        [$departmentsCreated, $departmentsDisabled] = $this->syncDepartments($faculty, $departments);
        $this->replaceDepartmentsSummary($faculty, is_array($data['departmentsSummary'] ?? null) ? $data['departmentsSummary'] : []);

        $labs = array_key_exists('labs', $data) ? $this->listOf($data['labs']) : null;
        [$labsCreated, $labsDisabled] = $labs === null ? [0, 0] : $this->syncLabs($faculty, $labs);
        $labsCardHidden = $labs === [] && $this->hideSubpage($faculty, 'labs');

        $pages = [];
        foreach ($this->listOf($data['pages'] ?? []) as $page) {
            $slug = (string) ($page['slug'] ?? '');
            $allowed = config('faculty_subpages.restricted.'.$slug);

            if ($slug === '' || (is_array($allowed) && ! in_array($publicSlug, $allowed, true))) {
                throw new InvalidArgumentException('Subpage "'.$slug.'" is not available for '.$publicSlug.'.');
            }

            $this->ensureSubpage($faculty, $slug, ['ar' => (string) ($page['titleAr'] ?? ''), 'en' => (string) ($page['titleEn'] ?? '')]);
            $pages[] = $slug;
        }

        return [
            'departments' => count($departments),
            'departmentsCreated' => $departmentsCreated,
            'departmentsDisabled' => $departmentsDisabled,
            'labs' => $labs === null ? null : count($labs),
            'labsCreated' => $labsCreated,
            'labsDisabled' => $labsDisabled,
            'labsCardHidden' => $labsCardHidden,
            'pages' => $pages,
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $departments
     * @return array{int, int} created, disabled
     */
    private function syncDepartments(Faculty $faculty, array $departments): array
    {
        $created = 0;
        $slugs = [];

        foreach ($departments as $index => $item) {
            $slug = $this->slug($item);
            $slugs[] = $slug;
            $department = Department::withTrashed()->firstOrNew(['faculty_id' => $faculty->getKey(), 'slug' => $slug]);
            $created += $department->exists ? 0 : 1;
            $kind = (string) ($item['kind'] ?? 'academic');

            if (! in_array($kind, self::DEPARTMENT_KINDS, true)) {
                throw new InvalidArgumentException('Department "'.$slug.'" has an unknown kind "'.$kind.'".');
            }

            $department->fill(['kind' => $kind, 'sort_order' => $index + 1, 'is_enabled' => true])->save();

            if ($department->trashed()) {
                $department->restore();
            }

            foreach (self::LOCALES as $locale) {
                $suffix = ucfirst($locale);
                $department->translations()->updateOrCreate(['locale' => $locale], [
                    'name' => $this->required($item, 'name'.$suffix),
                    'description' => trim((string) ($item['description'.$suffix] ?? '')) ?: null,
                ]);
            }
        }

        $disabled = Department::query()
            ->where('faculty_id', $faculty->getKey())
            ->where('is_enabled', true)
            ->whereNotIn('slug', $slugs)
            ->update(['is_enabled' => false]);

        return [$created, $disabled];
    }

    /**
     * @param  list<array<string, mixed>>  $labs
     * @return array{int, int} created, disabled
     */
    private function syncLabs(Faculty $faculty, array $labs): array
    {
        $created = 0;
        $slugs = [];

        foreach ($labs as $index => $item) {
            $slug = $this->slug($item);
            $slugs[] = $slug;
            $lab = FacultyLab::query()->firstOrNew(['faculty_id' => $faculty->getKey(), 'slug' => $slug]);
            $created += $lab->exists ? 0 : 1;
            $lab->fill(['sort_order' => $index + 1, 'is_enabled' => true])->save();

            foreach (self::LOCALES as $locale) {
                $suffix = ucfirst($locale);
                $lab->translations()->updateOrCreate(['locale' => $locale], [
                    'title' => $this->required($item, 'title'.$suffix),
                    'department' => trim((string) ($item['department'.$suffix] ?? '')) ?: null,
                    'instructor' => trim((string) ($item['instructor'.$suffix] ?? '')) ?: null,
                    'description' => trim((string) ($item['description'.$suffix] ?? '')) ?: null,
                ]);
            }
        }

        $disabled = FacultyLab::query()
            ->where('faculty_id', $faculty->getKey())
            ->where('is_enabled', true)
            ->whereNotIn('slug', $slugs)
            ->update(['is_enabled' => false]);

        return [$created, $disabled];
    }

    /**
     * The departments page's summary and payload came from the redesign's seed:
     * "sample data" wording, invented statistics and per-department extras keyed
     * by the invented slugs. They go; the real summary replaces them.
     *
     * @param  array<string, mixed>  $summary
     */
    private function replaceDepartmentsSummary(Faculty $faculty, array $summary): void
    {
        $page = FacultyPage::query()->where('faculty_id', $faculty->getKey())->where('slug', 'departments')->first();

        if (! $page instanceof FacultyPage) {
            return;
        }

        $payload = collect(is_array($page->payload_json) ? $page->payload_json : [])->except(['stats', 'departments'])->all();

        foreach (self::LOCALES as $locale) {
            $text = trim((string) ($summary[$locale] ?? ''));

            if ($text === '') {
                continue;
            }

            if (is_array($payload[$locale] ?? null)) {
                $payload[$locale]['summary'] = $text;
            }

            $page->translations()->where('locale', $locale)->update(['summary' => $text]);
        }

        $page->forceFill(['payload_json' => $payload])->save();
    }

    /** @param array<string, string> $titles */
    private function ensureSubpage(Faculty $faculty, string $slug, array $titles): void
    {
        $page = FacultyPage::query()->firstOrNew(['faculty_id' => $faculty->getKey(), 'slug' => $slug]);

        if (! $page->exists) {
            $page->fill([
                'kind' => $slug,
                'payload_json' => [],
                'sort_order' => (int) FacultyPage::query()->where('faculty_id', $faculty->getKey())->max('sort_order') + 1,
            ]);
        }

        $page->fill(['is_enabled' => true])->save();

        foreach (self::LOCALES as $locale) {
            if (trim($titles[$locale] ?? '') !== '') {
                $page->translations()->updateOrCreate(['locale' => $locale], ['title' => trim($titles[$locale])]);
            }
        }

        $facultySlug = (string) $faculty->public_slug;

        // A faculty without cards shows its enabled pages; one card would hide all the others.
        if (! FacultySubpageCard::query()->where('faculty_slug', $facultySlug)->exists()) {
            return;
        }

        $card = FacultySubpageCard::query()->firstOrNew(['faculty_slug' => $facultySlug, 'subpage_slug' => $slug]);

        if (! $card->exists) {
            $card->fill(['sort_order' => (int) FacultySubpageCard::query()->where('faculty_slug', $facultySlug)->max('sort_order') + 1]);
        }

        $card->fill([
            'title_override_ar' => $card->title_override_ar ?: (trim($titles['ar'] ?? '') ?: null),
            'title_override_en' => $card->title_override_en ?: (trim($titles['en'] ?? '') ?: null),
            'is_visible' => true,
            'status' => 'published',
            'publish_at' => null,
            'published_at' => $card->published_at ?? now(),
        ])->save();
    }

    private function hideSubpage(Faculty $faculty, string $slug): bool
    {
        $pages = FacultyPage::query()->where('faculty_id', $faculty->getKey())->where('slug', $slug)->where('is_enabled', true)->update(['is_enabled' => false]);
        $cards = FacultySubpageCard::query()->where('faculty_slug', (string) $faculty->public_slug)->where('subpage_slug', $slug)->where('is_visible', true)->update(['is_visible' => false]);

        return $pages + $cards > 0;
    }

    /** @param array<string, mixed> $item */
    private function slug(array $item): string
    {
        $slug = Str::slug((string) ($item['slug'] ?? ''));

        if ($slug === '') {
            throw new InvalidArgumentException('An entry has no slug.');
        }

        return $slug;
    }

    /** @param array<string, mixed> $item */
    private function required(array $item, string $field): string
    {
        $value = trim((string) ($item[$field] ?? ''));

        if ($value === '') {
            throw new InvalidArgumentException('Entry "'.($item['slug'] ?? '?').'" has no '.$field.'.');
        }

        return $value;
    }

    /** @return list<array<string, mixed>> */
    private function listOf(mixed $items): array
    {
        return array_values(array_filter(is_array($items) ? $items : [], static fn (mixed $item): bool => is_array($item)));
    }
}
