<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Contracts\Legacy\LegacyNewsImportReviewServiceInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

final class LegacyNewsImportReviewServiceTest extends TestCase
{
    use RefreshDatabase;

    private LegacyNewsImportReviewServiceInterface $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(LegacyNewsImportReviewServiceInterface::class);
    }

    public function test_review_reports_cleanup_required_for_long_legacy_news_slug(): void
    {
        $categoryId = $this->createCategory('news', 'news');
        $mediaId = $this->createMediaAsset('legacy-news.jpg');
        $articleId = $this->createArticle($categoryId, str_repeat('long-news-slug-', 7), 'published');
        $this->createTranslationsAndSeo($articleId);
        $this->createAttachment($articleId, $mediaId);
        $this->createMigrationLog($articleId);
        $this->createRejection('unsupported_locale');

        $review = $this->service->review();

        $this->assertSame('cleanup_required', $review->status);
        $this->assertSame(1, $review->categories);
        $this->assertSame(1, $review->articles);
        $this->assertSame(1, $review->legacyArticles);
        $this->assertSame(1, $review->publishedArticles);
        $this->assertSame(1, $review->newsArticles);
        $this->assertSame(0, $review->announcementArticles);
        $this->assertSame(2, $review->articleTranslations);
        $this->assertSame(2, $review->articleSeoRows);
        $this->assertSame(1, $review->attachments);
        $this->assertSame(1, $review->attachmentsWithMedia);
        $this->assertSame(1, $review->migrationLogRows);
        $this->assertSame(1, $review->migrationSuccessRows);
        $this->assertSame(1, $review->rejectionRows);
        $this->assertSame(1, $review->longSlugRows);
        $this->assertSame(0, $review->missingArabicTranslations);
        $this->assertSame(0, $review->missingEnglishTranslations);
        $this->assertSame(0, $review->missingSeoRows);
        $this->assertSame(['unsupported_locale' => 1], $review->rejectionReasonCounts);
    }

    public function test_review_blocks_when_required_article_integrity_is_missing(): void
    {
        $categoryId = $this->createCategory('announcements', 'announcement');
        $articleId = $this->createArticle($categoryId, 'announcement-with-gaps', 'draft');

        DB::table('news_article_translations')->insert([
            'news_article_id' => $articleId,
            'locale' => 'en',
            'title' => 'Announcement with gaps',
            'excerpt' => null,
            'body' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('news_article_attachments')->insert([
            'news_article_id' => $articleId,
            'media_asset_id' => null,
            'kind' => 'file',
            'legacy_path' => 'missing.pdf',
            'sort_order' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $review = $this->service->review();

        $this->assertSame('blocked', $review->status);
        $this->assertSame(1, $review->announcementArticles);
        $this->assertSame(1, $review->missingArabicTranslations);
        $this->assertSame(2, $review->missingSeoRows);
        $this->assertSame(1, $review->attachmentsWithoutMediaRows);
    }

    private function createCategory(string $slug, string $type): int
    {
        return (int) DB::table('news_categories')->insertGetId([
            'slug' => $slug,
            'type' => $type,
            'sort_order' => 1,
            'is_enabled' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function createArticle(int $categoryId, string $slug, string $status): int
    {
        return (int) DB::table('news_articles')->insertGetId([
            'news_category_id' => $categoryId,
            'slug' => $slug,
            'status' => $status,
            'published_at' => $status === 'published' ? now()->subDay() : null,
            'scheduled_at' => null,
            'is_enabled' => true,
            'is_featured' => false,
            'sort_order' => 0,
            'legacy_source_table' => 'jx_categories',
            'legacy_source_id' => 100,
            'legacy_service_type' => 3,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function createTranslationsAndSeo(int $articleId): void
    {
        foreach (['ar' => 'عنوان', 'en' => 'Title'] as $locale => $title) {
            DB::table('news_article_translations')->insert([
                'news_article_id' => $articleId,
                'locale' => $locale,
                'title' => $title,
                'excerpt' => null,
                'body' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            DB::table('news_article_seo_meta')->insert([
                'news_article_id' => $articleId,
                'locale' => $locale,
                'meta_title' => $title,
                'meta_description' => null,
                'og_title' => $title,
                'og_description' => null,
                'robots' => 'index,follow',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    private function createMediaAsset(string $filename): int
    {
        return (int) DB::table('media_assets')->insertGetId([
            'disk' => 'legacy',
            'directory' => 'news/images',
            'filename' => $filename,
            'original_name' => $filename,
            'mime_type' => 'image/jpeg',
            'extension' => 'jpg',
            'size_bytes' => 1,
            'media_type' => 'image',
            'library_scope' => 'legacy',
            'metadata_status' => 'missing',
            'path' => 'news/images/'.$filename,
            'source_path' => 'news/images/'.$filename,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function createAttachment(int $articleId, int $mediaId): void
    {
        DB::table('news_article_attachments')->insert([
            'news_article_id' => $articleId,
            'media_asset_id' => $mediaId,
            'kind' => 'image',
            'legacy_source_table' => 'jx_items',
            'legacy_source_id' => 200,
            'legacy_path' => 'legacy-news.jpg',
            'sort_order' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function createMigrationLog(int $targetId): void
    {
        DB::table('migration_logs')->insert([
            'module' => 'news',
            'batch_name' => 'news-review-test',
            'source_table' => 'jx_categories',
            'source_id' => 100,
            'target_table' => 'news_articles',
            'target_id' => $targetId,
            'status' => 'success',
            'message' => 'Imported.',
            'created_at' => now(),
        ]);
    }

    private function createRejection(string $reason): void
    {
        DB::table('migration_rejections')->insert([
            'module' => 'news',
            'source_table' => 'jx_categories',
            'source_id' => 101,
            'reason_code' => $reason,
            'reason_message' => 'Rejected.',
            'created_at' => now(),
        ]);
    }
}
