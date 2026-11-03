<?php

declare(strict_types=1);

namespace Tests\Feature\Chatbot;

use App\Contracts\Chatbot\ChatbotKnowledgeServiceInterface;
use App\Contracts\Chatbot\ChatbotSettingsServiceInterface;
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

    public function test_settings_save_the_key_encrypted_and_never_show_it(): void
    {
        $admin = $this->adminUser();

        Livewire::actingAs($admin)->test(ManageChatbot::class)
            ->callAction('editSettings', data: [
                'enabled' => true,
                'model' => 'gpt-4.1-nano',
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
}
