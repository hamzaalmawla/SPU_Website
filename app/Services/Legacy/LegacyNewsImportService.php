<?php

declare(strict_types=1);

namespace App\Services\Legacy;

use App\Contracts\Legacy\LegacyNewsImportServiceInterface;
use App\Contracts\Shared\CacheServiceInterface;
use App\Contracts\Shared\SlugServiceInterface;
use App\DTOs\Legacy\LegacyNewsImportResultDTO;
use App\Models\News\NewsArticle;
use App\Models\News\NewsArticleAttachment;
use App\Models\News\NewsArticleSeoMeta;
use App\Models\News\NewsArticleTranslation;
use App\Models\News\NewsCategory;
use App\Models\News\NewsCategoryTranslation;
use App\Models\Shared\MigrationLog;
use App\Support\LegacyImport\DateNormalizer;
use App\Support\LegacyImport\HtmlSanitizer;
use App\Support\LegacyImport\OldDatabaseConnection;
use App\Support\LegacyImport\TextCleaner;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use InvalidArgumentException;

final class LegacyNewsImportService implements LegacyNewsImportServiceInterface
{
    private const APPROVAL_TOKEN = 'phase6-news';

    private const SOURCE_TABLE = 'jx_categories';

    /** @var list<string> */
    private const REQUIRED_HEADERS = [
        'source_table',
        'source_id',
        'subsite',
        'service_type',
        'approval_decision',
        'approved_target',
    ];

    public function __construct(
        private readonly OldDatabaseConnection $oldDatabase,
        private readonly TextCleaner $textCleaner,
        private readonly HtmlSanitizer $htmlSanitizer,
        private readonly DateNormalizer $dateNormalizer,
        private readonly SlugServiceInterface $slugService,
        private readonly CacheServiceInterface $cacheService,
    ) {}

