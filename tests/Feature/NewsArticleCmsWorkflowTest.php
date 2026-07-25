<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Contracts\Cms\CmsTargetRegistryInterface;
use App\Contracts\Cms\CmsWorkflowServiceInterface;
use App\Contracts\News\NewsArticleCmsServiceInterface;
use App\DTOs\Cms\CmsDraftDTO;
use App\DTOs\News\NewsArticleCmsDataDTO;
use App\Enums\PublicationStatus;
use App\Exceptions\ConflictException;
use App\Filament\Resources\NewsArticleResource\Pages\CreateNewsArticle;
use App\Filament\Resources\NewsArticleResource\Pages\EditNewsArticle;
use App\Models\Cms\CmsDraft;
use App\Models\Media\MediaAsset;
use App\Models\News\NewsArticle;
use App\Models\News\NewsCategory;
use App\Models\User\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

final class NewsArticleCmsWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private NewsArticleCmsServiceInterface $articles;

    private CmsWorkflowServiceInterface $workflow;

    private User $editor;

    private NewsCategory $category;

    protected function setUp(): void
    {
        parent::setUp();

        $this->articles = app(NewsArticleCmsServiceInterface::class);
        $this->workflow = app(CmsWorkflowServiceInterface::class);
        $this->editor = User::factory()->create(['role_slug' => 'editor', 'is_locked' => false]);
        $this->category = NewsCategory::query()->create([
            'slug' => 'news',
            'type' => 'news',
            'is_enabled' => true,
        ]);
        $this->category->translations()->createMany([
            ['locale' => 'ar', 'name' => 'أخبار'],
            ['locale' => 'en', 'name' => 'News'],
        ]);
    }

    public function test_new_article_creates_a_non_public_shell_and_dynamic_target(): void
    {
        $prepared = $this->articles->prepareDraft(
            new NewsArticleCmsDataDTO(null, $this->payload('New private article')),
            (int) $this->editor->getKey(),
        );
        $this->workflow->saveDraft((string) $prepared->targetKey, $prepared->payload, (int) $this->editor->getKey());

        $article = NewsArticle::query()->findOrFail($prepared->articleId);
        $this->assertSame('draft', $article->status);
        $this->assertFalse((bool) $article->is_enabled);
        $this->assertNull($article->published_at);
        $this->assertSame($prepared->targetKey, app(CmsTargetRegistryInterface::class)->find((string) $prepared->targetKey)?->key);
        $this->get('/en/news/'.$article->getKey())->assertNotFound();
    }

    public function test_filament_create_saves_a_private_revision_instead_of_live_content(): void
    {
        $this->actingAs($this->editor, 'web');

        Livewire::test(CreateNewsArticle::class)
            ->fillForm($this->payload('Filament private article'))
            ->call('create')
            ->assertHasNoFormErrors();

        $article = NewsArticle::query()->where('slug', 'filament-private-article')->firstOrFail();

        $this->assertSame('draft', $article->status);
        $this->assertFalse((bool) $article->is_enabled);
        $this->assertSame(0, $article->translations()->count());
        $this->assertDatabaseHas('cms_drafts', [
            'target_key' => $this->targetKey($article),
            'status' => PublicationStatus::Draft->value,
        ]);
    }

    public function test_published_article_draft_is_isolated_and_protected_preview_renders_revision(): void
    {
        $article = $this->publishedArticle('Published article');
        $targetKey = $this->targetKey($article);
        $liveBefore = $this->liveAggregate($article);
        $formData = $this->payloadFor($article, 'Private replacement');
        $formData['translations'] = array_values($formData['translations']);
        $formData['seoMeta'] = array_values($formData['seo_meta']);
        unset($formData['entity_id'], $formData['published_at'], $formData['updated_by'], $formData['seo_meta']);
        $this->actingAs($this->editor, 'web');
        Livewire::test(EditNewsArticle::class, ['record' => $article->getRouteKey()])
            ->fillForm($formData)
            ->call('save')
            ->assertHasNoFormErrors();
        $draft = CmsDraft::query()->where('target_key', $targetKey)->latest('id')->firstOrFail();

        $this->assertSame($liveBefore, $this->liveAggregate($article->fresh()));
        $this->assertDatabaseHas('cms_drafts', [
            'id' => $draft->id,
            'target_key' => $targetKey,
            'status' => PublicationStatus::Draft->value,
        ]);
        $this->get('/en/news/'.$article->getKey())
            ->assertOk()
            ->assertSee('Published article')
            ->assertDontSee('Private replacement');

        $preview = $this->workflow->preview($targetKey, 'en', (int) $this->editor->getKey());
        $this->get($preview->previewUrl)
            ->assertOk()
            ->assertSee('Private replacement')
            ->assertDontSee('Published article')
            ->assertSee('Preview mode')
            ->assertSee('noindex,nofollow', false);
    }

    public function test_explicit_publish_promotes_the_complete_snapshot_and_sanitizes_html(): void
    {
        $article = $this->publishedArticle('Published article');
        $payload = $this->payloadFor($article, 'Promoted article');
        $payload['slug'] = 'promoted-article';
        $payload['is_featured'] = true;
        $payload['translations']['en']['body'] = '<p>Safe body</p><script>alert(1)</script>';
        $payload['seo_meta'] = [
            'en' => ['locale' => 'en', 'meta_title' => 'Promoted SEO', 'robots' => 'index,follow'],
            'ar' => ['locale' => 'ar', 'meta_title' => 'تحسين منشور', 'robots' => 'index,follow'],
        ];
        $payload['attachments'] = [[
            'media_asset_id' => $this->media('article.pdf', 'application/pdf', 'pdf')->getKey(),
            'kind' => 'file',
            'label_ar' => 'ملف',
            'label_en' => 'File',
            'sort_order' => 0,
        ]];
        $prepared = $this->articles->prepareDraft(new NewsArticleCmsDataDTO((int) $article->getKey(), $payload), (int) $this->editor->getKey());
        $this->workflow->saveDraft((string) $prepared->targetKey, $prepared->payload, (int) $this->editor->getKey());

        $publisher = User::factory()->create(['role_slug' => 'editor', 'is_locked' => false]);

        $this->assertTrue($this->workflow->publish((string) $prepared->targetKey, (int) $publisher->getKey()));

        $article->refresh()->load(['translations', 'seoMeta', 'attachments']);
        $this->assertSame('published', $article->status);
        $this->assertSame('promoted-article', $article->slug);
        $this->assertTrue((bool) $article->is_featured);
        $this->assertSame((int) $publisher->getKey(), (int) $article->updated_by);
        $this->assertSame('Promoted article', $article->translations->firstWhere('locale', 'en')?->title);
        $this->assertStringContainsString('<p>Safe body</p>', (string) $article->translations->firstWhere('locale', 'en')?->body);
        $this->assertStringNotContainsString('<script', (string) $article->translations->firstWhere('locale', 'en')?->body);
        $this->assertSame('Promoted SEO', $article->seoMeta->firstWhere('locale', 'en')?->meta_title);
        $this->assertSame('File', $article->attachments->first()?->label_en);
        $this->get('/en/news/'.$article->getKey())->assertOk()->assertSee('Promoted article');
    }

    public function test_scheduled_replacement_keeps_live_article_and_due_publish_preserves_newer_draft(): void
    {
        $article = $this->publishedArticle('Current public article');
        $targetKey = $this->targetKey($article);
        $scheduled = $this->saveDraft($article, 'Scheduled replacement');
        $this->workflow->schedule($targetKey, now()->addMinute(), (int) $this->editor->getKey());

        $article->refresh();
        $this->assertSame('published', $article->status);
        $this->assertSame('Current public article', $article->translations()->where('locale', 'en')->value('title'));
        $this->get('/en/news/'.$article->getKey())->assertOk()->assertSee('Current public article');

        $newerPayload = $this->payloadFor($article, 'Newer working draft');
        $prepared = $this->articles->prepareDraft(new NewsArticleCmsDataDTO((int) $article->getKey(), $newerPayload), (int) $this->editor->getKey());
        $newer = $this->workflow->saveDraft($targetKey, $prepared->payload, (int) $this->editor->getKey(), $scheduled->version);
        CmsDraft::query()->whereKey($scheduled->id)->update(['scheduled_at' => now()->subMinute()]);

        $this->assertSame(1, $this->workflow->publishDueScheduled());
        $this->assertSame('Scheduled replacement', $article->fresh()->translations()->where('locale', 'en')->value('title'));
        $this->assertDatabaseHas('cms_drafts', ['id' => $scheduled->id, 'status' => PublicationStatus::Published->value]);
        $this->assertDatabaseHas('cms_drafts', ['id' => $newer->id, 'status' => PublicationStatus::Draft->value]);
        $this->assertSame('Newer working draft', $this->workflow->latestEditableDraftPayload($targetKey, (int) $this->editor->getKey())['translations']['en']['title'] ?? null);
    }

    public function test_matching_faculty_editor_can_draft_and_preview_but_cannot_publish(): void
    {
        $article = $this->publishedArticle('Faculty article', 'medicine');
        $facultyEditor = User::factory()->create([
            'role_slug' => 'faculty_editor',
            'faculty_scope_slug' => 'medicine',
            'is_locked' => false,
        ]);
        $payload = $this->payloadFor($article, 'Faculty private draft');
        $prepared = $this->articles->prepareDraft(new NewsArticleCmsDataDTO((int) $article->getKey(), $payload), (int) $facultyEditor->getKey());
        $this->workflow->saveDraft((string) $prepared->targetKey, $prepared->payload, (int) $facultyEditor->getKey());

        $preview = $this->workflow->preview((string) $prepared->targetKey, 'en', (int) $facultyEditor->getKey());
        $this->get($preview->previewUrl)->assertOk()->assertSee('Faculty private draft');

        $this->expectException(AuthorizationException::class);
        $this->workflow->publish((string) $prepared->targetKey, (int) $facultyEditor->getKey());
    }

    public function test_stale_expected_version_conflicts_without_changing_live_article(): void
    {
        $article = $this->publishedArticle('Published article');
        $targetKey = $this->targetKey($article);
        $this->saveDraft($article, 'First draft');

        try {
            $this->workflow->saveDraft($targetKey, $this->payloadFor($article, 'Stale draft'), (int) $this->editor->getKey(), 999);
            $this->fail('A stale News draft was accepted.');
        } catch (ConflictException $exception) {
            $this->assertSame(1, $exception->currentVersion);
        }

        $this->assertSame('Published article', $article->fresh()->translations()->where('locale', 'en')->value('title'));
        $this->assertSame(1, CmsDraft::query()->where('target_key', $targetKey)->count());
    }

    private function publishedArticle(string $title, ?string $facultyScope = null): NewsArticle
    {
        $article = NewsArticle::query()->create([
            'news_category_id' => $this->category->getKey(),
            'slug' => 'published-'.strtolower(str_replace(' ', '-', $title)),
            'status' => 'published',
            'published_at' => now()->subDay(),
            'is_enabled' => true,
            'is_featured' => false,
            'sort_order' => 1,
            'faculty_scope_slug' => $facultyScope,
            'created_by' => $this->editor->getKey(),
            'updated_by' => $this->editor->getKey(),
        ]);
        $article->translations()->createMany([
            ['locale' => 'ar', 'title' => 'مقال منشور', 'excerpt' => 'ملخص منشور', 'body' => '<p>محتوى منشور</p>'],
            ['locale' => 'en', 'title' => $title, 'excerpt' => 'Published excerpt', 'body' => '<p>Published body</p>'],
        ]);
        $article->seoMeta()->createMany([
            ['locale' => 'ar', 'meta_title' => 'تحسين منشور', 'robots' => 'index,follow'],
            ['locale' => 'en', 'meta_title' => 'Published SEO', 'robots' => 'index,follow'],
        ]);

        return $article;
    }

    private function saveDraft(NewsArticle $article, string $englishTitle): CmsDraftDTO
    {
        $prepared = $this->articles->prepareDraft(
            new NewsArticleCmsDataDTO((int) $article->getKey(), $this->payloadFor($article, $englishTitle)),
            (int) $this->editor->getKey(),
        );

        return $this->workflow->saveDraft((string) $prepared->targetKey, $prepared->payload, (int) $this->editor->getKey());
    }

    /** @return array<string, mixed> */
    private function payloadFor(NewsArticle $article, string $englishTitle): array
    {
        $payload = $this->articles->getStoredData($this->targetKey($article))?->payload ?? [];
        $payload['translations']['en']['title'] = $englishTitle;
        $payload['translations']['en']['body'] = '<p>'.$englishTitle.' body</p>';

        return $payload;
    }

    /** @return array<string, mixed> */
    private function payload(string $englishTitle): array
    {
        return [
            'news_category_id' => (int) $this->category->getKey(),
            'slug' => '',
            'is_enabled' => true,
            'is_featured' => false,
            'sort_order' => 0,
            'translations' => [
                ['locale' => 'ar', 'title' => 'مقال عربي', 'excerpt' => 'ملخص عربي', 'body' => '<p>محتوى عربي</p>'],
                ['locale' => 'en', 'title' => $englishTitle, 'excerpt' => 'English excerpt', 'body' => '<p>English body</p>'],
            ],
            'attachments' => [],
            'seoMeta' => [
                ['locale' => 'ar', 'robots' => 'index,follow'],
                ['locale' => 'en', 'robots' => 'index,follow'],
            ],
        ];
    }

    /** @return array<string, mixed> */
    private function liveAggregate(NewsArticle $article): array
    {
        $article->load(['translations', 'seoMeta', 'attachments']);

        $parent = $article->only([
            'news_category_id', 'cover_media_id', 'slug', 'status', 'published_at', 'scheduled_at',
            'is_enabled', 'is_featured', 'sort_order', 'faculty_scope_slug', 'updated_by', 'updated_at',
        ]);
        $parent['published_at'] = $article->published_at?->toIso8601String();
        $parent['scheduled_at'] = $article->scheduled_at?->toIso8601String();
        $parent['updated_at'] = $article->updated_at?->toIso8601String();

        return [
            'parent' => $parent,
            'translations' => $article->translations->map->only(['locale', 'title', 'excerpt', 'body'])->all(),
            'seo' => $article->seoMeta->map->only(['locale', 'meta_title', 'meta_description', 'robots'])->all(),
            'attachments' => $article->attachments->map->only(['media_asset_id', 'kind', 'label_ar', 'label_en'])->all(),
        ];
    }

    private function targetKey(NewsArticle $article): string
    {
        return 'entity.news-article.'.$article->getKey();
    }

    private function media(string $filename, string $mimeType, string $mediaType): MediaAsset
    {
        $path = 'news/'.$filename;

        return MediaAsset::query()->create([
            'disk' => 'public',
            'directory' => 'news',
            'filename' => $filename,
            'original_name' => $filename,
            'mime_type' => $mimeType,
            'extension' => pathinfo($filename, PATHINFO_EXTENSION),
            'size_bytes' => 100,
            'checksum' => hash('sha256', $path),
            'media_type' => $mediaType,
            'library_scope' => 'main',
            'metadata_status' => 'reviewed',
            'path' => $path,
        ]);
    }
}
