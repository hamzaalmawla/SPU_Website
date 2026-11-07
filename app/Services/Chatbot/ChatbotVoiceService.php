<?php

declare(strict_types=1);

namespace App\Services\Chatbot;

use App\Contracts\Chatbot\ChatbotFairUseServiceInterface;
use App\Contracts\Chatbot\ChatbotServiceInterface;
use App\Contracts\Chatbot\ChatbotSettingsServiceInterface;
use App\Contracts\Chatbot\ChatbotUsageServiceInterface;
use App\Contracts\Chatbot\ChatbotVoiceServiceInterface;
use App\Contracts\Chatbot\OpenAiClientInterface;
use App\DTOs\Chatbot\ChatbotAudioTranscriptDTO;
use App\DTOs\Chatbot\ChatbotSettingsDTO;
use App\DTOs\Chatbot\ChatbotSpeechDTO;
use App\DTOs\Chatbot\ChatbotSpeechRequestDTO;
use App\DTOs\Chatbot\ChatbotSpeechSynthesisRequestDTO;
use App\DTOs\Chatbot\ChatbotTranscriptionCallDTO;
use App\DTOs\Chatbot\ChatbotTranscriptionDTO;
use App\DTOs\Chatbot\ChatbotTranscriptionRequestDTO;
use App\Exceptions\ChatbotProviderException;
use App\Support\Chatbot\ConversationSigner;
use App\Support\Chatbot\SpeechText;
use App\Support\Chatbot\VoiceModels;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Speech to text and text to speech for the public assistant, on OpenAI.
 *
 * Recordings are passed straight through and never stored. Voice has its own
 * share per visitor and a site-wide daily cap (recordings plus replies read
 * aloud), so it can be switched off or limited without touching text answers.
 * Logs carry only failure kinds and statuses.
 */
final class ChatbotVoiceService implements ChatbotVoiceServiceInterface
{
    /** What transcription models write for silence or noise, not what anyone said. */
    private const SILENCE_PHRASES = [
        '/^(thank you|thanks)( (so much|very much))?( for watching)?[.!]?$/iu',
        '/^(you|bye|okay|ok)[.!]?$/iu',
        '/اشتركوا في القناة/u',
        '/ترجمة نانسي قنقر/u',
        '/^شكرا(ً)? (لكم )?(على|علي)? ?(المشاهدة|المتابعة)[.!]?$/u',
    ];

    public function __construct(
        private readonly ChatbotServiceInterface $chatbot,
        private readonly ChatbotSettingsServiceInterface $settings,
        private readonly ChatbotUsageServiceInterface $usage,
        private readonly ChatbotFairUseServiceInterface $fairUse,
        private readonly OpenAiClientInterface $openAi,
    ) {}

