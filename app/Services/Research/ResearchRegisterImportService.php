<?php

declare(strict_types=1);

namespace App\Services\Research;

use App\Contracts\Research\ResearchRegisterImportServiceInterface;
use App\Contracts\Shared\AuditServiceInterface;
use App\Contracts\Shared\CacheServiceInterface;
use App\DTOs\Research\ResearchRegisterImportResultDTO;
use App\Models\Faculty\Faculty;
use App\Models\Person\FacultyMember;
use App\Models\Research\Researcher;
use App\Models\Research\ResearchPublication;
use App\Models\Research\ResearchPublicationTranslation;
use App\Models\Shared\MigrationLog;
use App\Support\PersonName;
use App\Support\ResearchPublicationSlug;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use RuntimeException;

/**
 * Brings the research office's newer papers onto the website. Papers the site
 * already has (same title) and papers imported before (same research number)
 * are left alone, so the import can run again safely.
 */
final class ResearchRegisterImportService implements ResearchRegisterImportServiceInterface
{
    public const APPROVAL_TOKEN = 'research-register-20261019';

    private const LEGACY_SOURCE = 'spu_sci';

    public function __construct(
        private readonly AuditServiceInterface $auditService,
        private readonly CacheServiceInterface $cacheService,
    ) {}

    public function import(string $path, bool $write, ?string $approval): ResearchRegisterImportResultDTO
    {
        if ($write && $approval !== self::APPROVAL_TOKEN) {
            throw new InvalidArgumentException('Writing requires --approve='.self::APPROVAL_TOKEN.'.');
        }

        $data = $this->read($path);
        $staff = $this->staffDirectory();
        $staffByResearcher = [];

        foreach ($data['researchers'] as $researcher) {
            $staffByResearcher[$researcher['source_id']] = $this->matchStaff($researcher['full_name'], $staff);
        }

        $existingTitles = ResearchPublicationTranslation::query()->pluck('title')
            ->map(fn (mixed $title): string => $this->titleKey((string) $title))
            ->flip();
        $existingNumbers = ResearchPublication::query()->withTrashed()->whereNotNull('research_number')->pluck('research_number')->flip();
        $facultySlugs = Faculty::query()->pluck('public_slug', 'id');

        $alreadyOnSite = 0;
        $alreadyImported = 0;
        $toCreate = [];
        $byFaculty = [];

        foreach ($data['papers'] as $paper) {
            if ($existingNumbers->has($paper['research_number'])) {
                $alreadyImported++;

                continue;
            }

            if ($existingTitles->has($this->titleKey($paper['title']))) {
                $alreadyOnSite++;

                continue;
            }

            $paper['faculty_id'] = $this->paperFaculty($paper['researchers'], $staffByResearcher);
            $slug = $paper['faculty_id'] !== null ? (string) ($facultySlugs[$paper['faculty_id']] ?? '') : '';
            $byFaculty[$slug] = ($byFaculty[$slug] ?? 0) + 1;
            $toCreate[] = $paper;
        }

        $existingResearchers = Researcher::query()->withTrashed()->where('legacy_source', self::LEGACY_SOURCE)->pluck('id', 'legacy_source_id');
        $researchersCreated = count(array_filter($data['researchers'], fn (array $researcher): bool => ! $existingResearchers->has($researcher['source_id'])));
        $linkedToStaff = count(array_filter($staffByResearcher));
        ksort($byFaculty);

        if ($write) {
            $this->write($data['researchers'], $toCreate, $staffByResearcher);
        }

        return new ResearchRegisterImportResultDTO(
            written: $write,
            papersInFile: count($data['papers']),
            alreadyOnSite: $alreadyOnSite,
            alreadyImported: $alreadyImported,
            created: count($toCreate),
            hiddenWithoutDate: count(array_filter($toCreate, fn (array $paper): bool => $paper['published_at'] === null)),
            researchersInFile: count($data['researchers']),
            researchersCreated: $researchersCreated,
            researchersLinkedToStaff: $linkedToStaff,
            papersByFaculty: $byFaculty,
        );
    }

