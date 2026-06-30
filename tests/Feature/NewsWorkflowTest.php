<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Contracts\Cms\CmsWorkflowServiceInterface;
use App\Contracts\News\NewsServiceInterface;
use App\Filament\Pages\ManageNews;
use App\Models\Cms\CmsDraft;
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
            ->assertSee('Hero')
            ->assertSee('Sections')
            ->assertSee('Cards and Labels');

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

    public function test_manage_news_non_index_targets_are_pending_until_curated(): void
    {
        $this->actingAs(User::query()->where('role_slug', 'super_admin')->firstOrFail(), 'web');

        Livewire::test(ManageNews::class)
            ->set('data.target_key', 'news.articles')
            ->call('loadTarget', 'news.articles')
            ->assertSee('Target Schema Pending')
            ->assertSee('The News landing page is editable now');
    }
}
