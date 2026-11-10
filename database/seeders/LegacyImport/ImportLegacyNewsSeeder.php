<?php

declare(strict_types=1);

namespace Database\Seeders\LegacyImport;

use App\Contracts\Shared\SlugServiceInterface;
use App\Models\News\NewsArticle;
use App\Models\News\NewsArticleAttachment;
use App\Models\News\NewsArticleSeoMeta;
use App\Models\News\NewsArticleTranslation;
use App\Models\News\NewsCategory;
use App\Models\News\NewsCategoryTranslation;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;

final class ImportLegacyNewsSeeder extends BaseLegacyImportSeeder
{
    public function run(): void
    {
        $module = 'news';

        if (! $this->shouldRunModule($module)) {
            return;
        }

        $batch = $this->batchName($module);
        $categories = $this->ensureCategories();
        $imported = 0;
        $skipped = 0;

        if (! $this->legacyTableExists('jx_categories')) {
            $this->command?->warn('Legacy table not found: jx_categories');

            return;
        }

        $rows = $this->legacyConnection()
            ->table('jx_categories')
            ->whereIn('service_type', [3, 4])
            ->orderBy('id')
            ->cursor();

        foreach ($rows as $row) {
            $sourceId = $this->normalizedInteger($this->rowValue($row, 'id'));

            if ($this->alreadyImported('jx_categories', $sourceId, 'news_articles')) {
                $skipped++;

                continue;
            }

            $translations = $this->translationsFromRow($row);

            if ($sourceId === null || $translations === []) {
                $this->reject($module, 'jx_categories', $sourceId, 'unsupported_locale', 'No usable AR/EN news title was found.');
                $this->logSkip($module, $batch, 'jx_categories', $sourceId, 'news_articles', 'Skipped legacy news row without usable translation.');
                $skipped++;

                continue;
            }

            $serviceType = $this->normalizedInteger($this->rowValue($row, 'service_type')) ?? 3;
            $category = $categories[$serviceType] ?? $categories[3];
            $titleFallback = $translations['en']['title'] ?? $translations['ar']['title'] ?? 'legacy-news-'.$sourceId;
            $photoPath = $this->cleanedString($row, 'photo');

            try {
                DB::transaction(function () use ($row, $module, $batch, $sourceId, $serviceType, $category, $translations, $titleFallback, $photoPath, &$imported): void {
                    $article = NewsArticle::query()->create([
                        'news_category_id' => $category->getKey(),
                        'cover_media_id' => $this->legacyMediaAssetId($photoPath, 'news/images', $translations['ar']['title'] ?? null, $translations['en']['title'] ?? null),
                        'slug' => $this->uniqueSlug($titleFallback, $sourceId),
                        'status' => $this->normalizedLegacyVisibility($row) ? 'published' : 'draft',
                        'published_at' => $this->dateNormalizer()->normalize($this->rowValue($row, ['start_date', 'end_date']))?->toDateTimeString(),
                        'scheduled_at' => null,
                        'is_enabled' => $this->normalizedLegacyVisibility($row),
                        'is_featured' => false,
                        'sort_order' => $this->normalizedInteger($this->rowValue($row, 'category_order')) ?? 0,
                        'faculty_scope_slug' => $this->facultyScopeForServiceType($serviceType),
                        'legacy_source_table' => 'jx_categories',
                        'legacy_source_id' => $sourceId,
                        'legacy_service_type' => $serviceType,
                        'legacy_url' => $this->cleanedString($row, 'url'),
                    ]);

                    foreach ($translations as $locale => $translation) {
                        NewsArticleTranslation::query()->create([
                            'news_article_id' => $article->getKey(),
                            'locale' => $locale,
                            'title' => $translation['title'],
                            'excerpt' => $translation['excerpt'],
                            'body' => $translation['body'],
                        ]);

                        NewsArticleSeoMeta::query()->create([
                            'news_article_id' => $article->getKey(),
                            'locale' => $locale,
                            'meta_title' => $translation['title'],
                            'meta_description' => $translation['excerpt'],
                            'og_title' => $translation['title'],
                            'og_description' => $translation['excerpt'],
                            'og_image_media_id' => $article->cover_media_id,
                            'og_image_url' => null,
                            'robots' => 'index,follow',
                        ]);
                    }

                    $this->importAttachments($article, $sourceId);

                    $this->snapshotLegacyRow($module, $batch, 'jx_categories', $sourceId, null, 'news_article', null, [
                        'service_type' => $serviceType,
                        'slug' => $article->slug,
                    ]);
                    $this->migrationLogger()->log($module, $batch, 'jx_categories', $sourceId, 'news_articles', (int) $article->getKey(), 'success', 'Imported legacy news article.', ['service_type' => $serviceType]);
                    $imported++;
                });
            } catch (Throwable $e) {
                $this->logSkip($module, $batch, 'jx_categories', $sourceId, 'news_articles', 'Insert failed: '.$e->getMessage());
                $skipped++;
            }
        }

        $this->command?->info("Legacy news import complete: {$imported} imported, {$skipped} skipped.");
    }