    /**
     * @param  list<array<string, mixed>>  $researchers
     * @param  list<array<string, mixed>>  $papers
     * @param  array<int, ?array{member_id: int, faculty_id: ?int}>  $staffByResearcher
     */
    private function write(array $researchers, array $papers, array $staffByResearcher): void
    {
        DB::transaction(function () use ($researchers, $papers, $staffByResearcher): void {
            $researcherIds = [];

            foreach ($researchers as $researcher) {
                $staff = $staffByResearcher[$researcher['source_id']] ?? null;
                $model = Researcher::query()->withTrashed()->firstOrNew([
                    'legacy_source' => self::LEGACY_SOURCE,
                    'legacy_source_id' => $researcher['source_id'],
                ]);

                // A re-run fills gaps only, so an editor's later changes stay.
                if (! $model->exists) {
                    $model->fill([
                        'full_name' => $researcher['full_name'],
                        'affiliation' => $researcher['affiliation'],
                        'registration_status' => $researcher['registration_status'],
                        'faculty_member_id' => $staff['member_id'] ?? null,
                        'faculty_id' => $staff['faculty_id'] ?? null,
                    ])->save();
                }

                $researcherIds[$researcher['source_id']] = (int) $model->getKey();
            }

            foreach ($papers as $paper) {
                $this->createPaper($paper, $researcherIds);
            }
        });

        $this->auditService->log('research.register_imported', null, ResearchPublication::class, null, [
            'batch' => self::APPROVAL_TOKEN,
            'papers' => count($papers),
            'researchers' => count($researchers),
        ]);
        $this->cacheService->flushTags(['research', 'facilities', 'public-pages', 'seo', 'sitemap']);
    }

    /**
     * @param  array<string, mixed>  $paper
     * @param  array<int, int>  $researcherIds
     */
    private function createPaper(array $paper, array $researcherIds): void
    {
        $publishedAt = $paper['published_at'];
        $publication = ResearchPublication::query()->create([
            'research_number' => $paper['research_number'],
            'faculty_id' => $paper['faculty_id'],
            'publication_type' => $paper['publication_type'],
            'study_type' => $paper['study_type'],
            'issn' => $paper['issn'],
            'journal_rank' => $paper['journal_rank'],
            'published_at' => $publishedAt,
            'publication_year' => $publishedAt !== null ? (int) substr($publishedAt, 0, 4) : null,
            'notes' => $this->notes($paper),
            'sort_order' => 0,
            // A paper without a publication date waits, hidden, for an editor to add one.
            'is_enabled' => $publishedAt !== null,
        ]);

        $slug = ResearchPublicationSlug::storable(ResearchPublicationSlug::derive($paper['title'], null, (int) $publication->getKey()));
        $candidate = $slug;
        $attempt = 2;

        while (ResearchPublication::query()->withTrashed()->where('slug', $candidate)->whereKeyNot($publication->getKey())->exists()) {
            $candidate = $slug.'-'.$attempt++;
        }

        $publication->forceFill(['slug' => $candidate])->save();

        // The register has English titles only; both languages show the
        // original title, as journals publish it.
        foreach (['ar', 'en'] as $locale) {
            $publication->translations()->create([
                'locale' => $locale,
                'title' => $paper['title'],
                'publisher' => $paper['venue'],
                'citation' => $paper['publication_info'],
                'keywords' => [],
            ]);
        }

        $order = 1;
        $pivot = [];

        foreach ($paper['researchers'] as $sourceId) {
            if (isset($researcherIds[$sourceId])) {
                $pivot[$researcherIds[$sourceId]] = ['role' => 'co_author', 'sort_order' => $order++];
            }
        }

        $publication->researchers()->sync($pivot);

        MigrationLog::query()->updateOrCreate(
            ['module' => 'research', 'source_table' => self::LEGACY_SOURCE.'.researches', 'source_id' => $paper['source_id'], 'target_table' => 'research_publications'],
            [
                'batch_name' => self::APPROVAL_TOKEN,
                'target_id' => (int) $publication->getKey(),
                'status' => 'success',
                'message' => 'Imported a paper from the research office register.',
                'metadata' => ['research_number' => $paper['research_number']],
            ],
        );
    }

    /** @param array<string, mixed> $paper */
    private function notes(array $paper): string
    {
        return implode("\n", array_filter([
            'Imported from the research office register ('.$paper['research_number'].').',
            $paper['original_study_type'] !== null ? 'Study type as registered: '.$paper['original_study_type'] : null,
            $paper['original_venue'] !== null ? 'Venue as registered: '.$paper['original_venue'] : null,
        ]));
    }

    /**
     * @param  list<int>  $researchers
     * @param  array<int, ?array{member_id: int, faculty_id: ?int}>  $staffByResearcher
     */
    private function paperFaculty(array $researchers, array $staffByResearcher): ?int
    {
        $faculties = collect($researchers)
            ->map(fn (int $sourceId): ?int => $staffByResearcher[$sourceId]['faculty_id'] ?? null)
            ->filter()
            ->countBy()
            ->sortDesc();

        return $faculties->isEmpty() ? null : (int) $faculties->keys()->first();
    }