    public function import(
        bool $write = false,
        ?string $approval = null,
        ?string $batch = null,
        ?string $input = null,
        string $disk = 'local',
    ): LegacyNewsImportResultDTO {
        if ($write && $approval !== self::APPROVAL_TOKEN) {
            throw new InvalidArgumentException('Importing Phase 6 news requires --approve='.self::APPROVAL_TOKEN.'.');
        }

        $batch = $batch !== null && trim($batch) !== '' ? trim($batch) : 'phase6-news-'.now()->format('Ymd_His');

        if ($input === null || trim($input) === '') {
            if ($write) {
                throw new InvalidArgumentException('Importing Phase 6 news requires an approved category review packet CSV.');
            }

            return new LegacyNewsImportResultDTO(false, $batch, 0, 0, 0, 0, 0, 0, []);
        }

        [$scannedRows, $approvedRows, $skippedRows, $skipReasonCounts] = $this->approvedPacketRows(trim($input), $disk);
        $inputChecksum = hash('sha256', Storage::disk($disk)->get(trim($input)));
        $approvedIds = array_keys($approvedRows);
        $rows = $approvedIds === []
            ? collect()
            : $this->oldDatabase->table(self::SOURCE_TABLE)->whereIn('id', $approvedIds)->get()->keyBy('id');
        $childCounts = $this->childCounts();
        $childSourceIds = array_fill_keys(array_keys($childCounts), true);
        [$sourceIds, $sourceTitleGroups] = $this->sourceIdentityEvidence($childCounts);
        $categories = [];
        $importableRows = 0;
        $importedRows = 0;
        $createdTranslations = 0;
        $createdAttachments = 0;

        foreach ($approvedRows as $sourceId => $packetRow) {
            $row = $rows->get($sourceId);

            if (! is_object($row)) {
                $this->countSkip($skipReasonCounts, 'missing_source');
                $skippedRows++;

                continue;
            }

            $serviceType = $this->integerValue($row, 'service_type');
            if ($serviceType !== $packetRow['service_type']) {
                $this->countSkip($skipReasonCounts, 'source_service_mismatch');
                $skippedRows++;

                continue;
            }

            if ($this->integerValue($row, 'is_visible') !== 1) {
                $this->countSkip($skipReasonCounts, 'hidden_source');
                $skippedRows++;

                continue;
            }

            if ($this->truthy($this->value($row, 'is_link'))) {
                $this->countSkip($skipReasonCounts, 'external_link_source');
                $skippedRows++;

                continue;
            }

            if (! $this->hasContentOrChildren($row, isset($childSourceIds[$sourceId]))) {
                $this->countSkip($skipReasonCounts, 'empty_content_and_children');
                $skippedRows++;

                continue;
            }
            $parentId = $this->integerValue($row, 'parent');
            if ($parentId !== null && $parentId !== 0 && ! isset($sourceIds[$parentId])) {
                $this->countSkip($skipReasonCounts, 'orphan_parent');
                $skippedRows++;

                continue;
            }

            if ($this->alreadyImported($sourceId)) {
                $this->countSkip($skipReasonCounts, 'already_imported');
                $skippedRows++;

                continue;
            }

            $translations = $this->translations($row);

            if ($translations === []) {
                $this->countSkip($skipReasonCounts, 'missing_translation');
                $skippedRows++;

                if ($write) {
                    $this->logSkip($sourceId, $batch, 'Skipped legacy news row without a usable AR/EN title.');
                }

                continue;
            }
            $duplicateStatus = $this->duplicateTitleStatus(
                $serviceType,
                $translations,
                $sourceTitleGroups,
                $this->sourceDuplicateFingerprint($row, (int) ($childCounts[$sourceId] ?? 0)),
            );
            $duplicateDisposition = (string) ($packetRow['approval_decision'] ?? 'import');

            if ($duplicateStatus === 'uncertain' && $duplicateDisposition !== 'canonical') {
                $this->countSkip($skipReasonCounts, 'duplicate_disposition_required');
                $skippedRows++;

                continue;
            }

            $importableRows++;

            if (! $write) {
                continue;
            }

            if ($categories === []) {
                $categories = $this->ensureCategories();
            }

            $category = $categories[$serviceType] ?? $categories[3];
            [$translationCount, $attachmentCount] = $this->writeArticle(
                $row,
                $sourceId,
                $serviceType,
                $category,
                $translations,
                $batch,
                trim($input),
                $disk,
                $inputChecksum,
                $duplicateStatus,
                $duplicateDisposition,
            );
            $createdTranslations += $translationCount;
            $createdAttachments += $attachmentCount;
            $importedRows++;
        }

        if ($importedRows > 0 && ! $this->cacheService->flushTags(['news', 'public-pages', 'seo', 'sitemap'])) {
            $this->cacheService->flushAll();
        }

        return new LegacyNewsImportResultDTO(
            written: $write,
            batch: $batch,
            scannedRows: $scannedRows,
            importableRows: $importableRows,
            importedRows: $importedRows,
            createdTranslations: $createdTranslations,
            createdAttachments: $createdAttachments,
            skippedRows: $skippedRows,
            skipReasonCounts: $skipReasonCounts,
        );
    }

