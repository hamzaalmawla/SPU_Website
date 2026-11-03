<?php

declare(strict_types=1);

namespace App\Services\Chatbot;

use App\Contracts\Chatbot\ChatbotKnowledgeServiceInterface;
use App\Contracts\Chatbot\ChatbotServiceInterface;
use App\Contracts\Chatbot\ChatbotSettingsServiceInterface;
use App\Contracts\Chatbot\ChatbotUsageServiceInterface;
use App\Contracts\Chatbot\OpenAiClientInterface;
use App\Contracts\Settings\SettingsServiceInterface;
use App\Contracts\Shared\CacheServiceInterface;
use App\DTOs\Chatbot\ChatbotCompletionRequestDTO;
use App\DTOs\Chatbot\ChatbotPassageDTO;
use App\DTOs\Chatbot\ChatbotQuestionDTO;
use App\DTOs\Chatbot\ChatbotReplyDTO;
use App\DTOs\Chatbot\ChatbotSettingsDTO;
use App\DTOs\Chatbot\ChatbotSourceDTO;
use App\DTOs\Chatbot\ChatbotWidgetDTO;
use App\Exceptions\ChatbotProviderException;
use App\Support\Chatbot\ChatbotPrompt;
use App\Support\SearchTextNormalizer;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Retrieval-augmented answers for the public assistant.
 *
 * One question costs one embedding call (cached per question text) and one
 * Responses call. An identical first question is answered from cache until
 * the knowledge base or the settings change.
 *
 * Logs carry the failure kind and HTTP status only, never the question, the
 * answer or the key.
 */
final class ChatbotService implements ChatbotServiceInterface
{
    public function __construct(
        private readonly ChatbotSettingsServiceInterface $settings,
        private readonly ChatbotKnowledgeServiceInterface $knowledge,
        private readonly ChatbotUsageServiceInterface $usage,
        private readonly OpenAiClientInterface $openAi,
        private readonly SettingsServiceInterface $siteSettings,
        private readonly CacheServiceInterface $cache,
    ) {}

    public function widget(string $locale): ChatbotWidgetDTO
    {
        $locale = $locale === 'en' ? 'en' : 'ar';

        /** @var array<string, mixed> $payload */
        $payload = $this->cache->remember(ChatbotCaches::widgetKey($locale), function () use ($locale): array {
            $settings = $this->settings->get();
            $contacts = $this->contacts($locale);

            return [
                'enabled' => $this->isLive($settings),
                'assistantName' => $locale === 'ar' ? $settings->assistantNameAr : $settings->assistantNameEn,
                'welcome' => $locale === 'ar' ? $settings->welcomeAr : $settings->welcomeEn,
                'suggestions' => $locale === 'ar' ? $settings->suggestionsAr : $settings->suggestionsEn,
                'phone' => $contacts['phone'],
                'email' => $contacts['email'],
            ];
        }, 3600);

        return new ChatbotWidgetDTO(
            enabled: (bool) ($payload['enabled'] ?? false),
            locale: $locale,
            assistantName: (string) ($payload['assistantName'] ?? ''),
            welcome: (string) ($payload['welcome'] ?? ''),
            suggestions: array_values(array_filter((array) ($payload['suggestions'] ?? []), 'is_string')),
            endpoint: route('public.chatbot.messages', ['locale' => $locale], false),
            maxMessageLength: (int) config('chatbot.conversation.max_message_characters', 1000),
            contactPhone: is_string($payload['phone'] ?? null) ? $payload['phone'] : null,
            contactEmail: is_string($payload['email'] ?? null) ? $payload['email'] : null,
        );
    }

