<?php

declare(strict_types=1);

namespace Tests\Feature\Chatbot;

use App\Contracts\Chatbot\ChatbotServiceInterface;
use App\Contracts\Chatbot\ChatbotSettingsServiceInterface;
use App\Contracts\Chatbot\ChatbotVoiceServiceInterface;
use App\DTOs\Chatbot\ChatbotLiveSessionRequestDTO;
use App\DTOs\Chatbot\ChatbotSettingsDTO;
use App\DTOs\Chatbot\ChatbotTranscriptionRequestDTO;
use App\Models\Chatbot\ChatbotUsageDay;
use App\Models\User\User;
use App\Services\Chatbot\ChatbotCaches;
use App\Support\Chatbot\ConversationSigner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Testing\TestResponse;
use InvalidArgumentException;
use Tests\TestCase;

/**
 * Spoken questions in (speech to text), answers read aloud (text to speech).
 */
final class ChatbotVoiceTest extends TestCase
{
    use ChatbotTestHelpers;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpChatbot();
    }

    public function test_a_recording_is_written_down_with_the_university_vocabulary(): void
    {
        $this->liveAssistant();

        $this->record()
            ->assertOk()
            ->assertHeader('Cache-Control', 'no-store, private')
            ->assertJsonPath('status', 'ok')
            ->assertJsonPath('text', 'What are the office hours of admission?');

        $call = $this->openAi->transcriptionRequests[0];
        $this->assertSame('gpt-transcribe', $call['model']);
        $this->assertSame('json', $call['response_format']);
        $this->assertSame(['ar', 'en'], $call['languages']);
        $this->assertContains('المفاضلة', $call['keywords']);
        $this->assertStringContainsString('Syrian Private University', $call['prompt']);
        $this->assertSame('question.webm', $call['file']['filename']);
        $this->assertGreaterThan(0, $call['file']['bytes']);

        // 4 billed seconds of gpt-transcribe at $0.0045 a minute = 300 millionths.
        $row = ChatbotUsageDay::query()->firstOrFail();
        $this->assertSame(1, $row->voice_transcriptions);
        $this->assertSame(4, $row->voice_seconds);
        $this->assertSame(300, $row->cost_micro_usd);
        $this->assertSame(0, $row->questions);
    }

    public function test_the_fallback_model_writes_it_down_when_the_key_cannot_use_the_first(): void
    {
        $this->liveAssistant();
        $this->openAi->failTranscriptionModels = ['gpt-transcribe' => 404];
        $this->openAi->transcriptionUsage = null;

        $this->record(durationMs: 6000)->assertJsonPath('status', 'ok');

        $this->assertSame(['gpt-transcribe', 'gpt-4o-mini-transcribe'], array_column($this->openAi->transcriptionRequests, 'model'));
        // The older model takes no language or keyword hints.
        $this->assertArrayNotHasKey('languages', $this->openAi->transcriptionRequests[1]);
        // Without reported usage, the browser's measured length is used: 6 s x $0.003 / min.
        $this->assertSame(300, ChatbotUsageDay::query()->value('cost_micro_usd'));
    }

    public function test_a_rejected_key_is_not_retried_and_the_visitor_is_told_plainly(): void
    {
        $this->liveAssistant();
        $this->openAi->failTranscriptionModels = ['gpt-transcribe' => 401];

        $this->record()
            ->assertJsonPath('status', 'error')
            ->assertJsonPath('message', __('chatbot.voice.error', [], 'en'));
        $this->assertCount(1, $this->openAi->transcriptionRequests);
        $this->assertSame(1, ChatbotUsageDay::query()->value('voice_failures'));
    }

    public function test_silence_and_noise_are_not_questions(): void
    {
        $this->liveAssistant();

        foreach (['Thank you for watching.', 'اشتركوا في القناة', '...', "\u{200B}"] as $heard) {
            $this->openAi->transcript = $heard;
            $this->record(locale: 'ar')
                ->assertJsonPath('status', 'empty')
                ->assertJsonPath('message', __('chatbot.voice.empty', [], 'ar'));
        }
    }

    public function test_only_short_audio_recordings_are_accepted(): void
    {
        $this->liveAssistant();

        $this->post('/en/assistant/transcriptions', ['audio' => UploadedFile::fake()->create('notes.txt', 4, 'text/plain')], ['Accept' => 'application/json'])->assertStatus(422);
        $this->post('/en/assistant/transcriptions', ['audio' => UploadedFile::fake()->create('long.webm', 4096, 'audio/webm')], ['Accept' => 'application/json'])->assertStatus(422);
        $this->record(durationMs: 75_000)->assertJsonPath('status', 'too_long');

        $this->openAi->transcript = str_repeat('Tell me about admission. ', 60);
        $this->record()->assertJsonPath('status', 'too_long');

        $this->assertCount(1, $this->openAi->transcriptionRequests);
    }

    public function test_voice_switched_off_answers_unavailable_and_hides_the_controls(): void
    {
        $user = $this->liveAssistant();
        $this->setVoice($user, enabled: false);

        $this->record()->assertJsonPath('status', 'unavailable');
        $this->assertSame([], $this->openAi->transcriptionRequests);

        $answer = $this->postJson('/en/assistant/messages', ['message' => 'Office hours?']);
        $this->speak((string) $answer->json('answer'), (string) $answer->json('speech'))->assertStatus(503)->assertJsonPath('status', 'unavailable');
    }

    public function test_a_paused_visitor_cannot_talk_either(): void
    {
        $this->liveAssistant();
        Cache::put(ChatbotCaches::pauseKey('ip', hash_hmac('sha256', '127.0.0.1', 'chatbot-client|'.(string) config('app.key'))), true, 600);

        $this->record()->assertJsonPath('status', 'refused');
        $this->assertSame([], $this->openAi->transcriptionRequests);
    }

    public function test_voice_has_its_own_share_per_visitor_and_a_daily_cap(): void
    {
        $user = $this->liveAssistant();

        $voice = app(ChatbotVoiceServiceInterface::class);
        $path = tempnam(sys_get_temp_dir(), 'voice');
        file_put_contents((string) $path, str_repeat("\x1A\x45\xDF\xA3", 64));
        $recording = static fn (string $visitor): ChatbotTranscriptionRequestDTO => new ChatbotTranscriptionRequestDTO('en', (string) $path, 'audio/webm', 256, 3000, 'campus-network', $visitor);

        foreach (range(1, 8) as $ignored) {
            $this->assertSame('ok', $voice->transcribe($recording('student-1'))->status);
        }
        $limited = $voice->transcribe($recording('student-1'));
        $this->assertSame('limited', $limited->status);
        $this->assertStringContainsString('1 minutes', $limited->message);
        // Another student on the same network keeps their own share.
        $this->assertSame('ok', $voice->transcribe($recording('student-2'))->status);

        $this->travel(2)->minutes();
        $this->setVoice($user, dailyLimit: 10);
        $this->assertSame('ok', $voice->transcribe($recording('student-1'))->status);
        $this->assertSame('daily_limit', $voice->transcribe($recording('student-1'))->status);
        $this->assertCount(10, $this->openAi->transcriptionRequests);
        @unlink((string) $path);
    }

    public function test_an_answer_is_read_aloud_segment_by_segment_as_plain_speech(): void
    {
        $this->liveAssistant();
        $this->openAi->answerWith = static fn (): string => "### Office hours\nThe admission office is open **Sunday to Thursday**, from nine to three.\n- Bring your [certificate](/en/admissions).";

        $answer = $this->postJson('/en/assistant/messages', ['message' => 'Office hours?'])->assertOk();

        $audio = $this->speak((string) $answer->json('answer'), (string) $answer->json('speech'))
            ->assertOk()
            ->assertHeader('Content-Type', 'audio/mpeg')
            ->assertHeader('Cache-Control', 'no-store, private')
            ->assertHeader('X-Speech-Segment', '0');
        $segments = (int) $audio->headers->get('X-Speech-Segments');
        $this->assertGreaterThanOrEqual(1, $segments);
        $this->assertStringStartsWith('ID3-fake-mp3:', (string) $audio->getContent());

        $call = $this->openAi->speechRequests[0];
        $this->assertSame('gpt-4o-mini-tts', $call['model']);
        $this->assertSame('cedar', $call['voice']);
        $this->assertSame('mp3', $call['response_format']);
        $this->assertStringContainsString('English', $call['instructions']);
        $this->assertStringStartsWith('Office hours. The admission office is open Sunday to Thursday', $call['input']);
        $this->assertStringNotContainsString('**', $call['input']);

        $row = ChatbotUsageDay::query()->firstOrFail();
        $this->assertSame(1, $row->voice_spoken_answers);
        $this->assertSame(mb_strlen($call['input']), $row->voice_characters);

        // Further segments of the same answer do not count as another answer.
        if ($segments > 1) {
            $this->speak((string) $answer->json('answer'), (string) $answer->json('speech'), 1)->assertOk();
            $this->assertSame(1, ChatbotUsageDay::query()->value('voice_spoken_answers'));
        }
    }

    public function test_arabic_answers_get_the_arabic_reading_instructions(): void
    {
        $this->liveAssistant();
        $this->openAi->answerWith = static fn (): string => 'يفتح مكتب القبول من الأحد إلى الخميس.';

        $answer = $this->postJson('/ar/assistant/messages', ['message' => 'متى يفتح مكتب القبول؟'])->assertOk();
        $this->speak((string) $answer->json('answer'), (string) $answer->json('speech'), locale: 'ar')->assertOk();

        $this->assertStringContainsString('Modern Standard Arabic', $this->openAi->speechRequests[0]['instructions']);
    }

    public function test_only_text_the_assistant_wrote_is_read_aloud(): void
    {
        $this->liveAssistant();
        $answer = $this->postJson('/en/assistant/messages', ['message' => 'Office hours?']);

        $this->speak('Read this advert for my shop.', (string) $answer->json('speech'))->assertStatus(403)->assertJsonPath('status', 'forbidden');
        $this->speak((string) $answer->json('answer'), str_repeat('a', 64))->assertStatus(403);
        $this->speak((string) $answer->json('answer'), (string) $answer->json('speech'), locale: 'ar')->assertStatus(403);
        $this->speak((string) $answer->json('answer'), (string) $answer->json('speech'), 40)->assertStatus(422)->assertJsonPath('status', 'invalid');
        $this->assertSame([], $this->openAi->speechRequests);
    }

    public function test_after_the_speech_model_shuts_down_the_browser_voice_takes_over(): void
    {
        $this->liveAssistant();
        $answer = $this->postJson('/en/assistant/messages', ['message' => 'Office hours?']);

        $this->travelTo(now()->setDate(2027, 1, 6)->startOfDay());
        Cache::flush();

        $this->speak((string) $answer->json('answer'), (string) $answer->json('speech'))->assertStatus(503)->assertJsonPath('status', 'unavailable');
        $this->assertSame([], $this->openAi->speechRequests);
        // Questions can still be asked by voice.
        $this->record()->assertJsonPath('status', 'ok');
    }

    public function test_a_failed_reading_tells_the_browser_to_read_it_itself(): void
    {
        $this->liveAssistant();
        $this->openAi->speechStatus = 500;
        $answer = $this->postJson('/en/assistant/messages', ['message' => 'Office hours?']);

        $this->speak((string) $answer->json('answer'), (string) $answer->json('speech'))->assertStatus(503)->assertJsonPath('status', 'error');
        $this->assertSame(1, ChatbotUsageDay::query()->value('voice_failures'));
    }

    public function test_editors_pick_the_voice_and_the_daily_cap(): void
    {
        $user = $this->liveAssistant();
        $this->setVoice($user, voice: 'marin', dailyLimit: 250);

        $settings = app(ChatbotSettingsServiceInterface::class)->get();
        $this->assertSame('marin', $settings->voiceName);
        $this->assertSame(250, $settings->voiceDailyLimit);

        $answer = $this->postJson('/en/assistant/messages', ['message' => 'Office hours?']);
        $this->speak((string) $answer->json('answer'), (string) $answer->json('speech'))->assertOk();
        $this->assertSame('marin', $this->openAi->speechRequests[0]['voice']);

        $this->expectException(InvalidArgumentException::class);
        $this->setVoice($user, voice: 'not-a-voice');
    }

    public function test_the_widget_offers_voice_only_when_it_is_on(): void
    {
        $user = $this->liveAssistant();
        $widget = app(ChatbotServiceInterface::class)->widget('en');
        $this->assertTrue($widget->voiceEnabled);
        $this->assertTrue($widget->serverSpeech);
        $this->assertSame('/en/assistant/transcriptions', $widget->transcriptionEndpoint);
        $this->assertSame('/en/assistant/speech', $widget->speechEndpoint);

        $this->setVoice($user, enabled: false);
        $widget = app(ChatbotServiceInterface::class)->widget('en');
        $this->assertFalse($widget->voiceEnabled);
        $this->assertNull($widget->transcriptionEndpoint);
    }

    public function test_live_transcription_gets_a_short_lived_transcription_only_key(): void
    {
        $this->liveAssistant();

        $this->postJson('/ar/assistant/live-session')
            ->assertOk()
            ->assertHeader('Cache-Control', 'no-store, private')
            ->assertJsonPath('status', 'ok')
            ->assertJsonPath('key', 'ek_test_1')
            ->assertJsonPath('url', 'wss://api.openai.com/v1/realtime?intent=transcription')
            ->assertJsonPath('session.type', 'transcription');

        $minted = $this->openAi->secretRequests[0];
        $this->assertSame(['anchor' => 'created_at', 'seconds' => 60], $minted['expires_after']);
        $this->assertSame('transcription', $minted['session']['type']);
        $input = $minted['session']['audio']['input'];
        $this->assertSame(['type' => 'audio/pcm', 'rate' => 24000], $input['format']);
        $this->assertSame('gpt-live-transcribe', $input['transcription']['model']);
        $this->assertSame(['ar', 'en'], $input['transcription']['languages']);
        $this->assertContains('المفاضلة', $input['transcription']['keywords']);
        $this->assertNull($input['turn_detection']);
        // A transcription session has nothing that could make it answer.
        $this->assertArrayNotHasKey('instructions', $minted['session']);
        $this->assertArrayNotHasKey('tools', $minted['session']);

        $this->assertSame(1, ChatbotUsageDay::query()->value('voice_live_sessions'));
        // About a minute of gpt-live-transcribe, as an estimate.
        $this->assertSame(17000, ChatbotUsageDay::query()->value('cost_micro_usd'));
    }

    public function test_live_transcription_follows_the_voice_rules(): void
    {
        $user = $this->liveAssistant();
        $voice = app(ChatbotVoiceServiceInterface::class);
        $ask = static fn (string $visitor) => $voice->liveSession(new ChatbotLiveSessionRequestDTO('en', 'campus', $visitor))->status;

        foreach (range(1, 4) as $ignored) {
            $this->assertSame('ok', $ask('student-1'));
        }
        $this->assertSame('limited', $ask('student-1'));
        $this->assertSame('ok', $ask('student-2'));

        Cache::put(ChatbotCaches::pauseKey('v', 'student-3'), true, 600);
        $this->assertSame('refused', $ask('student-3'));

        $this->openAi->secretStatus = 500;
        $this->assertSame('error', $ask('student-4'));

        config(['chatbot.voice.live_transcription.enabled' => false]);
        $this->assertSame('unavailable', $ask('student-5'));
        $this->assertNull(app(ChatbotServiceInterface::class)->widget('en')->liveTranscriptionEndpoint);

        config(['chatbot.voice.live_transcription.enabled' => true]);
        Cache::flush();
        $this->assertSame('/en/assistant/live-session', app(ChatbotServiceInterface::class)->widget('en')->liveTranscriptionEndpoint);
        $this->setVoice($user, enabled: false);
        $this->assertSame('unavailable', $ask('student-6'));
    }

    public function test_the_page_lets_the_browser_reach_only_the_transcription_socket(): void
    {
        $policy = (string) $this->get('/en/does-not-exist-'.uniqid())->headers->get('Content-Security-Policy');
        $this->assertMatchesRegularExpression("#connect-src 'self' wss://api\.openai\.com(;|$)#", $policy);

        config(['chatbot.voice.live_transcription.enabled' => false]);
        $policy = (string) $this->get('/en/does-not-exist-'.uniqid())->headers->get('Content-Security-Policy');
        $this->assertStringNotContainsString('openai', $policy);
    }

    public function test_speech_streams_raw_pcm_as_it_is_made_when_asked(): void
    {
        $this->liveAssistant();
        $answer = $this->postJson('/en/assistant/messages', ['message' => 'Office hours?']);

        $response = $this->postJson('/en/assistant/speech', ['text' => $answer->json('answer'), 'signature' => $answer->json('speech'), 'segment' => 0, 'stream' => true])
            ->assertOk()
            ->assertHeader('Content-Type', 'audio/L16;rate=24000;channels=1')
            ->assertHeader('X-Accel-Buffering', 'no')
            ->assertHeader('X-Speech-Segment', '0');

        $this->assertStringStartsWith('PCM-fake:', $response->streamedContent());
        $call = $this->openAi->speechRequests[0];
        $this->assertSame('pcm', $call['response_format']);
        $this->assertSame('audio', $call['stream_format']);
        $this->assertStringContainsString('lively conversational pace', $call['instructions']);
        $this->assertSame(1, ChatbotUsageDay::query()->value('voice_spoken_answers'));

        // Switched off, the same request gets one mp3 file.
        config(['chatbot.voice.speech.stream' => false]);
        $this->postJson('/en/assistant/speech', ['text' => $answer->json('answer'), 'signature' => $answer->json('speech'), 'segment' => 0, 'stream' => true])
            ->assertOk()
            ->assertHeader('Content-Type', 'audio/mpeg');
    }

    public function test_the_signature_check_ignores_how_the_browser_trims_nothing(): void
    {
        $text = 'Hello.';
        $this->assertTrue(ConversationSigner::verifySpeech('en', $text, ConversationSigner::signSpeech('en', " {$text} ")));
    }

    private function record(string $locale = 'en', int $durationMs = 3500): TestResponse
    {
        $file = UploadedFile::fake()->createWithContent('question.webm', str_repeat("\x1A\x45\xDF\xA3", 64));

        return $this->post("/{$locale}/assistant/transcriptions", ['audio' => $file, 'duration' => $durationMs], ['Accept' => 'application/json']);
    }

    private function speak(string $text, string $signature, int $segment = 0, string $locale = 'en'): TestResponse
    {
        return $this->postJson("/{$locale}/assistant/speech", ['text' => $text, 'signature' => $signature, 'segment' => $segment]);
    }

    private function setVoice(User $user, ?bool $enabled = null, ?string $voice = null, ?int $dailyLimit = null): void
    {
        $service = app(ChatbotSettingsServiceInterface::class);
        $current = $service->get();
        $service->save(new ChatbotSettingsDTO(
            enabled: $current->enabled,
            model: $current->model,
            hasApiKey: $current->hasApiKey,
            apiKeyHint: $current->apiKeyHint,
            assistantNameAr: $current->assistantNameAr,
            assistantNameEn: $current->assistantNameEn,
            welcomeAr: $current->welcomeAr,
            welcomeEn: $current->welcomeEn,
            suggestionsAr: $current->suggestionsAr,
            suggestionsEn: $current->suggestionsEn,
            extraInstructions: $current->extraInstructions,
            dailyQuestionLimit: $current->dailyQuestionLimit,
            fallbackModel: $current->fallbackModel,
            visitorHourlyLimit: $current->visitorHourlyLimit,
            visitorDailyLimit: $current->visitorDailyLimit,
            voiceEnabled: $enabled ?? $current->voiceEnabled,
            voiceName: $voice ?? $current->voiceName,
            voiceDailyLimit: $dailyLimit ?? $current->voiceDailyLimit,
        ), null, false, (int) $user->id);
    }
}