    /**
     * @return array{0: int, 1: array<int, array{service_type: int, approval_decision: string}>, 2: int, 3: array<string, int>}
     */
    private function approvedPacketRows(string $input, string $disk): array
    {
        if (! Storage::disk($disk)->exists($input)) {
            throw new InvalidArgumentException('Approved category review packet CSV ['.$input.'] does not exist on disk ['.$disk.'].');
        }

        $stream = fopen('php://temp', 'r+');
        if ($stream === false) {
            throw new InvalidArgumentException('Approved category review packet CSV could not be opened.');
        }

        fwrite($stream, Storage::disk($disk)->get($input));
        rewind($stream);
        $headers = fgetcsv($stream);

        if (! is_array($headers)) {
            fclose($stream);
            throw new InvalidArgumentException('Approved category review packet CSV is empty.');
        }

        $headers = array_map(static fn (mixed $header): string => trim((string) $header), $headers);
        $headers[0] = ltrim($headers[0] ?? '', "\xEF\xBB\xBF");
        $missingHeaders = array_values(array_diff(self::REQUIRED_HEADERS, $headers));
        if ($missingHeaders !== [] || count($headers) !== count(array_unique($headers))) {
            fclose($stream);
            $message = $missingHeaders !== [] ? ' Missing: '.implode(', ', $missingHeaders).'.' : ' Duplicate headers are not allowed.';
            throw new InvalidArgumentException('Input is not a valid category review packet CSV.'.$message);
        }

        $scanned = 0;
        $skipped = 0;
        $reasons = [];
        $packetRows = [];
        $candidates = [];

        while (($values = fgetcsv($stream)) !== false) {
            if ($values === [null] || $values === []) {
                continue;
            }

            $scanned++;
            if (count($values) !== count($headers)) {
                $this->countSkip($reasons, 'malformed_packet_row');
                $skipped++;

                continue;
            }

            /** @var array<string, string> $packetRow */
            $packetRow = array_combine($headers, array_map(static fn (mixed $value): string => trim((string) $value), $values));
            $packetRows[] = $packetRow;
        }
        fclose($stream);

        $sourceIdCounts = [];
        foreach ($packetRows as $packetRow) {
            if (ctype_digit($packetRow['source_id']) && (int) $packetRow['source_id'] > 0) {
                $sourceId = (int) $packetRow['source_id'];
                $sourceIdCounts[$sourceId] = ($sourceIdCounts[$sourceId] ?? 0) + 1;
            }
        }

        foreach ($packetRows as $packetRow) {
            $decision = Str::lower($packetRow['approval_decision']);
            $target = Str::lower($packetRow['approved_target']);

            if (ctype_digit($packetRow['source_id'])
                && (int) $packetRow['source_id'] > 0
                && ($sourceIdCounts[(int) $packetRow['source_id']] ?? 0) > 1) {
                $this->countSkip($reasons, 'duplicate_source_id');
                $skipped++;

                continue;
            }
            if (! in_array($decision, ['import', 'canonical', 'redirect'], true)) {
                $this->countSkip($reasons, $decision === '' ? 'blank_approval_decision' : 'approval_decision_not_import');
                $skipped++;

                continue;
            }
            if ($decision === 'redirect') {
                if ($target !== 'redirect') {
                    $this->countSkip($reasons, 'redirect_disposition_requires_redirect_target');
                } else {
                    $this->countSkip($reasons, 'redirect_disposition_requires_continuity_packet');
                }
                $skipped++;

                continue;
            }
            if ($target !== 'news') {
                $this->countSkip($reasons, $target === '' ? 'blank_approved_target' : 'approved_target_not_news');
                $skipped++;

                continue;
            }
            if ($packetRow['source_table'] !== self::SOURCE_TABLE) {
                $this->countSkip($reasons, 'source_table_mismatch');
                $skipped++;

                continue;
            }
            if ($packetRow['subsite'] !== 'root') {
                $this->countSkip($reasons, 'subsite_mismatch');
                $skipped++;

                continue;
            }
            if (! ctype_digit($packetRow['source_id']) || (int) $packetRow['source_id'] < 1) {
                $this->countSkip($reasons, 'invalid_source_id');
                $skipped++;

                continue;
            }
            if (! ctype_digit($packetRow['service_type']) || ! in_array((int) $packetRow['service_type'], [3, 4], true)) {
                $this->countSkip($reasons, 'invalid_service_type');
                $skipped++;

                continue;
            }

            $sourceId = (int) $packetRow['source_id'];
            $candidates[$sourceId][] = [
                'service_type' => (int) $packetRow['service_type'],
                'approval_decision' => $decision,
            ];
        }

        $approved = [];
        foreach ($candidates as $sourceId => $rows) {
            $approved[$sourceId] = $rows[0];
        }

        return [$scanned, $approved, $skipped, $reasons];
    }

    /** @return array<int, NewsCategory> */
    private function ensureCategories(): array
    {
        return [
            3 => $this->ensureCategory('news', 'news', 'الأخبار', 'News', 1),
            4 => $this->ensureCategory('announcements', 'announcement', 'الإعلانات', 'Announcements', 2),
        ];
    }

    private function ensureCategory(string $slug, string $type, string $nameAr, string $nameEn, int $sortOrder): NewsCategory
    {
        $category = NewsCategory::query()->updateOrCreate(
            ['slug' => $slug],
            ['type' => $type, 'sort_order' => $sortOrder, 'is_enabled' => true],
        );

        foreach (['ar' => $nameAr, 'en' => $nameEn] as $locale => $name) {
            NewsCategoryTranslation::query()->updateOrCreate(
                ['news_category_id' => (int) $category->getKey(), 'locale' => $locale],
                ['name' => $name, 'description' => null],
            );
        }

        return $category;
    }