    public function ask(ChatbotQuestionDTO $question): ChatbotReplyDTO
    {
        $locale = $question->locale === 'en' ? 'en' : 'ar';
        $settings = $this->settings->get();
        $apiKey = $this->settings->apiKey();

        if ($apiKey === null || (! $question->adminTest && ! $this->isLive($settings))) {
            return $this->notice(ChatbotReplyDTO::STATUS_UNAVAILABLE, 'unavailable', $locale);
        }

        if (! $question->adminTest && $settings->dailyQuestionLimit > 0 && $this->usage->questionsToday() >= $settings->dailyQuestionLimit) {
            return $this->notice(ChatbotReplyDTO::STATUS_BUSY, 'daily_limit', $locale);
        }

        $message = trim($question->message);
        $history = $this->history($question->history);
        $cacheKey = $history === [] && $question->useCache && ! $question->adminTest ? $this->answerCacheKey($settings, $locale, $message) : null;

        if ($cacheKey !== null) {
            $cached = Cache::get($cacheKey);
            if (is_array($cached) && is_string($cached['answer'] ?? null)) {
                $this->usage->recordCachedAnswer();

                return new ChatbotReplyDTO(ChatbotReplyDTO::STATUS_OK, $cached['answer'], $this->sourcesFromArray((array) ($cached['sources'] ?? [])), true);
            }
        }

        $embeddingTokens = 0;

        try {
            $retrieval = $this->knowledge->retrieve($this->retrievalQuery($message, $history), $apiKey);
            $embeddingTokens = $retrieval->embeddingTokens;

            $completion = $this->openAi->respond(new ChatbotCompletionRequestDTO(
                apiKey: $apiKey,
                model: $settings->model,
                instructions: ChatbotPrompt::instructions(
                    $locale === 'ar' ? $settings->assistantNameAr : $settings->assistantNameEn,
                    $locale,
                    $this->contacts($locale) + ['website' => (string) config('app.url')],
                    $settings->extraInstructions,
                    now()->toFormattedDateString(),
                ),
                messages: [
                    ...$history,
                    ['role' => 'user', 'content' => ChatbotPrompt::questionTurn($message, $retrieval->passages, (int) config('chatbot.retrieval.max_passage_characters', 1400))],
                ],
                maxOutputTokens: (int) config('chatbot.conversation.max_output_tokens', 1200),
                reasoningEffort: $this->reasoningEffort($settings->model),
                cacheKey: 'spu-assistant-'.$locale,
            ));
        } catch (ChatbotProviderException $exception) {
            $this->usage->recordFailure($embeddingTokens);
            Log::warning('Chatbot provider call failed.', ['reason' => $exception->reason, 'status' => $exception->status]);

            return $this->notice(
                $exception->reason === ChatbotProviderException::RATE_LIMITED ? ChatbotReplyDTO::STATUS_BUSY : ChatbotReplyDTO::STATUS_ERROR,
                $exception->reason === ChatbotProviderException::RATE_LIMITED ? 'busy' : 'error',
                $locale,
            );
        } catch (Throwable $exception) {
            $this->usage->recordFailure($embeddingTokens);
            report($exception);

            return $this->notice(ChatbotReplyDTO::STATUS_ERROR, 'error', $locale);
        }

        $this->usage->recordAnswer(
            $settings->model,
            $completion->inputTokens,
            $completion->cachedInputTokens,
            $completion->outputTokens,
            $embeddingTokens,
            $retrieval->passages === [],
        );

        $sources = $this->sources($retrieval->passages);
        $answer = $completion->text;

        if ($cacheKey !== null && ! $completion->truncated) {
            Cache::put($cacheKey, [
                'answer' => $answer,
                'sources' => array_map(static fn (ChatbotSourceDTO $source): array => ['title' => $source->title, 'pages' => $source->pages], $sources),
            ], (int) config('chatbot.conversation.answer_cache_minutes', 720) * 60);
        }

        return new ChatbotReplyDTO(ChatbotReplyDTO::STATUS_OK, $answer, $sources);
    }

    private function isLive(ChatbotSettingsDTO $settings): bool
    {
        return (bool) config('chatbot.enabled', true)
            && $settings->enabled
            && $settings->hasApiKey
            && $this->knowledge->stats()->searchableDocuments > 0;
    }