    /** @return array<int, NewsCategory> */
    private function ensureCategories(): array
    {
        $news = $this->ensureCategory('news', 'news', 'الأخبار', 'News', 1);
        $announcements = $this->ensureCategory('announcements', 'announcement', 'الإعلانات', 'Announcements', 2);

        return [3 => $news, 4 => $announcements];
    }

    private function ensureCategory(string $slug, string $type, string $nameAr, string $nameEn, int $sortOrder): NewsCategory
    {
        $category = NewsCategory::query()->updateOrCreate(
            ['slug' => $slug],
            ['type' => $type, 'sort_order' => $sortOrder, 'is_enabled' => true],
        );

        foreach (['ar' => $nameAr, 'en' => $nameEn] as $locale => $name) {
            NewsCategoryTranslation::query()->updateOrCreate(
                ['news_category_id' => $category->getKey(), 'locale' => $locale],
                ['name' => $name, 'description' => null],
            );
        }

        return $category;
    }

    /**
     * @return array<string, array{title: string, excerpt: ?string, body: ?string}>
     */
    private function translationsFromRow(object $row): array
    {
        $translations = [];

        foreach (['ar', 'en'] as $locale) {
            $title = $this->cleanedString($row, $locale.'_name');
            $excerpt = $this->cleanedString($row, $locale.'_brief');
            $body = $this->htmlSanitizer()->sanitize($this->cleanedString($row, $locale.'_data'));

            if ($title === null || $title === '' || Str::lower($title) === 'under construction') {
                continue;
            }

            $translations[$locale] = [
                'title' => $title,
                'excerpt' => $excerpt,
                'body' => $body,
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

    private function importAttachments(NewsArticle $article, int $legacyCategoryId): void
    {
        $rows = $this->legacyConnection()
            ->table('jx_items')
            ->where('category_id', $legacyCategoryId)
            ->orderBy('item_order')
            ->orderBy('id')
            ->get();

        foreach ($rows as $row) {
            $sourceId = $this->normalizedInteger($this->rowValue($row, 'id'));
            $paths = [
                ['kind' => 'image', 'path' => $this->cleanedString($row, 'photo')],
                ['kind' => 'file', 'path' => $this->cleanedString($row, 'ar_file')],
                ['kind' => 'file', 'path' => $this->cleanedString($row, 'en_file')],
            ];

            foreach ($paths as $index => $payload) {
                $path = $payload['path'];

                if (! is_string($path) || $path === '') {
                    continue;
                }

                $mediaId = $this->legacyMediaAssetId(
                    $path,
                    $payload['kind'] === 'image' ? 'news/images' : 'news/files',
                    $this->cleanedString($row, 'ar_name'),
                    $this->cleanedString($row, 'en_name'),
                );

                NewsArticleAttachment::query()->updateOrCreate(
                    [
                        'legacy_source_table' => 'jx_items',
                        'legacy_source_id' => $sourceId,
                        'legacy_path' => $path,
                    ],
                    [
                        'news_article_id' => $article->getKey(),
                        'media_asset_id' => $mediaId,
                        'kind' => $payload['kind'],
                        'label_ar' => $this->cleanedString($row, 'ar_name'),
                        'label_en' => $this->cleanedString($row, 'en_name'),
                        'sort_order' => ($this->normalizedInteger($this->rowValue($row, 'item_order')) ?? 0) + $index,
                    ],
                );
            }
        }
    }

    private function uniqueSlug(string $source, int $sourceId): string
    {
        $source = trim($source) !== '' ? $source : 'legacy-news-'.$sourceId;

        return app(SlugServiceInterface::class)->generate($source, NewsArticle::class, 'en', null, 80);
    }

    private function facultyScopeForServiceType(int $serviceType): ?string
    {
        return null;
    }
}
