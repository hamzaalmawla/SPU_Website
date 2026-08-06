<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Contracts\Legacy\LegacyNewsPublicationServiceInterface;
use App\Models\Cms\CmsDraft;
use App\Models\News\NewsArticle;
use App\Models\News\NewsCategory;
use App\Models\Shared\MigrationLog;
use App\Models\User\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\TestCase;

final class LegacyNewsPublicationServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_publication_is_dry_run_first_gated_replay_safe_and_uses_cms_workflow(): void
    {
        $editor = User::factory()->create(['role_slug' => 'editor', 'is_locked' => false]);
        $article = $this->importedArticle(7001);
        $service = app(LegacyNewsPublicationServiceInterface::class);

        $dryRun = $service->publish([7001], [7001], (int) $editor->getKey(), batch: 'demo-news');

        $this->assertFalse($dryRun->written);
        $this->assertSame(1, $dryRun->eligibleRows);
        $this->assertSame('draft', $article->fresh()->status);

        try {
            $service->publish([7001], [7001], (int) $editor->getKey(), true, 'wrong-token', 'demo-news');
            $this->fail('The publication token should be required.');
        } catch (InvalidArgumentException $exception) {
            $this->assertStringContainsString('publish-legacy-news', $exception->getMessage());
        }

        $written = $service->publish([7001], [7001], (int) $editor->getKey(), true, 'publish-legacy-news', 'demo-news');

        $article->refresh();
        $this->assertSame(1, $written->publishedRows);
        $this->assertSame('published', $article->status);
        $this->assertTrue((bool) $article->is_enabled);
        $this->assertTrue((bool) $article->is_featured);
        $this->assertNotNull($article->published_at);
        $this->assertDatabaseHas('migration_logs', [
            'module' => 'news_publication',
            'batch_name' => 'demo-news',
            'source_id' => 7001,
            'target_id' => $article->getKey(),
            'status' => 'success',
        ]);
        $this->assertSame('published', CmsDraft::query()->where('target_key', 'entity.news-article.'.$article->getKey())->value('status'));

        $replay = $service->publish([7001], [7001], (int) $editor->getKey(), true, 'publish-legacy-news', 'demo-news');

        $this->assertSame(0, $replay->publishedRows);
        $this->assertSame(1, $replay->alreadyPublishedRows);
        $this->assertSame(1, MigrationLog::query()->where('module', 'news_publication')->where('source_id', 7001)->count());
    }

    public function test_publication_blocks_unresolved_legacy_attachments(): void
    {
        $editor = User::factory()->create(['role_slug' => 'editor', 'is_locked' => false]);
        $article = $this->importedArticle(7002);
        $article->attachments()->create([
            'kind' => 'file',
            'legacy_source_table' => 'jx_items',
            'legacy_source_id' => 44,
            'legacy_path' => 'files/unavailable.pdf',
        ]);

        $result = app(LegacyNewsPublicationServiceInterface::class)->publish(
            [7002],
            [],
            (int) $editor->getKey(),
            true,
            'publish-legacy-news',
            'blocked-news',
        );

        $this->assertSame(0, $result->publishedRows);
        $this->assertSame(1, $result->blockedRows);
        $this->assertSame(['unresolved_attachments' => 1], $result->blockReasonCounts);
        $this->assertSame('draft', $article->fresh()->status);
    }

    public function test_publication_can_retain_deferred_media_without_rendering_a_broken_link(): void
    {
        $editor = User::factory()->create(['role_slug' => 'editor', 'is_locked' => false]);
        $article = $this->importedArticle(7004);
        $article->attachments()->create([
            'kind' => 'file',
            'legacy_source_table' => 'jx_items',
            'legacy_source_id' => 45,
            'legacy_path' => 'files/deferred.pdf',
            'label_en' => 'Deferred file',
        ]);

        $result = app(LegacyNewsPublicationServiceInterface::class)->publish(
            [7004],
            [],
            (int) $editor->getKey(),
            true,
            'publish-legacy-news',
            'deferred-media-news',
            true,
        );

        $this->assertSame(1, $result->publishedRows);
        $this->assertSame('published', $article->fresh()->status);
        $this->get('/en/news/'.$article->getKey())
            ->assertOk()
            ->assertDontSee('href=""', false)
            ->assertSee('href="/files/deferred.pdf"', false)
            ->assertSee('Deferred file')
            ->assertSee('Attachments');
    }

    public function test_publication_allows_approved_arabic_source_fallback_without_synthesizing_english(): void
    {
        $editor = User::factory()->create(['role_slug' => 'editor', 'is_locked' => false]);
        $article = $this->importedArticle(7003);
        $article->translations()->where('locale', 'en')->delete();
        $article->seoMeta()->where('locale', 'en')->delete();

        $result = app(LegacyNewsPublicationServiceInterface::class)->publish(
            [7003],
            [],
            (int) $editor->getKey(),
            true,
            'publish-legacy-news',
            'arabic-fallback-news',
        );

        $this->assertSame(1, $result->publishedRows);
        $this->assertSame(1, $article->fresh()->translations()->count());
        $this->get('/en/news/'.$article->getKey())
            ->assertOk()
            ->assertSee('خبر موثق')
            ->assertSee('محتوى عربي موثق');
    }

    private function importedArticle(int $sourceId): NewsArticle
    {
        $category = NewsCategory::query()->firstOrCreate(
            ['slug' => 'news'],
            ['type' => 'news', 'is_enabled' => true],
        );
        if (! $category->translations()->exists()) {
            $category->translations()->createMany([
                ['locale' => 'ar', 'name' => 'الأخبار'],
                ['locale' => 'en', 'name' => 'News'],
            ]);
        }
        $article = NewsArticle::query()->create([
            'news_category_id' => $category->getKey(),
            'slug' => 'legacy-news-'.$sourceId,
            'status' => 'draft',
            'is_enabled' => false,
            'is_featured' => false,
            'legacy_source_table' => 'jx_categories',
            'legacy_source_id' => $sourceId,
            'legacy_service_type' => 3,
        ]);
        $article->translations()->createMany([
            ['locale' => 'ar', 'title' => 'خبر موثق', 'body' => '<p>محتوى عربي موثق.</p>'],
            ['locale' => 'en', 'title' => 'Verified news', 'body' => '<p>Verified English content.</p>'],
        ]);
        $article->seoMeta()->createMany([
            ['locale' => 'ar', 'robots' => 'noindex,nofollow'],
            ['locale' => 'en', 'robots' => 'noindex,nofollow'],
        ]);
        MigrationLog::query()->create([
            'module' => 'news',
            'batch_name' => 'approved-import',
            'source_table' => 'jx_categories',
            'source_id' => $sourceId,
            'target_table' => 'news_articles',
            'target_id' => $article->getKey(),
            'status' => 'success',
        ]);

        return $article;
    }
}
