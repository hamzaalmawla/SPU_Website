<?php

declare(strict_types=1);

namespace Tests\Feature\Chatbot;

use App\Contracts\Chatbot\ChatbotKnowledgeServiceInterface;
use App\Contracts\Chatbot\ChatbotSettingsServiceInterface;
use App\Contracts\Chatbot\ChatbotUsageServiceInterface;
use App\Filament\Pages\ManageChatbot;
use App\Models\Chatbot\ChatbotDocument;
use App\Models\Chatbot\ChatbotSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Livewire\Livewire;
use Tests\TestCase;

final class ManageChatbotPageTest extends TestCase
{
    use ChatbotTestHelpers;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpChatbot();
    }

    public function test_only_holders_of_the_permission_open_the_page(): void
    {
        $this->actingAs($this->adminUser(['editor']))->get('/admin/ai-assistant')->assertForbidden();

        $viewer = $this->adminUser(['editor'], ['chatbot.view']);
        $this->actingAs($viewer)->get('/admin/ai-assistant')
            ->assertOk()
            ->assertSee(__('admin_chatbot.not_live'))
            ->assertSee(__('admin_chatbot.reasons.no_key'))
            ->assertSee(__('admin_chatbot.table.empty_title'));

        Livewire::actingAs($viewer)->test(ManageChatbot::class)
            ->assertActionHidden('uploadDocuments')
            ->assertActionHidden('editSettings')
            ->assertActionHidden('testAssistant');
    }

    public function test_the_usage_panel_shows_how_much_input_the_prompt_cache_served(): void
    {
        $admin = $this->liveAssistant();

        $this->actingAs($admin)->get('/admin/ai-assistant')->assertOk()->assertDontSee(__('admin_chatbot.stats.prompt_cache_help'));

        // One answer: 1,200 input tokens, 200 of them from OpenAI's cache, and
        // the search rewrite: 300 more, none cached.
        auth()->logout();
        $this->postJson('/en/assistant/messages', ['message' => 'Office hours?'])->assertOk();
        // The rewrite's cached tokens count too, at the cached price.
        app(ChatbotUsageServiceInterface::class)->recordModelUse('gpt-5.4-mini', 1000, 40, 800);

        $usage = app(ChatbotUsageServiceInterface::class)->summary();
        // (200 + 800) cached of (1,200 + 300 + 1,000) input.
        $this->assertSame(40, $usage->promptCacheRateToday);
        $this->assertSame(40, $usage->promptCacheRate30Days);
        $this->assertSame(1000, $usage->cachedInputTokens30Days);

        $this->actingAs($admin)->get('/admin/ai-assistant')
            ->assertOk()
            ->assertSee(__('admin_chatbot.stats.prompt_cache_hint', ['today' => '40%', 'month' => '40%', 'tokens' => '1,000']));
    }

    public function test_settings_save_the_key_encrypted_and_never_show_it(): void
    {
        $admin = $this->adminUser();

        Livewire::actingAs($admin)->test(ManageChatbot::class)
            ->callAction('editSettings', data: [
                'enabled' => true,
                'model' => 'gpt-4.1-nano',
                'fallback_model' => 'gpt-6-luna',
                'api_key' => 'sk-proj-abcdefghijklmnopqrstuvwxyz0123',
                'remove_api_key' => false,
                'assistant_name_ar' => 'سبو',
                'assistant_name_en' => 'Spoo',
                'welcome_ar' => 'أهلاً',
                'welcome_en' => 'Hello',
                'suggestions_ar' => ['سؤال'],
                'suggestions_en' => ['Question'],
                'extra_instructions' => '',
                'daily_question_limit' => 500,
            ])
            ->assertHasNoActionErrors();

        $stored = (string) ChatbotSetting::query()->where('key', 'api_key')->value('value');
        $this->assertNotSame('', $stored);
        $this->assertStringNotContainsString('abcdefghijklmnop', $stored);

        $settings = app(ChatbotSettingsServiceInterface::class);
        $this->assertSame('sk-proj-abcdefghijklmnopqrstuvwxyz0123', $settings->apiKey());
        $this->assertSame('…0123', $settings->get()->apiKeyHint);
        $this->assertSame('gpt-4.1-nano', $settings->get()->model);
        $this->assertSame(500, $settings->get()->dailyQuestionLimit);
        $this->assertSame('gpt-6-luna', $settings->get()->fallbackModel);

        $this->actingAs($admin)->get('/admin/ai-assistant')->assertOk()->assertDontSee('abcdefghijklmnop');
    }

    public function test_an_editor_uploads_tries_and_deletes_a_file(): void
    {
        $admin = $this->adminUser();
        $this->saveApiKey($admin);

        Livewire::actingAs($admin)->test(ManageChatbot::class)
            ->callAction('uploadDocuments', data: [
                'files' => [UploadedFile::fake()->createWithContent('admission-guide.txt', "Admission\nThe admission office is open Sunday to Thursday from nine to three.")],
            ])
            ->assertHasNoActionErrors()
            ->assertNotified(__('admin_chatbot.notifications.uploaded', ['count' => 1]));

        $document = ChatbotDocument::query()->firstOrFail();
        $this->assertSame('ready', $document->status);
        $this->assertSame('admission guide', $document->title);

        $this->actingAs($admin)->get('/admin/ai-assistant')
            ->assertOk()
            ->assertSee(__('admin_chatbot.live'))
            ->assertSee('admission guide');

        $page = Livewire::actingAs($admin)->test(ManageChatbot::class)
            ->callAction('testAssistant', data: ['question' => 'When is the admission office open?', 'locale' => 'en'])
            ->assertHasNoActionErrors();
        $this->assertSame('ok', $page->get('testResult')['status']);
        $this->assertSame('admission guide', $page->get('testResult')['passages'][0]['title']);

        Livewire::actingAs($admin)->test(ManageChatbot::class)
            ->callAction('renameDocument', data: ['title' => 'Admission guide 2026'], arguments: ['document' => $document->id])
            ->callAction('toggleDocument', arguments: ['document' => $document->id, 'active' => true])
            ->callAction('deleteDocument', arguments: ['document' => $document->id]);

        $this->assertSame(0, ChatbotDocument::query()->count());
    }

    public function test_a_failed_file_is_reported_and_can_be_reindexed(): void
    {
        $admin = $this->adminUser();
        $this->addKnowledge($admin, 'Notice', 'The library opens at eight in the morning on weekdays.');
        $this->saveApiKey($admin);

        $this->actingAs($admin)->get('/admin/ai-assistant')->assertSee(__('admin_chatbot.errors.no_api_key'));

        Livewire::actingAs($admin)->test(ManageChatbot::class)
            ->assertActionVisible('reindexStale')
            ->callAction('reindexStale')
            ->assertNotified(__('admin_chatbot.notifications.reindexed_all', ['count' => 1]));

        $this->assertSame('ready', app(ChatbotKnowledgeServiceInterface::class)->documents()->first()?->status);
    }

    public function test_the_documents_list_is_paged_searchable_and_filterable(): void
    {
        $admin = $this->adminUser();
        $this->saveApiKey($admin);
        foreach (range(1, 23) as $index) {
            $this->addKnowledge($admin, sprintf('Guide %02d', $index), "Section {$index}: the admission office answers question number {$index} every weekday.");
        }
        $this->addKnowledge($admin, 'Fees 100%_discount notice', 'Students who rank first get a one hundred percent discount on fees.');

        $service = app(ChatbotKnowledgeServiceInterface::class);
        $first = $service->documentsPage(1, 10);
        $this->assertSame(24, $first->total);
        $this->assertSame(3, $first->lastPage);
        $this->assertCount(10, $first->items);
        $this->assertSame('Fees 100%_discount notice', $first->items->first()->title, 'Newest first.');
        $this->assertCount(4, $service->documentsPage(3, 10)->items);
        $this->assertSame(3, $service->documentsPage(99, 10)->currentPage, 'A page past the end shows the last page.');

        // LIKE metacharacters match literally.
        $this->assertSame(1, $service->documentsPage(1, 10, '100%_')->total);
        $this->assertSame(0, $service->documentsPage(1, 10, '100%x')->total);
        $this->assertSame(10, $service->documentsPage(1, 10, 'guide 1')->total, 'Guide 10 to 19, case-insensitive.');
        $this->assertSame(4, $service->documentsPage(1, 10, 'guide 2')->total);
        $this->assertSame(24, $service->documentsPage(1, 10, null, 'text')->total);
        $this->assertSame(0, $service->documentsPage(1, 10, null, 'website')->total);
        $this->assertSame(24, $service->documentsPage(1, 10, null, null, 'ready')->total);
        $this->assertSame(0, $service->documentsPage(1, 10, null, null, 'failed')->total);

        $page = Livewire::actingAs($admin)->test(ManageChatbot::class)
            ->assertSee(__('admin_chatbot.table.showing', ['from' => 1, 'to' => 10, 'total' => 24]))
            ->assertSee('Fees 100%_discount notice')
            ->assertDontSee('Guide 01')
            ->assertSeeHtml('aria-current="page"');

        $page->call('goToDocumentsPage', 3)
            ->assertSee(__('admin_chatbot.table.showing', ['from' => 21, 'to' => 24, 'total' => 24]))
            ->assertSee('Guide 01')
            ->assertDontSee('Fees 100%_discount notice');

        $page->set('documentSearch', 'Guide 07')
            ->assertSet('documentsPage', 1)
            ->assertSee('Guide 07')
            ->assertDontSee('Guide 08')
            ->assertSee(__('admin_chatbot.table.showing', ['from' => 1, 'to' => 1, 'total' => 1]));

        $page->set('documentSearch', 'nothing like this')
            ->assertSee(__('admin_chatbot.table.no_matches'))
            ->call('clearDocumentFilters')
            ->assertSet('documentSearch', '')
            ->assertSee(__('admin_chatbot.table.showing', ['from' => 1, 'to' => 10, 'total' => 24]));

        $page->set('documentsPerPage', 25)
            ->assertSee(__('admin_chatbot.table.showing', ['from' => 1, 'to' => 24, 'total' => 24]))
            ->set('documentsPerPage', 7)
            ->assertSet('documentsPerPage', 10)
            ->set('documentType', 'bogus')
            ->assertSet('documentType', '');
    }
}
