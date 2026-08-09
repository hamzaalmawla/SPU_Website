<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Contracts\Cms\CmsWorkflowServiceInterface;
use App\Contracts\Media\MediaServiceInterface;
use App\Contracts\News\NewsServiceInterface;
use App\Filament\Pages\ManageNews;
use App\Models\Cms\CmsDraft;
use App\Models\Media\MediaAsset;
use App\Models\News\NewsArticle;
use App\Models\News\NewsCategory;
use App\Models\User\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

final class NewsWorkflowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);
    }

    public function test_news_index_workflow_draft_does_not_leak_until_published(): void
    {
        $news = app(NewsServiceInterface::class);
        $workflow = app(CmsWorkflowServiceInterface::class);
        $author = User::query()->where('role_slug', 'super_admin')->firstOrFail();
        $payload = $news->getEditablePayload('news.index');
        $payload['translations']['en']['heroTitle'] = 'News Published Workflow';
        $payload['translations']['ar']['heroTitle'] = 'أخبار منشورة';

        $workflow->saveDraft('news.index', $payload, (int) $author->id);

        $this->get('/en/news')
            ->assertOk()
            ->assertDontSee('News Published Workflow');

        $this->assertTrue($workflow->publish('news.index', (int) $author->id));

        $this->get('/en/news')
            ->assertOk()
            ->assertSee('News Published Workflow');
    }

    public function test_news_index_preview_renders_draft_snapshot(): void
    {
        $news = app(NewsServiceInterface::class);
        $workflow = app(CmsWorkflowServiceInterface::class);
        $author = User::query()->where('role_slug', 'super_admin')->firstOrFail();
        $payload = $news->getEditablePayload('news.index');
        $payload['translations']['en']['heroTitle'] = 'News Preview Workflow';
        $payload['translations']['ar']['heroTitle'] = 'معاينة الأخبار';

        $workflow->saveDraft('news.index', $payload, (int) $author->id);
        $preview = $workflow->preview('news.index', 'en', (int) $author->id);

        $this->get($preview->previewUrl)
            ->assertOk()
            ->assertSee('News Preview Workflow')
            ->assertSee('Preview mode');

        $this->get('/en/news')
            ->assertOk()
            ->assertDontSee('News Preview Workflow');
    }

    public function test_manage_news_uses_curated_index_editor_and_saves_payload(): void
    {
        $this->actingAs(User::query()->where('role_slug', 'super_admin')->firstOrFail(), 'web');

        $component = Livewire::test(ManageNews::class)
            ->assertSet('data.target_key', 'news.index')
            ->assertSee('مقدمة الصفحة')
            ->assertSee('أقسام الصفحة الرئيسية')
            ->assertSee('البطاقات والتسميات');

        /** @var array<string, mixed> $data */
        $data = $component->get('data');
        $heroLinks = is_array($data['en_index']['heroLinks'] ?? null) ? $data['en_index']['heroLinks'] : [];
        $heroLinks[] = [
            'id' => 'curated-news-link',
            'label' => 'Curated News Link',
        ];

        $component
            ->set('data.en_index.heroTitle', 'Curated News Landing')
            ->set('data.en_index.heroLinks', $heroLinks)
            ->call('save');

        $draft = CmsDraft::query()->where('target_key', 'news.index')->latest('id')->firstOrFail();
        $linkLabels = collect($draft->payload_json['translations']['en']['heroLinks'] ?? [])->pluck('label')->all();

        $this->assertSame('Curated News Landing', $draft->payload_json['translations']['en']['heroTitle'] ?? null);
        $this->assertContains('Curated News Link', $linkLabels);
    }

    public function test_manage_news_articles_target_has_curated_shell_editor(): void
    {
        $this->actingAs(User::query()->where('role_slug', 'super_admin')->firstOrFail(), 'web');

        Livewire::test(ManageNews::class)
            ->set('data.target_key', 'news.articles')
            ->call('loadTarget', 'news.articles')
            ->assertSee('صفحة المقالات الإخبارية')
            ->assertDontSee('Target Schema Pending');
    }

    public function test_announcements_route_takes_precedence_and_only_renders_announcements(): void
    {
        $this->createPublishedArticle('news', 'announcements', 'Colliding News Article');
        $this->createPublishedArticle('announcement', 'priority-announcement', 'Priority Announcement', true);
        $this->createPublishedArticle('announcement', 'regular-announcement', 'Regular Announcement');

        $this->get('/en/news/announcements')
            ->assertOk()
            ->assertSee('Priority Announcement')
            ->assertSee('Regular Announcement')
            ->assertDontSee('Colliding News Article');
    }

    public function test_news_uses_newest_legacy_source_order_and_excludes_announcements_from_articles(): void
    {
        $older = $this->createPublishedArticle('news', 'older-legacy-news', 'Older Legacy News');
        $older->forceFill(['legacy_source_table' => 'jx_categories', 'legacy_source_id' => 100, 'legacy_service_type' => 3])->save();
        $newer = $this->createPublishedArticle('news', 'newer-legacy-news', 'Newer Legacy News');
        $newer->forceFill(['legacy_source_table' => 'jx_categories', 'legacy_source_id' => 300, 'legacy_service_type' => 3])->save();
        $announcement = $this->createPublishedArticle('announcement', 'separate-announcement', 'Separate Announcement');
        $announcement->forceFill(['legacy_source_table' => 'jx_categories', 'legacy_source_id' => 400, 'legacy_service_type' => 4])->save();

        $listing = app(NewsServiceInterface::class)->listPublicArticles('en', ['categoryType' => 'news'], 1, 9);

        $this->assertSame(['Newer Legacy News', 'Older Legacy News'], $listing->items->pluck('title')->all());
        $this->get('/en/news/articles')
            ->assertOk()
            ->assertSeeInOrder(['Newer Legacy News', 'Older Legacy News'])
            ->assertDontSee('Separate Announcement');
    }

    public function test_homepage_news_selector_returns_published_articles_in_requested_order(): void
    {
        $first = $this->createPublishedArticle('news', 'homepage-first', 'Homepage First');
        $second = $this->createPublishedArticle('news', 'homepage-second', 'Homepage Second');
        $this->createPublishedArticle('announcement', 'homepage-announcement', 'Homepage Announcement');

        $cards = app(NewsServiceInterface::class)->getHomepageArticleCards('en', [
            (int) $second->getKey(),
            (int) $first->getKey(),
        ]);

        $this->assertSame(['Homepage Second', 'Homepage First'], $cards->pluck('title')->all());
        $this->assertSame([
            '/en/news/'.$second->getKey(),
            '/en/news/'.$first->getKey(),
        ], $cards->pluck('url')->all());
        $this->assertNotContains('Homepage Announcement', $cards->pluck('title')->all());
    }

    public function test_past_events_are_newest_first_while_upcoming_events_remain_nearest_first(): void
    {
        $news = app(NewsServiceInterface::class);
        $past = $news->listNewsEvents('en', true)->pluck('startsAt')->all();
        $upcoming = $news->listNewsEvents('en')->pluck('startsAt')->all();

        $this->assertSame(collect($past)->sortDesc()->values()->all(), $past);
        $this->assertSame(collect($upcoming)->sort()->values()->all(), $upcoming);
    }

    public function test_reference_article_query_redirects_to_the_canonical_article_url(): void
    {
        $article = $this->createPublishedArticle('news', 'news-001', 'Legacy Query Article');

        $this->get('/en/news/article?id=news-001')
            ->assertRedirect('/en/news/'.$article->id);

        $this->get('/en/news/article?id=missing')->assertNotFound();
    }

    public function test_announcements_workflow_draft_preview_and_publish_are_isolated(): void
    {
        $news = app(NewsServiceInterface::class);
        $workflow = app(CmsWorkflowServiceInterface::class);
        $author = User::query()->where('role_slug', 'super_admin')->firstOrFail();
        $payload = $news->getEditablePayload('news.announcements');
        $payload['translations']['en']['pageTitle'] = 'Published Announcement Center';
        $payload['translations']['ar']['pageTitle'] = 'مركز الإعلانات المنشور';

        $workflow->saveDraft('news.announcements', $payload, (int) $author->id);

        $this->get('/en/news/announcements')
            ->assertOk()
            ->assertDontSee('Published Announcement Center');

        $preview = $workflow->preview('news.announcements', 'en', (int) $author->id);
        $this->get($preview->previewUrl)
            ->assertOk()
            ->assertSee('Published Announcement Center')
            ->assertSee('Preview mode');

        $this->assertTrue($workflow->publish('news.announcements', (int) $author->id));

        $this->get('/en/news/announcements')
            ->assertOk()
            ->assertSee('Published Announcement Center');
    }

    public function test_manage_news_uses_curated_announcements_editor(): void
    {
        $this->actingAs(User::query()->where('role_slug', 'super_admin')->firstOrFail(), 'web');

        Livewire::test(ManageNews::class)
            ->call('loadTarget', 'news.announcements')
            ->assertSet('data.target_key', 'news.announcements')
            ->assertSee('مقدمة صفحة الإعلانات')
            ->set('data.en_target.pageTitle', 'Curated Announcement Center')
            ->call('save');

        $draft = CmsDraft::query()->where('target_key', 'news.announcements')->latest('id')->firstOrFail();

        $this->assertSame('Curated Announcement Center', $draft->payload_json['translations']['en']['pageTitle'] ?? null);
    }

    public function test_all_dedicated_event_routes_render_without_catch_all_fallback(): void
    {
        $this->get('/en/news/events')->assertOk()->assertSee('Events Calendar');
        $this->get('/en/news/events-list')
            ->assertOk()
            ->assertSee('University Events')
            ->assertSee('href="/en/news/events-list/evt-001"', false);
        $this->get('/en/news/events-list/evt-001')
            ->assertOk()
            ->assertSee('Annual Research Symposium &amp; Innovation Showcase', false)
            ->assertDontSee('Workshop on AI Tools for Academic Research')
            ->assertSee('href="/ar/news/events-list/evt-001"', false);
        $this->get('/en/news/events?month=2026-11')
            ->assertOk()
            ->assertSee('href="/en/news/events-list/evt-001"', false)
            ->assertDontSee('/en/news/events-list#evt-001', false);
        $this->get('/en/news/events-list/register?event=evt-001')
            ->assertOk()
            ->assertHeader('X-Cache', 'BYPASS')
            ->assertSee('data-event-id="evt-001"', false)
            ->assertSee('href="/ar/news/events-list/register?event=evt-001"', false);
        $this->get('/en/news/events-list/past?event=evt-past-002')->assertOk()->assertSee('Workshop on Academic Writing');
        $this->get('/en/news/events-list/evt-past-002')->assertOk()->assertSee('Workshop on Academic Writing');
        $this->get('/en/news/events-list/register?event=evt-past-002')->assertOk()->assertSee('Event Not Found');
        $this->get('/en/news/events-list/missing-event')->assertNotFound();
    }

    public function test_event_category_filter_is_functional(): void
    {
        $this->get('/en/news/events-list?category=sports')
            ->assertOk()
            ->assertSee('Student Sports Tournament')
            ->assertDontSee('Workshop on AI Tools for Academic Research');
    }

    public function test_events_catalog_draft_preview_and_publish_are_isolated(): void
    {
        $news = app(NewsServiceInterface::class);
        $workflow = app(CmsWorkflowServiceInterface::class);
        $author = User::query()->where('role_slug', 'super_admin')->firstOrFail();
        $payload = $news->getEditablePayload('news.events');
        $payload['translations']['en']['title'] = 'Published Events Catalog';
        $payload['translations']['en']['headline'] = 'Published Events Catalog';
        $payload['translations']['en']['upcoming'][0]['title'] = 'Previewed Symposium Event';

        $workflow->saveDraft('news.events', $payload, (int) $author->id);

        $this->get('/en/news/events-list')->assertOk()->assertDontSee('Previewed Symposium Event');

        $preview = $workflow->preview('news.events', 'en', (int) $author->id);
        $this->get($preview->previewUrl)
            ->assertOk()
            ->assertSee('Published Events Catalog')
            ->assertSee('Previewed Symposium Event')
            ->assertSee('Preview mode');

        $this->assertTrue($workflow->publish('news.events', (int) $author->id));
        $this->get('/en/news/events-list')->assertOk()->assertSee('Previewed Symposium Event');
    }

    public function test_manage_news_exposes_structured_events_catalog_editor(): void
    {
        $this->actingAs(User::query()->where('role_slug', 'super_admin')->firstOrFail(), 'web');

        Livewire::test(ManageNews::class)
            ->call('loadTarget', 'news.events')
            ->assertSet('data.target_key', 'news.events')
            ->assertSee('إدارة الفعاليات')
            ->assertSee('الفعاليات القادمة')
            ->assertSee('الفعاليات السابقة');
    }

    public function test_gallery_route_filter_and_pagination_are_functional(): void
    {
        $this->get('/en/news/gallery')
            ->assertOk()
            ->assertSee('Media Gallery')
            ->assertSee('data-gallery-item', false)
            ->assertSee('1 / 2');

        $this->get('/en/news/gallery?category=research')
            ->assertOk()
            ->assertSee('Research Activity')
            ->assertSee('Digital Dentistry Research')
            ->assertDontSee('Student Clubs');

        $this->get('/en/news/gallery?page=2')
            ->assertOk()
            ->assertSee('Student Activity')
            ->assertSee('Discover SPU')
            ->assertDontSee('Campus Community');
    }

    public function test_gallery_draft_preview_publish_and_media_cache_invalidation(): void
    {
        $media = $this->createGalleryMedia('Gallery Image Before Update', 'صورة المعرض قبل التحديث');
        $news = app(NewsServiceInterface::class);
        $workflow = app(CmsWorkflowServiceInterface::class);
        $mediaService = app(MediaServiceInterface::class);
        $author = User::query()->where('role_slug', 'super_admin')->firstOrFail();
        $payload = $news->getEditablePayload('news.gallery');
        $item = [
            'id' => 'cms-gallery-image',
            'mediaId' => (int) $media->id,
            'categoryId' => 'events',
            'categoryLabel' => 'Events',
            'dateLabel' => '2026',
            'featured' => true,
        ];
        $payload['translations']['en']['title'] = 'Published Media Center';
        $payload['translations']['en']['headline'] = 'Published Media Center';
        $payload['translations']['en']['items'] = [$item];
        $payload['translations']['ar']['items'] = [array_merge($item, ['categoryLabel' => 'الفعاليات'])];

        $workflow->saveDraft('news.gallery', $payload, (int) $author->id);
        $this->get('/en/news/gallery')->assertOk()->assertDontSee('Gallery Image Before Update');

        $preview = $workflow->preview('news.gallery', 'en', (int) $author->id);
        $this->get($preview->previewUrl)
            ->assertOk()
            ->assertSee('Published Media Center')
            ->assertSee('Gallery Image Before Update')
            ->assertSee('Preview mode');

        $this->assertTrue($workflow->publish('news.gallery', (int) $author->id));
        $this->get('/en/news/gallery')->assertOk()->assertSee('Gallery Image Before Update');
        $this->get('/ar/news/gallery')->assertOk()->assertSee('صورة المعرض قبل التحديث');

        $this->assertTrue($mediaService->updateMetadata((int) $media->id, [
            'title_en' => 'Gallery Image After Update',
        ], (int) $author->id));

        $this->get('/en/news/gallery')
            ->assertOk()
            ->assertSee('Gallery Image After Update')
            ->assertDontSee('Gallery Image Before Update');
    }

    public function test_gallery_publish_readiness_rejects_non_library_fallback_records(): void
    {
        $news = app(NewsServiceInterface::class);
        $workflow = app(CmsWorkflowServiceInterface::class);
        $author = User::query()->where('role_slug', 'super_admin')->firstOrFail();
        $payload = $news->getEditablePayload('news.gallery');

        $workflow->saveDraft('news.gallery', $payload, (int) $author->id);
        $readiness = $workflow->readiness('news.gallery');

        $this->assertFalse($readiness->isReady);
        $this->assertStringContainsString('Media Library image', implode(' ', $readiness->errors['ar'] ?? []));
        $this->assertStringContainsString('Media Library image', implode(' ', $readiness->errors['en'] ?? []));
    }

    public function test_manage_news_exposes_id_based_gallery_editor(): void
    {
        $this->actingAs(User::query()->where('role_slug', 'super_admin')->firstOrFail(), 'web');

        Livewire::test(ManageNews::class)
            ->call('loadTarget', 'news.gallery')
            ->assertSet('data.target_key', 'news.gallery')
            ->assertSee('معرض الوسائط')
            ->assertSee('تصنيفات المعرض')
            ->assertSee('صور المعرض');
    }

    private function createPublishedArticle(string $type, string $slug, string $title, bool $featured = false): NewsArticle
    {
        $category = NewsCategory::query()->create([
            'slug' => $type.'-'.$slug,
            'type' => $type,
            'sort_order' => 1,
            'is_enabled' => true,
        ]);
        $category->translations()->createMany([
            ['locale' => 'ar', 'name' => $type === 'announcement' ? 'إعلانات' : 'أخبار'],
            ['locale' => 'en', 'name' => $type === 'announcement' ? 'Announcements' : 'News'],
        ]);

        $article = NewsArticle::query()->create([
            'news_category_id' => $category->id,
            'slug' => $slug,
            'status' => 'published',
            'published_at' => now()->subMinute(),
            'is_enabled' => true,
            'is_featured' => $featured,
            'sort_order' => 1,
        ]);
        $article->translations()->createMany([
            ['locale' => 'ar', 'title' => $title, 'excerpt' => $title],
            ['locale' => 'en', 'title' => $title, 'excerpt' => $title],
        ]);

        return $article;
    }

    private function createGalleryMedia(string $titleEn, string $titleAr): MediaAsset
    {
        return MediaAsset::query()->create([
            'disk' => 'public',
            'directory' => 'gallery',
            'filename' => 'gallery-image.jpg',
            'original_name' => 'gallery-image.jpg',
            'mime_type' => 'image/jpeg',
            'extension' => 'jpg',
            'size_bytes' => 1024,
            'checksum' => hash('sha256', $titleEn),
            'media_type' => 'image',
            'library_scope' => 'main',
            'metadata_status' => 'reviewed',
            'width' => 1200,
            'height' => 800,
            'title_ar' => $titleAr,
            'title_en' => $titleEn,
            'alt_text_ar' => 'وصف الصورة للاختبار',
            'alt_text_en' => 'Gallery image test description',
            'path' => 'gallery/gallery-image.jpg',
        ]);
    }
}