    public function transcribe(ChatbotTranscriptionRequestDTO $request): ChatbotTranscriptionDTO
    {
        $locale = $request->locale === 'en' ? 'en' : 'ar';
        $apiKey = $this->settings->apiKey();
        $models = VoiceModels::transcription();

        if ($apiKey === null || $models === [] || ! $this->chatbot->widget($locale)->voiceEnabled) {
            return $this->failed('unavailable', $locale);
        }

        if ($this->fairUse->isPaused($request->visitorKey, $request->clientKey)) {
            return $this->failed('refused', $locale);
        }

        $maxSeconds = max(5, (int) config('chatbot.voice.transcription.max_seconds', 60));
        if ($request->durationMs > ($maxSeconds + 2) * 1000) {
            return $this->failed(ChatbotTranscriptionDTO::STATUS_TOO_LONG, $locale);
        }

        $wait = $this->fairUse->consume('vt', [
            'm' => [60, 8],
            'h' => [3600, (int) config('chatbot.voice.limits.transcriptions_per_hour', 30)],
        ], $request->visitorKey, $request->clientKey);
        if ($wait !== null) {
            $this->usage->recordRateLimited();

            return $this->failed('limited', $locale, max(1, (int) ceil($wait / 60)));
        }

        $settings = $this->settings->get();
        if ($this->overDailyCap($settings)) {
            return $this->failed('daily_limit', $locale);
        }

        $transcript = null;
        $usedModel = $models[0];
        foreach ($models as $index => $model) {
            try {
                $transcript = $this->openAi->transcribe($this->transcriptionCall($apiKey, $model, $request));
                $usedModel = $model;

                break;
            } catch (ChatbotProviderException $exception) {
                $last = $index === count($models) - 1;
                Log::notice('Chatbot transcription failed.', ['model' => $model, 'reason' => $exception->reason, 'status' => $exception->status, 'fallback' => ! $last]);

                // A rejected key or an empty account fails the same way on every model.
                if ($last || $exception->reason === ChatbotProviderException::AUTH || $exception->reason === ChatbotProviderException::QUOTA) {
                    $this->usage->recordVoiceFailure();

                    return $this->failed($exception->reason === ChatbotProviderException::RATE_LIMITED ? 'busy' : 'error', $locale);
                }
            } catch (Throwable $exception) {
                report($exception);
                $this->usage->recordVoiceFailure();

                return $this->failed('error', $locale);
            }
        }

        if (! $transcript instanceof ChatbotAudioTranscriptDTO) {
            return $this->failed('error', $locale);
        }

        $seconds = $transcript->seconds ?? min($maxSeconds, max(0, $request->durationMs) / 1000);
        $this->usage->recordTranscription($usedModel, $seconds);

        $text = $this->cleanTranscript($transcript->text);
        if ($text === '') {
            return $this->failed(ChatbotTranscriptionDTO::STATUS_EMPTY, $locale);
        }

        if (mb_strlen($text) > (int) config('chatbot.conversation.max_message_characters', 1000)) {
            return $this->failed(ChatbotTranscriptionDTO::STATUS_TOO_LONG, $locale);
        }

        return new ChatbotTranscriptionDTO(ChatbotTranscriptionDTO::STATUS_OK, $text);
    }

    public function speak(ChatbotSpeechRequestDTO $request): ChatbotSpeechDTO
    {
        $locale = $request->locale === 'en' ? 'en' : 'ar';

        // Only a reply this server wrote, unchanged, is read aloud.
        if (! ConversationSigner::verifySpeech($locale, $request->text, $request->signature)) {
            return new ChatbotSpeechDTO(ChatbotSpeechDTO::STATUS_FORBIDDEN);
        }

        $segments = SpeechText::segments(
            SpeechText::fromMarkdown($request->text),
            (int) config('chatbot.voice.speech.first_segment_characters', 200),
            (int) config('chatbot.voice.speech.segment_characters', 900),
            (int) config('chatbot.voice.speech.max_characters', 3000),
        );
        if ($request->segment < 0 || $request->segment >= count($segments)) {
            return new ChatbotSpeechDTO(ChatbotSpeechDTO::STATUS_INVALID, segments: count($segments));
        }

        $apiKey = $this->settings->apiKey();
        $model = VoiceModels::speech();
        $widget = $this->chatbot->widget($locale);
        if ($apiKey === null || $model === null || ! $widget->voiceEnabled || ! $widget->serverSpeech) {
            return $this->speechUnavailable('unavailable', count($segments));
        }

        if ($this->fairUse->isPaused($request->visitorKey, $request->clientKey)) {
            return $this->speechUnavailable('refused', count($segments));
        }

        $wait = $this->fairUse->consume('vs', [
            'm' => [60, 40],
            'h' => [3600, (int) config('chatbot.voice.limits.speech_segments_per_hour', 200)],
        ], $request->visitorKey, $request->clientKey);
        if ($wait !== null) {
            return $this->speechUnavailable('limited', count($segments));
        }

        $settings = $this->settings->get();
        // A reply counts once against the daily cap, when its reading starts.
        if ($request->segment === 0 && $this->overDailyCap($settings)) {
            return $this->speechUnavailable('busy', count($segments));
        }

        $input = $segments[$request->segment];
        $format = (string) config('chatbot.voice.speech.format', 'mp3');
        $instructions = (array) config('chatbot.voice.speech.instructions', []);

        try {
            $audio = $this->openAi->speak(new ChatbotSpeechSynthesisRequestDTO(
                apiKey: $apiKey,
                model: $model,
                voice: $settings->voiceName,
                input: $input,
                instructions: (string) ($instructions[SpeechText::isArabic($input) ? 'ar' : 'en'] ?? ''),
                format: $format,
                timeout: (int) config('chatbot.voice.speech.timeout', 30),
            ));
        } catch (ChatbotProviderException $exception) {
            Log::notice('Chatbot speech failed.', ['reason' => $exception->reason, 'status' => $exception->status]);
            $this->usage->recordVoiceFailure();

            return $this->speechUnavailable($exception->reason === ChatbotProviderException::RATE_LIMITED ? 'busy' : 'error', count($segments));
        } catch (Throwable $exception) {
            report($exception);
            $this->usage->recordVoiceFailure();

            return $this->speechUnavailable('error', count($segments));
        }

        $this->usage->recordSpeech($model, mb_strlen($input), $request->segment === 0);

        return new ChatbotSpeechDTO(
            ChatbotSpeechDTO::STATUS_OK,
            $audio,
            self::mimeType($format),
            $request->segment,
            count($segments),
        );
    }