    /** @return array<string, array{title: string, excerpt: ?string, body: ?string}> */
    private function translations(object $row): array
    {
        $translations = [];

        foreach (['ar', 'en'] as $locale) {
            $title = $this->plainTextValue($row, $locale.'_name');

            if ($title === null || Str::lower($title) === 'under construction') {
                continue;
            }

            $translations[$locale] = [
                'title' => $title,
                'excerpt' => $this->plainTextValue($row, $locale.'_brief'),
                'body' => $this->htmlSanitizer->sanitize($this->stringValue($row, $locale.'_data')),
            ];
        }

        return $translations;
    }

    /**
     * @param  array<string, array{title: string, excerpt: ?string, body: ?string}>  $translations
     * @return array{0: int, 1: int}
     */
    private function writeArticle(
        object $row,
        int $sourceId,
        int $serviceType,
        NewsCategory $category,
        array $translations,
        string $batch,
        string $input,
        string $disk,
        string $inputChecksum,
        string $duplicateStatus,
        string $duplicateDisposition,
    ): array {
        return DB::transaction(function () use ($row, $sourceId, $serviceType, $category, $translations, $batch, $input, $disk, $inputChecksum, $duplicateStatus, $duplicateDisposition): array {
            $title = $translations['en']['title'] ?? $translations['ar']['title'];
            $article = NewsArticle::query()->create([
                'news_category_id' => (int) $category->getKey(),
                'cover_media_id' => null,
                'slug' => $this->legacySlug($title, $sourceId),
                'status' => 'draft',
                'published_at' => null,
                'scheduled_at' => null,
                'is_enabled' => false,
                'is_featured' => false,
                'sort_order' => $this->integerValue($row, 'category_order') ?? 0,
                'faculty_scope_slug' => null,
                'legacy_source_table' => 'jx_categories',
                'legacy_source_id' => $sourceId,
                'legacy_service_type' => $serviceType,
                'legacy_url' => $this->stringValue($row, 'url'),
                'legacy_cover_path' => $this->legacyPhotoPath($this->stringValue($row, 'photo')),
            ]);

            foreach ($translations as $locale => $translation) {
                NewsArticleTranslation::query()->create([
                    'news_article_id' => (int) $article->getKey(),
                    'locale' => $locale,
                    'title' => $translation['title'],
                    'excerpt' => $translation['excerpt'],
                    'body' => $translation['body'],
                ]);
                NewsArticleSeoMeta::query()->create([
                    'news_article_id' => (int) $article->getKey(),
                    'locale' => $locale,
                    'meta_title' => $translation['title'],
                    'meta_description' => $translation['excerpt'],
                    'og_title' => $translation['title'],
                    'og_description' => $translation['excerpt'],
                    'og_image_media_id' => null,
                    'og_image_url' => null,
                    'robots' => 'noindex,nofollow',
                ]);
            }

            $attachmentCount = $this->writeAttachments($article, $sourceId);
            MigrationLog::query()->create([
                'module' => 'news',
                'batch_name' => $batch,
                'source_table' => 'jx_categories',
                'source_id' => $sourceId,
                'target_table' => 'news_articles',
                'target_id' => (int) $article->getKey(),
                'status' => 'success',
                'message' => 'Imported Phase 6 legacy news article with media files deferred.',
                'metadata' => [
                    'phase' => 'phase6',
                    'service_type' => $serviceType,
                    'approval_packet' => ['disk' => $disk, 'path' => $input, 'sha256' => $inputChecksum],
                    'legacy_visibility' => $this->value($row, ['is_visible', 'is_active', 'active', 'is_enabled']),
                    'legacy_dates' => [
                        'start_raw' => $this->value($row, 'start_date'),
                        'start_normalized' => $this->dateNormalizer->normalize($this->value($row, 'start_date'))?->toDateTimeString(),
                        'end_raw' => $this->value($row, 'end_date'),
                        'end_normalized' => $this->dateNormalizer->normalize($this->value($row, 'end_date'))?->toDateTimeString(),
                    ],
                    'legacy_photo' => $this->stringValue($row, 'photo'),
                    'attachments_deferred' => true,
                    'locale_fallback_policy' => isset($translations['en']) ? null : 'display_arabic_source_in_english',
                    'duplicate_title_status' => $duplicateStatus,
                    'duplicate_disposition' => $duplicateDisposition,
                    'slug_source_id' => $sourceId,
                ],
            ]);

            return [count($translations), $attachmentCount];
        });
    }

