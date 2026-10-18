<?php

declare(strict_types=1);

namespace App\Services\Legacy;

use App\Contracts\Legacy\LegacyFacultyResearchImportServiceInterface;
use App\Contracts\Legacy\LegacyResearchMetadataExtractorInterface;
use App\Contracts\Shared\CacheServiceInterface;
use App\DTOs\Legacy\LegacyFacultyResearchImportResultDTO;
use App\DTOs\Legacy\LegacyResearchMetadataDTO;
use App\Models\Faculty\Faculty;
use App\Models\Research\LegacyResearchFileReference;
use App\Models\Research\ResearchPublication;
use App\Models\Research\ResearchPublicationTranslation;
use App\Models\Shared\MigrationLog;
use App\Support\LegacyImport\HtmlSanitizer;
use App\Support\LegacyImport\SqlDumpReader;
use App\Support\LegacyImport\TextCleaner;
use App\Support\ResearchPublicationSlug;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;
use JsonException;
use RuntimeException;

final class LegacyFacultyResearchImportService implements LegacyFacultyResearchImportServiceInterface
{
    public const APPROVAL_TOKEN = 'faculty-research-20261018';

    private const SOURCE_TABLE = 'jx_categories';

    private const ITEM_TABLE = 'jx_items';

    private const MEMBER_TABLE = 'jx_member_categories';

    /** Research list service of each faculty site, as linked from the portal's "by faculty" page. */
    private const FACULTY_SERVICES = [
        27 => 'medicine',
        37 => 'dentistry',
        47 => 'pharmacy',
        57 => 'artificial-intelligence',
        67 => 'petroleum',
        77 => 'business-administration',
        89 => null, // "SPU students publications": no faculty in the source.
    ];

    /** Staff list service of each faculty site (jx_councils / jx_councils1.service_type). */
    private const OWNER_SERVICES = [
        4 => 'medicine',
        6 => 'dentistry',
        8 => 'pharmacy',
        10 => 'artificial-intelligence',
        12 => 'petroleum',
        14 => 'business-administration',
    ];

    private const IMAGE_EXTENSIONS = ['jpg', 'jpeg', 'png', 'gif', 'webp'];

    public function __construct(
        private readonly LegacyResearchMetadataExtractorInterface $metadataExtractor,
        private readonly HtmlSanitizer $htmlSanitizer,
        private readonly TextCleaner $textCleaner,
        private readonly CacheServiceInterface $cacheService,
        private readonly SqlDumpReader $dumpReader,
    ) {}