    /**
     * Staff names in both languages, to link researchers to their profiles.
     *
     * @return Collection<int, array{member_id: int, faculty_id: ?int, names: list<string>}>
     */
    private function staffDirectory(): Collection
    {
        return FacultyMember::query()
            ->with('translations')
            ->get()
            ->map(fn (FacultyMember $member): array => [
                'member_id' => (int) $member->getKey(),
                'faculty_id' => $member->faculty_id !== null ? (int) $member->faculty_id : null,
                'names' => $member->translations->pluck('full_name')->filter()->map(fn (mixed $name): string => (string) $name)->values()->all(),
            ]);
    }

    /**
     * The one staff member with this name; none when no one or several match.
     *
     * @param  Collection<int, array{member_id: int, faculty_id: ?int, names: list<string>}>  $staff
     * @return ?array{member_id: int, faculty_id: ?int}
     */
    private function matchStaff(string $name, Collection $staff): ?array
    {
        $matches = $staff->filter(fn (array $member): bool => collect($member['names'])->contains(fn (string $staffName): bool => PersonName::same($name, $staffName)));

        if ($matches->count() !== 1) {
            return null;
        }

        $member = $matches->first();

        return ['member_id' => $member['member_id'], 'faculty_id' => $member['faculty_id']];
    }

    /** @return array{researchers: list<array<string, mixed>>, papers: list<array<string, mixed>>} */
    private function read(string $path): array
    {
        if (! is_file($path)) {
            throw new RuntimeException('The research register file was not found: '.$path);
        }

        $data = json_decode((string) file_get_contents($path), true);

        if (! is_array($data) || ! is_array($data['researchers'] ?? null) || ! is_array($data['papers'] ?? null)) {
            throw new InvalidArgumentException('The research register file is not valid.');
        }

        $researchers = [];
        foreach ($data['researchers'] as $index => $researcher) {
            if (! is_array($researcher) || ! is_int($researcher['source_id'] ?? null) || trim((string) ($researcher['full_name'] ?? '')) === '') {
                throw new InvalidArgumentException('Researcher '.$index.' has no id or name.');
            }

            $researchers[] = [
                'source_id' => $researcher['source_id'],
                'full_name' => trim((string) $researcher['full_name']),
                'affiliation' => $this->nullableString($researcher['affiliation'] ?? null),
                'registration_status' => in_array($researcher['registration_status'] ?? null, Researcher::REGISTRATION_STATUSES, true) ? $researcher['registration_status'] : 'pending',
            ];
        }

        $papers = [];
        foreach ($data['papers'] as $index => $paper) {
            $number = is_array($paper) ? trim((string) ($paper['research_number'] ?? '')) : '';
            $title = is_array($paper) ? trim((string) ($paper['title'] ?? '')) : '';

            if ($number === '' || $title === '' || mb_strlen($title) > 500 || ! is_int($paper['source_id'] ?? null)) {
                throw new InvalidArgumentException('Paper '.$index.' has no research number, id or title.');
            }

            $publishedAt = $this->nullableString($paper['published_at'] ?? null);
            if ($publishedAt !== null && preg_match('/^\d{4}-\d{2}-\d{2}$/', $publishedAt) !== 1) {
                throw new InvalidArgumentException('Paper '.$number.' has an invalid date.');
            }

            $papers[] = [
                'source_id' => $paper['source_id'],
                'research_number' => $number,
                'title' => $title,
                'publication_type' => in_array($paper['publication_type'] ?? null, ResearchPublication::PUBLICATION_TYPES, true) ? $paper['publication_type'] : null,
                'study_type' => in_array($paper['study_type'] ?? null, ResearchPublication::STUDY_TYPES, true) ? $paper['study_type'] : null,
                'venue' => $this->nullableString($paper['venue'] ?? null),
                'issn' => $this->nullableString($paper['issn'] ?? null),
                'publication_info' => $this->nullableString($paper['publication_info'] ?? null),
                'published_at' => $publishedAt,
                'journal_rank' => $this->nullableString($paper['journal_rank'] ?? null),
                'original_study_type' => $this->nullableString($paper['original_study_type'] ?? null),
                'original_venue' => $this->nullableString($paper['original_venue'] ?? null),
                'researchers' => array_values(array_filter(is_array($paper['researchers'] ?? null) ? $paper['researchers'] : [], 'is_int')),
            ];
        }

        return ['researchers' => $researchers, 'papers' => $papers];
    }

    private function titleKey(string $title): string
    {
        return (string) preg_replace('/[^a-z0-9]+/', '', mb_strtolower(html_entity_decode(strip_tags($title), ENT_QUOTES | ENT_HTML5, 'UTF-8')));
    }

    private function nullableString(mixed $value): ?string
    {
        $value = is_string($value) ? trim($value) : '';

        return $value !== '' ? $value : null;
    }
}
