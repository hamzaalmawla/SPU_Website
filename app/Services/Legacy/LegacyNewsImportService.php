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
use Illuminate\Support\Str;
use InvalidArgumentException;

final class LegacyNewsImportService implements LegacyNewsImportServiceInterface
{
    private const APPROVAL_TOKEN = 'phase6-news';

    public function __construct(
        private readonly OldDatabaseConnection $oldDatabase,
        private readonly TextCleaner $textCleaner,
        private readonly HtmlSanitizer $htmlSanitizer,
        private readonly DateNormalizer $dateNormalizer,
        private readonly SlugServiceInterface $slugService,
        private readonly CacheServiceInterface $cacheService,
    ) {}

    public function import(bool $write = false, ?string $approval = null, ?string $batch = null): LegacyNewsImportResultDTO
    {
        if ($write && $approval !== self::APPROVAL_TOKEN) {
            throw new InvalidArgumentException('Importing Phase 6 news requires --approve='.self::APPROVAL_TOKEN.'.');
        }

        $batch = $batch !== null && trim($batch) !== '' ? trim($batch) : 'phase6-news-'.now()->format('Ymd_His');
        $rows = $this->oldDatabase->table('jx_categories')
            ->whereIn('service_type', [3, 4])
            ->orderBy('id')
            ->get();
        $categories = $write ? $this->ensureCategories() : [];
        $importableRows = 0;
        $importedRows = 0;
        $createdTranslations = 0;
        $createdAttachments = 0;
        $skippedRows = 0;
        $skipReasonCounts = [];

        foreach ($rows as $row) {
            $sourceId = $this->integerValue($row, 'id');

            if ($sourceId === null) {
                $this->countSkip($skipReasonCounts, 'missing_source_id');
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

            $importableRows++;

            if (! $write) {
                continue;
            }

            $serviceType = $this->integerValue($row, 'service_type') ?? 3;
            $category = $categories[$serviceType] ?? $categories[3];
            [$translationCount, $attachmentCount] = $this->writeArticle($row, $sourceId, $serviceType, $category, $translations, $batch);
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
            scannedRows: $rows->count(),
            importableRows: $importableRows,
            importedRows: $importedRows,
            createdTranslations: $createdTranslations,
            createdAttachments: $createdAttachments,
            skippedRows: $skippedRows,
            skipReasonCounts: $skipReasonCounts,
        );
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
            $title = $this->stringValue($row, $locale.'_name');

            if ($title === null || Str::lower($title) === 'under construction') {
                continue;
            }

            $translations[$locale] = [
                'title' => $title,
                'excerpt' => $this->stringValue($row, $locale.'_brief'),
                'body' => $this->htmlSanitizer->sanitize($this->stringValue($row, $locale.'_data')),
            ];
        }

        if (! isset($translations['ar']) && isset($translations['en'])) {
            $translations['ar'] = $translations['en'];
        }

        if (! isset($translations['en']) && isset($translations['ar'])) {
            $translations['en'] = $translations['ar'];
        }

        return $translations;
    }

    /**
     * @param  array<string, array{title: string, excerpt: ?string, body: ?string}>  $translations
     * @return array{0: int, 1: int}
     */
    private function writeArticle(object $row, int $sourceId, int $serviceType, NewsCategory $category, array $translations, string $batch): array
    {
        return DB::transaction(function () use ($row, $sourceId, $serviceType, $category, $translations, $batch): array {
            $title = $translations['en']['title'] ?? $translations['ar']['title'];
            $enabled = $this->visible($row);
            $article = NewsArticle::query()->create([
                'news_category_id' => (int) $category->getKey(),
                'cover_media_id' => null,
                'slug' => $this->slugService->generate($title, NewsArticle::class, 'en', null, 80),
                'status' => $enabled ? 'published' : 'draft',
                'published_at' => $this->dateNormalizer->normalize($this->value($row, ['start_date', 'end_date']))?->toDateTimeString(),
                'scheduled_at' => null,
                'is_enabled' => $enabled,
                'is_featured' => false,
                'sort_order' => $this->integerValue($row, 'category_order') ?? 0,
                'faculty_scope_slug' => null,
                'legacy_source_table' => 'jx_categories',
                'legacy_source_id' => $sourceId,
                'legacy_service_type' => $serviceType,
                'legacy_url' => $this->stringValue($row, 'url'),
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
                    'robots' => 'index,follow',
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
                    'legacy_photo' => $this->stringValue($row, 'photo'),
                    'attachments_deferred' => true,
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
                $path = $this->stringValue($row, $definition['column']);

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

    private function visible(object $row): bool
    {
        $value = $this->value($row, ['is_visible', 'is_active', 'active', 'is_enabled']);

        return $value === null || (string) $value === '1' || $value === 1 || $value === true;
    }

    private function stringValue(object $row, string $key): ?string
    {
        return $this->textCleaner->clean((string) $this->value($row, $key, ''));
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
}