    public function export(string $dumpPath, string $jsonPath): int
    {
        if (! is_file($dumpPath) || ! is_readable($dumpPath)) {
            throw new InvalidArgumentException('The SQL dump could not be read: '.$dumpPath);
        }

        $services = array_keys(self::FACULTY_SERVICES);
        $entries = [];
        $items = [];
        $owners = ['jx_councils' => [], 'jx_councils1' => []];

        foreach ($this->dumpReader->rows($dumpPath, ['jx_categories', 'jx_items', 'jx_councils', 'jx_councils1']) as [$table, $row]) {
            $service = (int) ($row['service_type'] ?? 0);

            if ($table === 'jx_categories' && in_array($service, $services, true)) {
                $entries[(int) $row['id']] = [
                    'id' => (int) $row['id'],
                    'service_type' => $service,
                    'visible' => (int) $row['is_visible'] === 1,
                    'order' => (int) ($row['category_order'] ?? 0),
                    ...$this->only($row, ['ar_name', 'en_name', 'ar_data', 'en_data', 'ar_brief', 'en_brief', 'ar_keywords', 'en_keywords', 'photo']),
                    'items' => [],
                ];
            } elseif ($table === 'jx_items' && in_array($service, $services, true)) {
                $items[] = [
                    'category_id' => (int) $row['category_id'],
                    'id' => (int) $row['id'],
                    'visible' => (int) $row['is_visible'] === 1 && (int) ($row['is_accepted'] ?? 1) === 1,
                    ...$this->only($row, ['ar_name', 'en_name', 'en_file', 'ar_file', 'photo']),
                ];
            } elseif ($table === 'jx_councils' || $table === 'jx_councils1') {
                $owners[$table][(int) $row['id']] = $service;
            }
        }

        foreach ($items as $item) {
            if (isset($entries[$item['category_id']])) {
                $categoryId = $item['category_id'];
                unset($item['category_id']);
                $entries[$categoryId]['items'][] = $item;
            }
        }

        ksort($entries);
        $json = json_encode([
            'source' => basename($dumpPath).': jx_categories services '.implode(', ', $services).' with their jx_items, and jx_councils / jx_councils1 service types.',
            'entries' => array_values($entries),
            'owners' => $owners,
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);

        if (file_put_contents($jsonPath, $json."\n") === false) {
            throw new RuntimeException('Could not write '.$jsonPath);
        }

        return count($entries);
    }

    public function import(string $jsonPath, bool $write = false, ?string $approval = null): LegacyFacultyResearchImportResultDTO
    {
        if ($write && $approval !== self::APPROVAL_TOKEN) {
            throw new InvalidArgumentException('Writing requires --approve='.self::APPROVAL_TOKEN.'.');
        }

        $data = $this->readJson($jsonPath);
        $facultyIds = Faculty::query()
            ->whereIn('public_slug', array_filter(array_values(self::FACULTY_SERVICES)))
            ->pluck('id', 'public_slug')
            ->map(static fn (mixed $id): int => (int) $id)
            ->all();

        foreach (array_filter(array_values(self::FACULTY_SERVICES)) as $slug) {
            if (! isset($facultyIds[$slug])) {
                throw new RuntimeException('Target faculty is missing: '.$slug);
            }
        }

        // Every publication already here, by normalized title, so a paper listed on a staff page
        // and in a faculty list stays one publication.
        $byTitle = [];
        $bySource = [];
        // Staff-page copies first, and of those the public one (a duplicate title stays private).
        $rank = static fn (ResearchPublication $publication): int => ($publication->legacy_source_table === self::MEMBER_TABLE ? 2 : 0)
            + ($publication->is_enabled && $publication->extraction_status === 'published' ? 1 : 0);
        foreach (ResearchPublication::query()->with('translations')->get() as $publication) {
            if ($publication->legacy_source_table === self::SOURCE_TABLE) {
                $bySource[(int) $publication->legacy_source_id] = $publication;
            }
            foreach ($publication->translations as $translation) {
                $key = $this->titleKey($translation->title);
                if ($key !== '' && (! isset($byTitle[$key]) || $rank($publication) > $rank($byTitle[$key]))) {
                    $byTitle[$key] = $publication;
                }
            }
        }

        $counts = ['visible' => 0, 'linked' => 0, 'created' => 0, 'updated' => 0, 'duplicates' => 0, 'owner' => 0];
        $entriesByFaculty = [];
        $claimed = [];      // publication id => faculty slug given by a faculty list
        $seenTitles = [];   // title key => source id, within the lists

        $entries = $data['entries'];
        usort($entries, static fn (array $a, array $b): int => $a['id'] <=> $b['id']);

        foreach ($entries as $entry) {
            if (! ($entry['visible'] ?? false)) {
                continue;
            }

            $titles = $this->titles($entry);
            if ($titles === null) {
                continue;
            }

            $counts['visible']++;
            $facultySlug = self::FACULTY_SERVICES[(int) $entry['service_type']] ?? null;
            $entriesByFaculty[$facultySlug ?? ''] = ($entriesByFaculty[$facultySlug ?? ''] ?? 0) + 1;
            $keys = array_values(array_filter(array_unique([$this->titleKey($titles['ar']), $this->titleKey($titles['en'] ?? null)])));

            // The same paper listed under two faculties keeps its first listing.
            $firstListing = null;
            foreach ($keys as $key) {
                $firstListing ??= $seenTitles[$key] ?? null;
            }
            if ($firstListing !== null && $firstListing !== (int) $entry['id']) {
                $counts['duplicates']++;

                continue;
            }
            foreach ($keys as $key) {
                $seenTitles[$key] = (int) $entry['id'];
            }

            $existing = $bySource[(int) $entry['id']] ?? null;
            if ($existing === null) {
                foreach ($keys as $key) {
                    $candidate = $byTitle[$key] ?? null;
                    if ($candidate !== null && $candidate->legacy_source_table === self::MEMBER_TABLE) {
                        $existing = $candidate;
                        break;
                    }
                }
            }

            $facultyId = $facultySlug !== null ? $facultyIds[$facultySlug] : null;

            if ($existing !== null && $existing->legacy_source_table === self::MEMBER_TABLE) {
                $counts['linked']++;
                $claimed[(int) $existing->getKey()] = $facultySlug;
                if ($write) {
                    $this->linkExisting($existing, $entry, $facultyId);
                }

                continue;
            }

            $existing === null ? $counts['created']++ : $counts['updated']++;
            if ($write) {
                $publication = $this->writeEntry($entry, $titles, $facultyId, $existing);
                $claimed[(int) $publication->getKey()] = $facultySlug;
            }
        }

        // Staff-profile publications no faculty list claimed take their owner's faculty, when the
        // owner's staff lists agree on it (both tables were checked against the faculty lists:
        // 256 owners, no disagreement).
        $owners = $data['owners'];
        $withoutFaculty = 0;
        foreach (ResearchPublication::query()->where('legacy_source_table', self::MEMBER_TABLE)->get() as $publication) {
            if (array_key_exists((int) $publication->getKey(), $claimed)) {
                continue;
            }
            if ($publication->faculty_id !== null) {
                continue;
            }

            $slug = $this->ownerFaculty($publication->legacy_owner_id, $owners);
            if ($slug === null) {
                $withoutFaculty++;

                continue;
            }

            $counts['owner']++;
            if ($write) {
                $publication->forceFill(['faculty_id' => $facultyIds[$slug]])->save();
            }
        }

        // The first import stored card images as bare file names, which resolve to the site root and
        // 404; they live in the photo directory. Some "photos" are PDF or XPS files (missing on the
        // old site too), which are no card image: those cards use the default image.
        $imagesRepaired = 0;
        foreach (ResearchPublication::query()->whereNotNull('legacy_image_path')->get() as $publication) {
            $repaired = $this->imagePath($publication->legacy_image_path);
            if ($repaired === $publication->legacy_image_path) {
                continue;
            }
            $imagesRepaired++;
            if ($write) {
                $publication->forceFill(['legacy_image_path' => $repaired])->save();
            }
        }

        if ($write && ! $this->cacheService->flushTags(['research', 'facilities', 'public-pages', 'seo', 'sitemap'])) {
            $this->cacheService->flushAll();
        }

        $public = ResearchPublication::query()->public()->with(['faculty', 'facultyMember.faculty'])->get();
        $publicByFaculty = $public
            ->groupBy(fn (ResearchPublication $publication): string => (string) $publication->effectiveFaculty()?->public_slug)
            ->map(fn ($group): int => $group->count())
            ->sortKeys()
            ->all();
        ksort($entriesByFaculty);

        return new LegacyFacultyResearchImportResultDTO(
            written: $write,
            scannedEntries: count($entries),
            visibleEntries: $counts['visible'],
            linkedToExisting: $counts['linked'],
            created: $counts['created'],
            updated: $counts['updated'],
            duplicatesInLists: $counts['duplicates'],
            facultyFromOwner: $counts['owner'],
            withoutFaculty: $withoutFaculty + ($entriesByFaculty[''] ?? 0),
            imagesRepaired: $imagesRepaired,
            publicPublications: $public->count(),
            entriesByFaculty: $entriesByFaculty,
            publicPublicationsByFaculty: $publicByFaculty,
        );
    }

    /** @param array<string, mixed> $entry */
    private function linkExisting(ResearchPublication $publication, array $entry, ?int $facultyId): void
    {
        DB::transaction(function () use ($publication, $entry, $facultyId): void {
            // The faculty list published this paper, so the copy it matched is public even if it was
            // held back as a possible duplicate title (the match prefers an already public copy).
            $publication->forceFill([
                'faculty_id' => $facultyId ?? $publication->faculty_id,
                'is_enabled' => true,
                'extraction_status' => 'published',
                // The staff-page copy's own image, else the faculty list's.
                'legacy_image_path' => $this->legacyFilePath($publication->legacy_image_path) ?? $this->imagePath($entry['photo'] ?? null),
            ])->save();

            $known = $publication->legacyFileReferences()->pluck('legacy_path')->all();
            $next = count($known);
            foreach ($this->attachments($entry) as $attachment) {
                if (in_array($attachment['path'], $known, true)) {
                    continue;
                }
                LegacyResearchFileReference::query()->create([
                    'research_publication_id' => (int) $publication->getKey(),
                    'legacy_source_table' => self::ITEM_TABLE,
                    'legacy_source_id' => $attachment['source_id'],
                    'legacy_path' => $attachment['path'],
                    'label_ar' => $attachment['label_ar'],
                    'label_en' => $attachment['label_en'],
                    'sort_order' => $next++,
                    'status' => 'deferred',
                ]);
                $known[] = $attachment['path'];
            }
        });
    }

    /**
     * @param  array<string, mixed>  $entry
     * @param  array{ar: string, en: ?string}  $titles
     */
    private function writeEntry(array $entry, array $titles, ?int $facultyId, ?ResearchPublication $existing): ResearchPublication
    {
        $sourceId = (int) $entry['id'];
        $metadata = [];
        foreach (['ar', 'en'] as $locale) {
            if ($locale === 'en' && $titles['en'] === null) {
                continue;
            }
            $metadata[$locale] = $this->metadataExtractor->extract(
                $this->body($entry[$locale.'_data'] ?? null),
                $this->clean($entry[$locale.'_keywords'] ?? null),
                $titles[$locale],
            );
        }
        $first = static fn (string $field): mixed => ($metadata['en'] ?? null)?->{$field} ?? ($metadata['ar'] ?? null)?->{$field};

        $publication = DB::transaction(function () use ($entry, $titles, $facultyId, $existing, $sourceId, $metadata, $first): ResearchPublication {
            $publication = $existing ?? new ResearchPublication;
            $publication->forceFill([
                'slug' => $publication->slug ?: $this->uniqueSlug($this->slugFor($titles, $sourceId)),
                'faculty_id' => $facultyId,
                'publication_year' => $first('publicationYear'),
                'doi' => $first('doi'),
                'journal_rank' => $first('journalRank'),
                'legacy_source_table' => self::SOURCE_TABLE,
                'legacy_source_id' => $sourceId,
                'legacy_image_path' => $this->imagePath($entry['photo'] ?? null),
                'extraction_status' => 'published',
                'sort_order' => (int) ($entry['order'] ?? 0),
                'is_enabled' => true,
            ])->save();

            foreach ($metadata as $locale => $extracted) {
                $this->writeTranslation($publication, $locale, $titles[$locale], $entry, $extracted);
            }
            ResearchPublicationTranslation::query()
                ->where('research_publication_id', $publication->getKey())
                ->whereNotIn('locale', array_keys($metadata))
                ->delete();

            $publication->legacyFileReferences()->delete();
            foreach ($this->attachments($entry) as $index => $attachment) {
                LegacyResearchFileReference::query()->create([
                    'research_publication_id' => (int) $publication->getKey(),
                    'legacy_source_table' => self::ITEM_TABLE,
                    'legacy_source_id' => $attachment['source_id'],
                    'legacy_path' => $attachment['path'],
                    'label_ar' => $attachment['label_ar'],
                    'label_en' => $attachment['label_en'],
                    'sort_order' => $index,
                    'status' => 'deferred',
                ]);
            }

            return $publication;
        });

        MigrationLog::query()->updateOrCreate(
            ['module' => 'research', 'source_table' => self::SOURCE_TABLE, 'source_id' => $sourceId, 'target_table' => 'research_publications'],
            [
                'batch_name' => self::APPROVAL_TOKEN,
                'target_id' => (int) $publication->getKey(),
                'status' => 'success',
                'message' => 'Imported a faculty research list entry.',
                'metadata' => ['legacy_service_type' => (int) $entry['service_type'], 'faculty_id' => $facultyId],
            ],
        );

        return $publication;
    }

    /** @param array<string, mixed> $entry */
    private function writeTranslation(ResearchPublication $publication, string $locale, string $title, array $entry, LegacyResearchMetadataDTO $extracted): void
    {
        $abstract = $extracted->abstract ?? $this->body($entry[$locale.'_data'] ?? null);
        $brief = $this->plain($entry[$locale.'_brief'] ?? null);

        ResearchPublicationTranslation::query()->updateOrCreate(
            ['research_publication_id' => (int) $publication->getKey(), 'locale' => $locale],
            [
                'title' => $title,
                'authors' => $extracted->authors,
                'excerpt' => $brief ?? ($abstract !== null ? Str::limit(trim(strip_tags($abstract)), 260) : null),
                'abstract' => $abstract,
                'publisher' => $extracted->publisher,
                'citation' => $extracted->citation,
                'keywords' => $extracted->keywords,
            ],
        );
    }

    /**
     * @param  array<string, mixed>  $entry
     * @return list<array{source_id: int, path: string, label_ar: ?string, label_en: ?string}>
     */
    private function attachments(array $entry): array
    {
        $attachments = [];
        $seen = [];
        foreach ($entry['items'] ?? [] as $item) {
            if (! ($item['visible'] ?? false)) {
                continue;
            }
            foreach ([$item['en_file'] ?? null, $item['ar_file'] ?? null] as $file) {
                $path = $this->legacyFilePath($file);
                if ($path === null || isset($seen[$path])) {
                    continue;
                }
                $seen[$path] = true;
                $attachments[] = [
                    'source_id' => (int) $item['id'],
                    'path' => $path,
                    'label_ar' => $this->plain($item['ar_name'] ?? null),
                    'label_en' => $this->usableEnglish($item['en_name'] ?? null),
                ];
            }
        }

        return $attachments;
    }

    /** @param array<string, mixed> $entry @return array{ar: string, en: ?string}|null */
    private function titles(array $entry): ?array
    {
        $arabic = $this->plain($entry['ar_name'] ?? null);
        $english = $this->usableEnglish($entry['en_name'] ?? null);

        if ($arabic === null && $english === null) {
            foreach ($entry['items'] ?? [] as $item) {
                $arabic ??= $this->plain($item['ar_name'] ?? null);
            }
        }

        $arabic ??= $english;

        return $arabic === null ? null : ['ar' => $arabic, 'en' => $english];
    }

    /**
     * @param  array<string, array<int|string, int>>  $owners
     */
    private function ownerFaculty(?int $ownerId, array $owners): ?string
    {
        if ($ownerId === null) {
            return null;
        }

        $candidates = array_values(array_unique(array_filter([
            self::OWNER_SERVICES[(int) ($owners['jx_councils'][$ownerId] ?? $owners['jx_councils'][(string) $ownerId] ?? 0)] ?? null,
            self::OWNER_SERVICES[(int) ($owners['jx_councils1'][$ownerId] ?? $owners['jx_councils1'][(string) $ownerId] ?? 0)] ?? null,
        ])));

        return count($candidates) === 1 ? $candidates[0] : null;
    }

    /** The site's usual slug, with long titles cut at a word so it fits the column. @param array{ar: string, en: ?string} $titles */
    private function slugFor(array $titles, int $sourceId): string
    {
        $slug = ResearchPublicationSlug::derive($titles['en'], $titles['ar'], $sourceId);
        if (ResearchPublicationSlug::isValid($slug)) {
            return $slug;
        }

        $base = substr($slug, 0, -strlen('-'.$sourceId));
        $base = substr($base, 0, 150);
        $base = str_contains($base, '-') ? substr($base, 0, (int) strrpos($base, '-')) : $base;

        return trim($base, '-').'-'.$sourceId;
    }

    private function uniqueSlug(string $slug): string
    {
        $candidate = $slug;
        $suffix = 2;
        while (ResearchPublication::withTrashed()->where('slug', $candidate)->exists()) {
            $candidate = $slug.'-'.$suffix++;
        }

        return $candidate;
    }

    private function titleKey(?string $title): string
    {
        $plain = mb_strtolower(html_entity_decode(strip_tags((string) $title), ENT_QUOTES | ENT_HTML5, 'UTF-8'));

        return preg_replace('/[^\p{L}\p{N}]+/u', '', $plain) ?? '';
    }

    private function body(mixed $html): ?string
    {
        $html = $this->clean($html);
        if ($html === null) {
            return null;
        }

        $sanitized = $this->htmlSanitizer->sanitize($html);
        $plain = is_string($sanitized) ? trim(html_entity_decode(strip_tags($sanitized), ENT_QUOTES | ENT_HTML5, 'UTF-8')) : '';
        if ($plain === '' || in_array(mb_strtolower($plain), ['spu', 'قيد الإعداد'], true) || $this->isPlaceholder($plain)) {
            return null;
        }

        return $sanitized;
    }

    private function plain(mixed $value): ?string
    {
        $value = $this->clean($value);
        if ($value === null) {
            return null;
        }

        $value = trim(preg_replace('/\s+/u', ' ', html_entity_decode(strip_tags($value), ENT_QUOTES | ENT_HTML5, 'UTF-8')) ?? '');

        return $value !== '' ? $value : null;
    }

    private function usableEnglish(mixed $value): ?string
    {
        $value = $this->plain($value);

        return $value === null || $this->isPlaceholder($value) ? null : $value;
    }

    private function isPlaceholder(string $value): bool
    {
        $value = mb_strtolower($value);
        foreach (['under construction', 'sous construction', 'sorry, this page is temporarily unavailable', 'translation to english will', 'translation in english will'] as $placeholder) {
            if (str_contains($value, $placeholder)) {
                return true;
            }
        }

        return false;
    }

    private function clean(mixed $value): ?string
    {
        return is_string($value) && trim($value) !== '' ? $this->textCleaner->clean($value) : null;
    }

    /** A card image, in the photo directory like every other legacy image (a bare name 404s at the site root). */
    private function imagePath(mixed $value): ?string
    {
        $path = $this->legacyFilePath($value);
        $extension = strtolower(pathinfo((string) parse_url((string) $path, PHP_URL_PATH), PATHINFO_EXTENSION));

        return $path !== null && in_array($extension, self::IMAGE_EXTENSIONS, true) ? $path : null;
    }

    /** Same location rule as the staff-profile research import: bare file names live in the photo directory. */
    private function legacyFilePath(mixed $value): ?string
    {
        $value = is_string($value) ? trim(str_replace('\\', '/', $value)) : '';
        if ($value === '') {
            return null;
        }
        if (str_starts_with($value, '/') || preg_match('#^(https?:)?//#i', $value) === 1 || str_contains($value, '/')) {
            return $value;
        }

        return trim((string) config('legacy_media.photo_directory', 'downloads/files'), '/').'/'.$value;
    }

    /** @return array{entries: list<array<string, mixed>>, owners: array<string, array<int|string, int>>} */
    private function readJson(string $path): array
    {
        if (! is_file($path) || ! is_readable($path)) {
            throw new InvalidArgumentException('The research data file could not be read: '.$path);
        }

        try {
            $data = json_decode((string) file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new InvalidArgumentException('The research data file is not valid JSON: '.$exception->getMessage());
        }

        if (! is_array($data) || ! is_array($data['entries'] ?? null) || ! is_array($data['owners'] ?? null)) {
            throw new InvalidArgumentException('The research data file has no entries or owners.');
        }

        return ['entries' => array_values(array_filter($data['entries'], 'is_array')), 'owners' => $data['owners']];
    }

    /** @param array<string, mixed> $row @param list<string> $keys @return array<string, ?string> */
    private function only(array $row, array $keys): array
    {
        $values = [];
        foreach ($keys as $key) {
            $value = $row[$key] ?? null;
            $values[$key] = is_string($value) && trim($value) !== '' ? $value : null;
        }

        return $values;
    }
}
