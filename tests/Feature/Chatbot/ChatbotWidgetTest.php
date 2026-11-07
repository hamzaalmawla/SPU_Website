<?php

declare(strict_types=1);

namespace Tests\Feature\Chatbot;

use App\Contracts\Chatbot\ChatbotSettingsServiceInterface;
use App\DTOs\Chatbot\ChatbotSettingsDTO;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

final class ChatbotWidgetTest extends TestCase
{
    use ChatbotTestHelpers;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
        $this->setUpChatbot();
    }

    public function test_the_widget_is_absent_until_the_assistant_is_live(): void
    {
        $this->get('/en')->assertOk()->assertDontSee('data-chatbot', false);

        $user = $this->adminUser();
        $this->saveApiKey($user);
        $this->get('/en')->assertOk()->assertDontSee('data-chatbot', false);

        $this->addKnowledge($user, 'Guide', 'The admission office is open Sunday to Thursday from nine to three.');
        Cache::flush();

        $this->get('/en')
            ->assertOk()
            ->assertSee('data-chatbot', false)
            ->assertSee('data-endpoint="/en/assistant/messages"', false)
            ->assertSee(__('chatbot.defaults.name', [], 'en'))
            ->assertSee('aria-controls="spu-chatbot-panel"', false);

        $this->get('/ar')
            ->assertOk()
            ->assertSee('data-endpoint="/ar/assistant/messages"', false)
            ->assertSee(__('chatbot.defaults.name', [], 'ar'))
            ->assertSee(__('chatbot.widget.disclaimer', [], 'ar'));
    }

    public function test_the_voice_controls_render_only_while_voice_is_on(): void
    {
        $user = $this->liveAssistant();
        Cache::flush();

        $this->get('/ar')
            ->assertOk()
            ->assertSee('data-voice="', false)
            ->assertSee('/ar/assistant/transcriptions', false)
            ->assertSee('/ar/assistant/speech', false)
            ->assertSee('data-chatbot-mic', false)
            ->assertSee(__('chatbot.widget.speak', [], 'ar'));

        $settings = app(ChatbotSettingsServiceInterface::class);
        $current = $settings->get();
        $settings->save(new ChatbotSettingsDTO(
            enabled: true, model: $current->model, hasApiKey: true, apiKeyHint: null,
            assistantNameAr: $current->assistantNameAr, assistantNameEn: $current->assistantNameEn,
            welcomeAr: $current->welcomeAr, welcomeEn: $current->welcomeEn,
            suggestionsAr: $current->suggestionsAr, suggestionsEn: $current->suggestionsEn,
            extraInstructions: '', dailyQuestionLimit: $current->dailyQuestionLimit,
            fallbackModel: $current->fallbackModel, visitorHourlyLimit: 20, visitorDailyLimit: 40,
            voiceEnabled: false,
        ), null, false, (int) $user->id);

        $this->get('/ar')
            ->assertOk()
            ->assertSee('data-chatbot', false)
            ->assertDontSee('data-voice="', false)
            ->assertDontSee('data-chatbot-mic', false);
    }

    public function test_the_launcher_shows_from_the_first_paint_and_carries_no_secret(): void
    {
        $this->liveAssistant();
        Cache::flush();

        $html = (string) $this->get('/en')->assertOk()->getContent();

        // Shown by CSS wherever html.js is set (before the first paint), so it
        // never appears late; the widget's script is not in the page at all.
        $this->assertDoesNotMatchRegularExpression('/<div\s+class="spu-chatbot"[^>]*\shidden[\s>]/s', $html);
        $this->assertStringContainsString('html:not(.js) .spu-chatbot', (string) file_get_contents(resource_path('css/frontend/chatbot.css')));
        $this->assertStringNotContainsString('chatbotWidget', $html);
        $this->assertMatchesRegularExpression('/<section[^>]+data-chatbot-panel[^>]*\shidden/s', $html, 'The panel stays hidden until opened.');
        $this->assertStringNotContainsString('sk-test', $html);
        $this->assertStringNotContainsString('gpt-5', $html);
    }

    public function test_a_warm_page_spends_no_query_on_the_widget(): void
    {
        $this->liveAssistant();
        Cache::flush();
        $this->get('/en')->assertOk();

        DB::enableQueryLog();
        $this->withHeader('Cache-Control', 'no-cache')->get('/en/about')->assertOk();
        $queries = collect(DB::getQueryLog())->pluck('query')->implode("\n");

        $this->assertStringNotContainsString('chatbot_', $queries);
    }
}
