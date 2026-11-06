<?php

declare(strict_types=1);

namespace App\Services\Chatbot;

use App\Contracts\Chatbot\ChatbotDocumentCardWriterInterface;
use App\Contracts\Chatbot\ChatbotSettingsServiceInterface;
use App\Contracts\Chatbot\OpenAiClientInterface;
use App\DTOs\Chatbot\ChatbotCompletionDTO;
use App\DTOs\Chatbot\ChatbotCompletionRequestDTO;
use App\DTOs\Chatbot\ChatbotDocumentCardDTO;
use App\Exceptions\ChatbotProviderException;
use App\Support\Chatbot\DocumentTextExtractor;
use Illuminate\Support\Facades\Log;

/**
 * Why a card: an announcement's deadline usually sits in a passage that says
 * "this announcement" and never names it ("على الراغبين بالتقدم إلى هذا
 * الإعلان ... اعتباراً من 2026/10/19"), so a question naming the competition
 * matches other passages first, and the model may then take another
 * document's dates. The card puts the document's name and its key facts in
 * one passage.
 *
 * Written by the reading model (chatbot.ocr.model, falling back to the main
 * model), from the start and the end of the text (excerpt()), `store: false`.
 */
final class ChatbotDocumentCardWriter implements ChatbotDocumentCardWriterInterface
{
    private const INSTRUCTIONS = <<<'TEXT'
You write the "document card" of an official document for a university's search index. The card is a short summary that is shown to the university's assistant together with the document's other passages, so it must be exact.

Write in the document's own language (Arabic stays Arabic). At most 12 short lines, about 900 characters:
- Line 1: what the document is: issuer, type and number if any, subject, academic year.
- Then the key facts, copied exactly from the text, each with what it refers to: application dates and deadlines, fees and amounts, who it applies to, how and where to apply, the main conditions, links and contacts.
- If the document is mainly a data table (minimum averages, lists of programmes), say what it lists, for which procedure and year, and its columns; do not copy its rows.

Use only what the text states. Copy numbers, dates and names exactly; never compute, convert, interpret or add anything. No heading, no introduction, no closing remark.
TEXT;

    public function __construct(
        private readonly OpenAiClientInterface $openAi,
        private readonly ChatbotSettingsServiceInterface $settings,
    ) {}

    public function write(string $title, string $text, string $apiKey): ?ChatbotDocumentCardDTO
    {
        $text = self::excerpt($text);
        $main = $this->settings->get()->model;
        $configured = trim((string) config('chatbot.knowledge.card_model', ''));
        $models = array_values(array_unique(array_filter([$configured !== '' ? $configured : trim((string) config('chatbot.ocr.model', '')), $main])));

        foreach ($models as $model) {
            try {
                $completion = $this->respondWithRetry(new ChatbotCompletionRequestDTO(
                    apiKey: $apiKey,
                    model: $model,
                    instructions: self::INSTRUCTIONS,
                    messages: [['role' => 'user', 'content' => 'Document title: '.mb_substr($title, 0, 250)."\n\nDocument text:\n".$text]],
                    maxOutputTokens: 1500,
                    reasoningEffort: $this->reasoningEffort($model),
                    timeout: max(10, (int) config('chatbot.ocr.timeout', 120)),
                ));
            } catch (ChatbotProviderException $exception) {
                if (in_array($exception->reason, [ChatbotProviderException::AUTH, ChatbotProviderException::QUOTA], true)) {
                    throw $exception;
                }
                Log::warning('Chatbot document card not written.', ['model' => $model, 'reason' => $exception->reason, 'status' => $exception->status]);

                continue;
            }

            $card = DocumentTextExtractor::clean($completion->text);
            if (mb_strlen($card) < 40) {
                return null;
            }

            return new ChatbotDocumentCardDTO(mb_substr($card, 0, 1500), $model, $completion->inputTokens, $completion->outputTokens);
        }

        return null;
    }

    /**
     * The start of the document and its end: announcements put their dates
     * and how to apply at the end ("التعليمات العامة"), tables say what they
     * list at the top. Keeps a card to a few thousand tokens, inside a new
     * key's per-minute limits, whatever the file's size.
     */
    public static function excerpt(string $text): string
    {
        $head = max(2000, (int) config('chatbot.knowledge.card_head_characters', 24_000));
        $tail = max(0, (int) config('chatbot.knowledge.card_tail_characters', 8_000));

        if (mb_strlen($text) <= $head + $tail) {
            return $text;
        }

        return mb_substr($text, 0, $head)."\n\n[…]\n\n".($tail > 0 ? mb_substr($text, -$tail) : '');
    }

    /**
     * One more try after a short wait when the account's rate limit is hit
     * (re-indexing several files in a row does that on a new key).
     */
    private function respondWithRetry(ChatbotCompletionRequestDTO $request): ChatbotCompletionDTO
    {
        try {
            return $this->openAi->respond($request);
        } catch (ChatbotProviderException $exception) {
            if ($exception->reason !== ChatbotProviderException::RATE_LIMITED) {
                throw $exception;
            }
            usleep(max(0, (int) config('chatbot.knowledge.card_retry_ms', 4000)) * 1000);

            return $this->openAi->respond($request);
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
}