    private function transcriptionCall(string $apiKey, string $model, ChatbotTranscriptionRequestDTO $request): ChatbotTranscriptionCallDTO
    {
        $hinted = in_array($model, (array) config('chatbot.voice.transcription.hinted_models', []), true);

        return new ChatbotTranscriptionCallDTO(
            apiKey: $apiKey,
            model: $model,
            path: $request->path,
            filename: 'question.'.self::extension($request->mimeType),
            mimeType: $request->mimeType,
            prompt: 'A visitor asks the website assistant of the Syrian Private University (الجامعة السورية الخاصة, SPU) in Damascus about admission, faculties, programs, fees, exams or student services, in Arabic or English.',
            languages: $hinted ? ['ar', 'en'] : [],
            keywords: $hinted ? array_values(array_filter((array) config('chatbot.voice.transcription.keywords', []), 'is_string')) : [],
            timeout: (int) config('chatbot.voice.transcription.timeout', 30),
        );
    }

    /**
     * The transcript as a message: invisible and control characters removed
     * (they would trip the question guard), one line, and nothing when the
     * model only wrote what it writes for silence.
     */
    private function cleanTranscript(string $text): string
    {
        $text = (string) preg_replace('/[\x{0000}-\x{0008}\x{000B}-\x{001F}\x{007F}\x{200B}-\x{200F}\x{202A}-\x{202E}\x{2060}-\x{2069}\x{FEFF}]/u', '', $text);
        $text = trim((string) preg_replace('/\s+/u', ' ', $text));

        foreach (self::SILENCE_PHRASES as $pattern) {
            if (preg_match($pattern, $text) === 1) {
                return '';
            }
        }

        return preg_match('/[\p{L}\p{N}]/u', $text) === 1 ? $text : '';
    }

    private function overDailyCap(ChatbotSettingsDTO $settings): bool
    {
        return $settings->voiceDailyLimit > 0 && $this->usage->voiceRequestsToday() >= $settings->voiceDailyLimit;
    }

    private function failed(string $status, string $locale, int $minutes = 1): ChatbotTranscriptionDTO
    {
        return new ChatbotTranscriptionDTO($status, '', (string) __('chatbot.voice.'.$status, ['minutes' => $minutes], $locale));
    }

    private function speechUnavailable(string $status, int $segments): ChatbotSpeechDTO
    {
        return new ChatbotSpeechDTO($status, segments: $segments);
    }

    private static function extension(string $mimeType): string
    {
        return match (true) {
            str_contains($mimeType, 'webm') => 'webm',
            str_contains($mimeType, 'ogg') => 'ogg',
            str_contains($mimeType, 'mp4'), str_contains($mimeType, 'm4a') => 'mp4',
            str_contains($mimeType, 'mpeg') => 'mp3',
            str_contains($mimeType, 'wav') => 'wav',
            default => 'webm',
        };
    }

    private static function mimeType(string $format): string
    {
        return match ($format) {
            'opus' => 'audio/ogg',
            'aac' => 'audio/aac',
            'flac' => 'audio/flac',
            'wav' => 'audio/wav',
            'pcm' => 'audio/L16',
            default => 'audio/mpeg',
        };
    }
}