    private function writeAttachments(NewsArticle $article, int $sourceId): int
    {
        $rows = $this->oldDatabase->table('jx_items')
            ->where('category_id', $sourceId)
            ->orderBy('item_order')
            ->orderBy('id')
            ->get();
        $created = 0;

        foreach ($rows as $row) {
            $itemId = $this->integerValue($row, 'id');

            foreach ([
                ['kind' => 'image', 'column' => 'photo'],
                ['kind' => 'file', 'column' => 'ar_file'],
                ['kind' => 'file', 'column' => 'en_file'],
            ] as $index => $definition) {
                $path = $this->legacyPhotoPath($this->stringValue($row, $definition['column']));

                if ($itemId === null || $path === null) {
                    continue;
                }

                NewsArticleAttachment::query()->updateOrCreate(
                    [
                        'legacy_source_table' => 'jx_items',
                        'legacy_source_id' => $itemId,
                        'legacy_path' => $path,
                    ],
                    [
                        'news_article_id' => (int) $article->getKey(),
                        'media_asset_id' => null,
                        'kind' => $definition['kind'],
                        'label_ar' => $this->stringValue($row, 'ar_name'),
                        'label_en' => $this->stringValue($row, 'en_name'),
                        'sort_order' => ($this->integerValue($row, 'item_order') ?? 0) + $index,
                    ],
                );
                $created++;
            }
        }

        return $created;
    }

    private function hasContentOrChildren(object $row, bool $hasChildren): bool
    {
        if ($hasChildren) {
            return true;
        }

        foreach (['ar_brief', 'en_brief', 'ar_data', 'en_data'] as $column) {
            if ($this->stringValue($row, $column) !== null) {
                return true;
            }
        }

        return false;
    }

    /** @param array<int, int> $childCounts @return array{array<int, true>, array<string, array{rows: int, fingerprints: array<string, int>}>} */
    private function sourceIdentityEvidence(array $childCounts = []): array
    {
        $ids = [];
        $titleGroups = [];
        $lengthFunction = $this->oldDatabase->connection()->getDriverName() === 'sqlite' ? 'LENGTH' : 'CHAR_LENGTH';
        $rows = $this->oldDatabase->table(self::SOURCE_TABLE)
            ->select(['id', 'service_type', 'is_visible', 'is_link', 'ar_name', 'en_name'])
            ->selectRaw($lengthFunction.'(ar_data) as ar_content_length')
            ->selectRaw($lengthFunction.'(en_data) as en_content_length')
            ->lazyById(500, 'id');

        foreach ($rows as $row) {
            $id = $this->integerValue($row, 'id');
            if ($id !== null) {
                $ids[$id] = true;
            }
            $service = $this->integerValue($row, 'service_type');
            if (! in_array($service, [3, 4], true) || $this->integerValue($row, 'is_visible') !== 1 || $this->truthy($this->value($row, 'is_link'))) {
                continue;
            }
            foreach (['ar', 'en'] as $locale) {
                $title = $this->plainTextValue($row, $locale.'_name');
                if ($title === null || Str::lower($title) === 'under construction') {
                    continue;
                }

                $key = $service.'|'.$locale.'|'.$this->normalizedTitle($title);
                $group = $titleGroups[$key] ?? ['rows' => 0, 'fingerprints' => []];
                $group['rows']++;
                $fingerprint = $this->sourceDuplicateFingerprint($row, (int) ($childCounts[$id] ?? 0));
                $group['fingerprints'][$fingerprint] = ($group['fingerprints'][$fingerprint] ?? 0) + 1;
                $titleGroups[$key] = $group;
            }
        }

        return [$ids, $titleGroups];
    }

    /** @param array<string, array{title: string, excerpt: ?string, body: ?string}> $translations @param array<string, array{rows: int, fingerprints: array<string, int>}> $titleGroups */
    private function duplicateTitleStatus(int $serviceType, array $translations, array $titleGroups, string $sourceFingerprint): string
    {
        $status = 'unique';

        foreach ($translations as $locale => $translation) {
            $key = $serviceType.'|'.$locale.'|'.$this->normalizedTitle($translation['title']);
            $group = $titleGroups[$key] ?? null;

            if (! is_array($group) || $group['rows'] < 2) {
                continue;
            }

            if (($group['fingerprints'][$sourceFingerprint] ?? 0) > 1) {
                return 'uncertain';
            }

            $status = 'materially_distinct';
        }

        return $status;
    }