    /**
     * Follow-ups ("and the fees?") carry little meaning alone, so a short
     * question is searched together with the visitor's previous one.
     *
     * @param  list<array{role: 'user'|'assistant', content: string}>  $history
     */
    private function retrievalQuery(string $message, array $history): string
    {
        if (mb_strlen($message) >= 60) {
            return $message;
        }

        for ($index = count($history) - 1; $index >= 0; $index--) {
            if ($history[$index]['role'] === 'user') {
                return mb_substr($history[$index]['content'], 0, 300)."\n".$message;
            }
        }

        return $message;
    }

    /**
     * @param  list<array{role: 'user'|'assistant', content: string}>  $history
     * @return list<array{role: 'user'|'assistant', content: string}>
     */
    private function history(array $history): array
    {
        $maxTurns = (int) config('chatbot.conversation.max_history_turns', 6);
        $maxCharacters = (int) config('chatbot.conversation.max_history_characters', 1500);

        $clean = [];
        foreach (array_slice($history, -$maxTurns) as $turn) {
            $content = trim($turn['content']);
            if ($content !== '' && in_array($turn['role'], ['user', 'assistant'], true)) {
                $clean[] = ['role' => $turn['role'], 'content' => mb_substr($content, 0, $maxCharacters)];
            }
        }

        // The Responses API reads the conversation in order; it must not
        // start with an assistant turn.
        while ($clean !== [] && $clean[0]['role'] === 'assistant') {
            array_shift($clean);
        }

        return $clean;
    }

    /**
     * Distinct documents behind the answer, best first, with their pages.
     *
     * @param  list<ChatbotPassageDTO>  $passages
     * @return list<ChatbotSourceDTO>
     */
    private function sources(array $passages): array
    {
        $byDocument = [];
        foreach ($passages as $passage) {
            $entry = $byDocument[$passage->documentId] ?? ['title' => $passage->documentTitle, 'pages' => []];
            if ($passage->page !== null && ! in_array($passage->page, $entry['pages'], true)) {
                $entry['pages'][] = $passage->page;
            }
            $byDocument[$passage->documentId] = $entry;
        }

        return $this->sourcesFromArray(array_slice(array_values($byDocument), 0, 4));
    }

    /**
     * @param  array<mixed>  $items
     * @return list<ChatbotSourceDTO>
     */
    private function sourcesFromArray(array $items): array
    {
        $sources = [];
        foreach ($items as $item) {
            if (is_array($item) && is_string($item['title'] ?? null) && $item['title'] !== '') {
                $pages = array_values(array_filter((array) ($item['pages'] ?? []), 'is_int'));
                sort($pages);
                $sources[] = new ChatbotSourceDTO($item['title'], array_slice($pages, 0, 6));
            }
        }

        return $sources;
    }

    private function answerCacheKey(ChatbotSettingsDTO $settings, string $locale, string $message): string
    {
        return 'chatbot.answer.'.sha1(implode('|', [
            $locale,
            $settings->model,
            $this->settings->version(),
            $this->knowledge->version(),
            SearchTextNormalizer::normalize((string) preg_replace('/[^\p{L}\p{N}\s]+/u', ' ', $message)),
        ]));
    }

    private function reasoningEffort(string $model): ?string
    {
        if (! (bool) config('chatbot.models.'.$model.'.reasoning', false)) {
            return null;
        }

        $efforts = (array) config('chatbot.reasoning_effort', []);

        return (string) ($efforts[$model] ?? $efforts['default'] ?? 'minimal');
    }

    /**
     * @return array{phone: ?string, email: ?string}
     */
    private function contacts(string $locale): array
    {
        try {
            $footer = $this->siteSettings->getFooterSettings($locale);

            return ['phone' => $footer->phone, 'email' => $footer->email];
        } catch (Throwable) {
            return ['phone' => null, 'email' => null];
        }
    }

    private function notice(string $status, string $key, string $locale): ChatbotReplyDTO
    {
        $contacts = $this->contacts($locale);
        $message = (string) __('chatbot.replies.'.$key, [], $locale);
        $reach = array_values(array_filter([$contacts['phone'], $contacts['email']]));

        if ($reach !== []) {
            $message .= ' '.__('chatbot.replies.contact', ['contact' => implode(' · ', $reach)], $locale);
        }

        return new ChatbotReplyDTO($status, $message);
    }
}
