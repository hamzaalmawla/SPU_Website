<?php

declare(strict_types=1);

namespace App\Services\Chatbot;

use App\Contracts\Chatbot\ChatbotPassageContextWriterInterface;
use App\Contracts\Chatbot\ChatbotSettingsServiceInterface;
use App\Contracts\Chatbot\OpenAiClientInterface;
use App\DTOs\Chatbot\ChatbotCompletionDTO;
use App\DTOs\Chatbot\ChatbotCompletionRequestDTO;
use App\DTOs\Chatbot\ChatbotPassageContextsDTO;
use App\Exceptions\ChatbotProviderException;
use Illuminate\Support\Facades\Log;

/**
 * Writes each passage's context from what a reader of that passage would
 * need: the document's title and card (its summary, the same for every
 * passage, so OpenAI's prompt cache serves it), the text just before the
 * passage (where its article number and heading usually are) and the text
 * just after. Anthropic's version reads the whole document per passage; the
 * card plus a window costs a fraction of that and keeps a long decree inside
 * the free daily tier.
 *
 * `chatbot.knowledge.context_concurrency` calls at a time, temperature 0,
 * `store: false`.
 */
final class ChatbotPassageContextWriter implements ChatbotPassageContextWriterInterface
{
    public const INSTRUCTIONS = <<<'TEXT'
You help a university's search index. You are given an official document's title and summary, the text around one passage, and the passage. Write ONE or TWO short sentences (at most 45 words) that situate the passage in its document, so the passage can be found and understood on its own.

Say: which document it is from (issuer, type, number, year if given), which part (chapter, article, clause, table, section heading) and what the passage is about (the procedure, the people it applies to, the faculty or service). If the passage continues a list or clause that started before it, say which one. If the text marks the passage as an amended or replaced text, or as the text in force, say so.

Write in the document's language (Arabic stays Arabic). Use only what the given text states. Do not repeat the passage's facts or numbers, do not answer anything, do not add a heading or a label. Write the sentences only.
TEXT;

    public function __construct(
        private readonly OpenAiClientInterface $openAi,
        private readonly ChatbotSettingsServiceInterface $settings,
    ) {}

    public function write(string $title, string $card, array $passages, string $apiKey, ?array $only = null): ChatbotPassageContextsDTO
    {
        $model = trim((string) config('chatbot.knowledge.context_model', '')) ?: $this->settings->get()->model;
        $contexts = array_fill(0, count($passages), '');
        $input = $cached = $output = 0;

        $requests = [];
        foreach ($only ?? array_keys($passages) as $index) {
            if (! isset($passages[$index])) {
                continue;
            }
            $requests['p'.$index] = $this->request($model, $title, $card, $passages, $index, $apiKey);
        }

        $concurrency = max(1, (int) config('chatbot.knowledge.context_concurrency', 8));
        foreach (array_chunk($requests, $concurrency, true) as $batch) {
            foreach ($this->openAi->respondMany($batch) as $index => $outcome) {
                if ($outcome instanceof ChatbotProviderException) {
                    if (in_array($outcome->reason, [ChatbotProviderException::AUTH, ChatbotProviderException::QUOTA], true)) {
                        throw $outcome;
                    }
                    if ($outcome->reason === ChatbotProviderException::RATE_LIMITED) {
                        $outcome = $this->retryOnce($batch[$index]);
                    }
                }
                if ($outcome instanceof ChatbotCompletionDTO) {
                    $contexts[(int) substr((string) $index, 1)] = self::clean($outcome->text);
                    $input += $outcome->inputTokens;
                    $cached += $outcome->cachedInputTokens;
                    $output += $outcome->outputTokens;
                } else {
                    Log::notice('Chatbot passage context not written.', ['reason' => $outcome->reason, 'status' => $outcome->status]);
                }
            }
        }

        return new ChatbotPassageContextsDTO($contexts, $model, $input, $cached, $output);
    }

    /**
     * One or two plain sentences: no label, no quotes, no line breaks.
     */
    public static function clean(string $text): string
    {
        $text = trim((string) preg_replace('/\s+/u', ' ', $text));
        $text = (string) preg_replace('/^(?:context|السياق)\s*[:：]\s*/iu', '', $text);
        // Quotes trimmed by character, never by byte: a byte-wise trim of
        // «» or “” would cut Arabic letters that share their bytes (ث).
        $text = trim((string) preg_replace('/^["\'«»“”]+|["\'«»“”]+$/u', '', $text));

        return mb_strlen($text) > 400 ? rtrim(mb_substr($text, 0, 400)).'…' : $text;
    }

    /**
     * @param  list<array{heading: ?string, content: string}>  $passages
     */
    private function request(string $model, string $title, string $card, array $passages, int $index, string $apiKey): ChatbotCompletionRequestDTO
    {
        $before = $index > 0 ? mb_substr($passages[$index - 1]['content'], -(int) config('chatbot.knowledge.context_before_characters', 1200)) : '';
        $after = isset($passages[$index + 1]) ? mb_substr($passages[$index + 1]['content'], 0, (int) config('chatbot.knowledge.context_after_characters', 500)) : '';
        $passage = $passages[$index];

        // The parts shared by every passage of the document come first, so
        // the prompt cache can serve them.
        $content = 'Document title: '.mb_substr($title, 0, 250)."\n\n"
            .'Document summary:'."\n".($card !== '' ? mb_substr($card, 0, 1500) : '(none)')."\n\n"
            .'Text just before the passage:'."\n".($before !== '' ? '…'.$before : '(start of the document)')."\n\n"
            .'Passage'.($passage['heading'] !== null && $passage['heading'] !== '' ? ' (under the heading "'.mb_substr($passage['heading'], 0, 200).'")' : '').':'."\n".$passage['content']."\n\n"
            .'Text just after the passage:'."\n".($after !== '' ? $after.'…' : '(end of the document)');

        return new ChatbotCompletionRequestDTO(
            apiKey: $apiKey,
            model: $model,
            instructions: self::INSTRUCTIONS,
            messages: [['role' => 'user', 'content' => $content]],
            maxOutputTokens: 220,
            reasoningEffort: $this->reasoningEffort($model),
            cacheKey: 'spu-assistant-context',
            timeout: 60,
            temperature: $this->temperature($model),
        );
    }

    private function retryOnce(ChatbotCompletionRequestDTO $request): ChatbotCompletionDTO|ChatbotProviderException
    {
        usleep(max(0, (int) config('chatbot.knowledge.card_retry_ms', 4000)) * 1000);

        try {
            return $this->openAi->respond($request);
        } catch (ChatbotProviderException $exception) {
            return $exception;
        }
    }

    private function reasoningEffort(string $model): ?string
    {
        // Model ids contain dots (gpt-5.4-mini), so never address them with a config dot path.
        if (! (bool) (((array) config('chatbot.models', []))[$model]['reasoning'] ?? false)) {
            return null;
        }

        $efforts = (array) config('chatbot.reasoning_effort', []);

        return (string) ($efforts[$model] ?? $efforts['default'] ?? 'minimal');
    }

    private function temperature(string $model): ?float
    {
        if (! (bool) (((array) config('chatbot.models', []))[$model]['reasoning'] ?? false)) {
            return 0.0;
        }

        return $this->reasoningEffort($model) === 'none' ? 0.0 : null;
    }
}