    /** @return array<int, int> */
    private function childCounts(): array
    {
        if (! $this->oldDatabase->schema()->hasTable('jx_items')) {
            return [];
        }

        return $this->oldDatabase->table('jx_items')
            ->select('category_id')
            ->selectRaw('COUNT(*) as child_total_count')
            ->groupBy('category_id')
            ->get()
            ->mapWithKeys(static fn (object $row): array => [(int) $row->category_id => (int) $row->child_total_count])
            ->all();
    }

    private function sourceDuplicateFingerprint(object $row, int $childCount): string
    {
        return implode('|', [
            (string) ($row->ar_content_length ?? strlen((string) $this->value($row, 'ar_data', ''))),
            (string) ($row->en_content_length ?? strlen((string) $this->value($row, 'en_data', ''))),
            (string) $childCount,
        ]);
    }

    private function legacySlug(string $title, int $sourceId): string
    {
        $suffix = '-'.$sourceId;
        $baseLength = max(20, 80 - strlen($suffix));
        $base = $this->slugService->generate($title, NewsArticle::class, 'en', null, $baseLength);
        $base = rtrim(substr($base, 0, $baseLength), '-');

        return $this->slugService->generate($base.$suffix, NewsArticle::class, 'en', null, 80);
    }

    private function normalizedTitle(string $title): string
    {
        return Str::lower(trim((string) preg_replace('/\s+/u', ' ', strip_tags($title))));
    }

    private function truthy(mixed $value): bool
    {
        return in_array($value, [true, 1, '1', 'true', 'yes', 'on'], true);
    }

    private function alreadyImported(int $sourceId): bool
    {
        return MigrationLog::query()
            ->where('module', 'news')
            ->where('source_table', 'jx_categories')
            ->where('source_id', $sourceId)
            ->where('target_table', 'news_articles')
            ->whereIn('status', ['success', 'skipped'])
            ->exists();
    }

    private function logSkip(int $sourceId, string $batch, string $message): void
    {
        MigrationLog::query()->create([
            'module' => 'news',
            'batch_name' => $batch,
            'source_table' => 'jx_categories',
            'source_id' => $sourceId,
            'target_table' => 'news_articles',
            'target_id' => null,
            'status' => 'skipped',
            'message' => $message,
            'metadata' => ['phase' => 'phase6'],
        ]);
    }

    private function stringValue(object $row, string $key): ?string
    {
        return $this->textCleaner->clean((string) $this->value($row, $key, ''));
    }

    private function plainTextValue(object $row, string $key): ?string
    {
        $value = $this->stringValue($row, $key);

        return $value === null ? null : html_entity_decode($value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }

    private function integerValue(object $row, string $key): ?int
    {
        $value = $this->value($row, $key);

        return is_numeric($value) ? (int) $value : null;
    }

    private function value(object $row, array|string $keys, mixed $default = null): mixed
    {
        foreach (is_array($keys) ? $keys : [$keys] as $key) {
            if (isset($row->{$key})) {
                return $row->{$key};
            }
        }

        return $default;
    }

    /** @param array<string, int> $counts */
    private function countSkip(array &$counts, string $reason): void
    {
        $counts[$reason] = ($counts[$reason] ?? 0) + 1;
    }

    /**
     * Turn a legacy image column into a path the media resolver can use.
     *
     * The old CMS stored these as a bare filename and prefixed the download
     * directory at render time. legacy_cover_path is consumed as a path, so a
     * bare filename would resolve to "/<file>" at the web root and 404.
     */
    private function legacyPhotoPath(?string $value): ?string
    {
        $value = is_string($value) ? trim(str_replace('\\', '/', $value)) : '';

        if ($value === '') {
            return null;
        }

        // Already absolute, already a URL, or already carries a directory.
        if (str_starts_with($value, '/') || preg_match('#^(https?:)?//#i', $value) === 1 || str_contains($value, '/')) {
            return $value;
        }

        $directory = trim((string) config('legacy_media.photo_directory', 'downloads/files'), '/');

        return $directory === '' ? $value : $directory.'/'.$value;
    }
}
