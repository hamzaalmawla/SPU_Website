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
use App\DTOs\Chatbot\ChatbotCompletionDTO;
use App\DTOs\Chatbot\ChatbotCompletionRequestDTO;
use App\DTOs\Chatbot\ChatbotPassageDTO;
use App\DTOs\Chatbot\ChatbotQuestionDTO;
use App\DTOs\Chatbot\ChatbotReplyDTO;
use App\DTOs\Chatbot\ChatbotSettingsDTO;
use App\DTOs\Chatbot\ChatbotSourceDTO;
use App\DTOs\Chatbot\ChatbotWidgetDTO;
use App\Exceptions\ChatbotProviderException;
use App\Support\Chatbot\ChatbotGuard;
use App\Support\Chatbot\ChatbotPrompt;
use App\Support\Chatbot\ConversationSigner;
use App\Support\SearchTextNormalizer;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
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

        // A visitor or address that kept asking for things the assistant does
        // not do is paused before anything else runs, so it costs nothing.
        if (! $question->adminTest && $this->isPaused($question)) {
            return $this->notice(ChatbotReplyDTO::STATUS_REFUSED, 'blocked', $locale, false);
        }

        // Fair use: each visitor gets a share per minute, hour and day.
        if (! $question->adminTest) {
            $wait = $this->fairUseWait($question, $this->settings->get());
            if ($wait !== null) {
                $this->usage->recordRateLimited();
                $reply = $this->notice(ChatbotReplyDTO::STATUS_LIMITED, 'limited', $locale, false);

                return new ChatbotReplyDTO($reply->status, str_replace(':minutes', (string) max(1, (int) ceil($wait / 60)), $reply->answer));
            }
        }

        $message = trim($question->message);

        // Layer 1: deterministic screen (code, rule-bypass, obfuscation). Free.
        $reason = ChatbotGuard::inspectQuestion($message);
        if ($reason !== null) {
            return $this->refuse($question, $locale, $reason);
        }

        // The same question over and over is someone playing, not asking.
        if (! $question->adminTest && $this->isRepeated($question, $message)) {
            return $this->refuse($question, $locale, 'spam');
        }

        if (! $question->adminTest && $settings->dailyQuestionLimit > 0 && $this->usage->questionsToday() >= $settings->dailyQuestionLimit) {
            return $this->notice(ChatbotReplyDTO::STATUS_BUSY, 'daily_limit', $locale);
        }

        $history = $this->history($question->history, $locale);
        $cacheKey = $history === [] && $question->useCache && ! $question->adminTest ? $this->answerCacheKey($settings, $locale, $message) : null;

        if ($cacheKey !== null) {
            $cached = Cache::get($cacheKey);
            if (is_array($cached) && is_string($cached['answer'] ?? null)) {
                $this->usage->recordCachedAnswer();

                return new ChatbotReplyDTO(
                    ChatbotReplyDTO::STATUS_OK,
                    $cached['answer'],
                    $this->sourcesFromArray((array) ($cached['sources'] ?? [])),
                    true,
                    ConversationSigner::sign($locale, $message, $cached['answer']),
                );
            }
        }

        // Personal data is never needed to answer and never leaves the site.
        $outgoing = ChatbotGuard::redactPersonalData($message);
        $embeddingTokens = 0;

        try {
            // Layer 2: OpenAI's free moderation check for harmful content. An
            // outage here does not stop answers: the other layers still apply.
            if ((bool) config('chatbot.guard.moderation', true) && $this->flagged($outgoing, $apiKey)) {
                return $this->refuse($question, $locale, 'unsafe');
            }

            $retrieval = $this->knowledge->retrieve($this->retrievalQuery($outgoing, $history), $apiKey, $locale);
            $embeddingTokens = $retrieval->embeddingTokens;

            // Layer 3: the model classifies the message before answering
            // (structured output), so an out-of-scope request comes back as a
            // category, never as text shown to the visitor.
            $request = new ChatbotCompletionRequestDTO(
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
                    ...array_map(static fn (array $turn): array => ['role' => $turn['role'], 'content' => ChatbotGuard::redactPersonalData($turn['content'])], $history),
                    ['role' => 'user', 'content' => ChatbotPrompt::questionTurn($outgoing, $retrieval->passages, (int) config('chatbot.retrieval.max_passage_characters', 1400))],
                ],
                maxOutputTokens: (int) config('chatbot.conversation.max_output_tokens', 1200),
                reasoningEffort: $this->reasoningEffort($settings->model),
                cacheKey: 'spu-assistant-'.$locale,
                jsonSchema: ChatbotPrompt::schema(),
            );
            [$completion, $model] = $this->respondWithFallback($request, $settings->fallbackModel);
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
            $model,
            $completion->inputTokens,
            $completion->cachedInputTokens,
            $completion->outputTokens,
            $embeddingTokens,
            $retrieval->passages === [],
        );

        $reply = ChatbotPrompt::parseReply($completion->text);
        if ($reply === null || ($completion->truncated && $reply['answer'] === '')) {
            Log::warning('Chatbot reply was not valid structured output.', ['truncated' => $completion->truncated]);

            return $this->notice(ChatbotReplyDTO::STATUS_ERROR, 'error', $locale);
        }

        if (! in_array($reply['category'], ChatbotPrompt::ANSWERABLE, true) || $reply['answer'] === '') {
            return $this->refuse($question, $locale, $reply['category'] === '' ? 'off_topic' : $reply['category']);
        }

        // Layer 4: whatever the model said, nothing code-like or carrying its
        // instructions reaches the page, and links stay on university hosts.
        $leak = ChatbotGuard::inspectAnswer($reply['answer']);
        if ($leak !== null) {
            Log::warning('Chatbot answer withheld by the output guard.', ['reason' => $leak]);

            return $this->refuse($question, $locale, 'answer_'.$leak);
        }

        $answer = ChatbotGuard::restrictLinks($reply['answer'], $this->linkHosts());
        $sources = $reply['category'] === 'university' ? $this->sources($retrieval->passages) : [];

        if ($cacheKey !== null && ! $completion->truncated) {
            Cache::put($cacheKey, [
                'answer' => $answer,
                'sources' => array_map(static fn (ChatbotSourceDTO $source): array => ['title' => $source->title, 'pages' => $source->pages, 'url' => $source->url], $sources),
            ], (int) config('chatbot.conversation.answer_cache_minutes', 720) * 60);
        }

        return new ChatbotReplyDTO(ChatbotReplyDTO::STATUS_OK, $answer, $sources, false, ConversationSigner::sign($locale, $message, $answer));
    }

    /**
     * Refuse politely, count it, and pause a visitor that keeps trying.
     * The visitor sees the same message whatever the reason, so probing
     * learns nothing about which layer stopped it.
     */
    private function refuse(ChatbotQuestionDTO $question, string $locale, string $reason): ChatbotReplyDTO
    {
        $this->usage->recordBlocked();

        if (! $question->adminTest) {
            // An innocent off-topic question costs one point; attacks two.
            $points = $reason === 'off_topic' ? 1 : 2;
            $window = (int) config('chatbot.guard.strike_window_minutes', 60) * 60;

            foreach ([['v', $question->visitorKey, (int) config('chatbot.guard.strikes', 4)], ['ip', $question->clientKey, (int) config('chatbot.guard.ip_strikes', 16)]] as [$scope, $key, $threshold]) {
                if ($key === null) {
                    continue;
                }

                $strikesKey = "chatbot.strikes.{$scope}.{$key}";
                $strikes = (int) Cache::get($strikesKey, 0) + $points;
                Cache::put($strikesKey, $strikes, $window);

                if ($strikes >= $threshold) {
                    $this->pause($scope, $key);
                    Cache::forget($strikesKey);
                }
            }
        }

        $reply = $this->notice(ChatbotReplyDTO::STATUS_REFUSED, 'refused', $locale, false);

        return new ChatbotReplyDTO($reply->status, $reply->answer, [], false, null, $reason);
    }

    /**
     * Pause for an hour; a second pause the same day lasts a day.
     */
    private function pause(string $scope, string $key): void
    {
        $repeatKey = "chatbot.paused-today.{$scope}.{$key}";
        $seconds = Cache::has($repeatKey)
            ? (int) config('chatbot.guard.repeat_block_hours', 24) * 3600
            : (int) config('chatbot.guard.block_minutes', 60) * 60;

        Cache::put("chatbot.paused.{$scope}.{$key}", true, $seconds);
        Cache::put($repeatKey, true, 86400);
        $this->usage->recordPaused();
        Log::notice('Chatbot paused a visitor after repeated refused questions.', ['scope' => $scope, 'key' => substr($key, 0, 12), 'minutes' => intdiv($seconds, 60)]);
    }

    private function isPaused(ChatbotQuestionDTO $question): bool
    {
        return ($question->visitorKey !== null && Cache::get('chatbot.paused.v.'.$question->visitorKey) === true)
            || ($question->clientKey !== null && Cache::get('chatbot.paused.ip.'.$question->clientKey) === true);
    }

    /**
     * Seconds until this visitor may ask again, or null when within their
     * share. A visitor's share comes from the settings; one IP address may
     * use several shares, because a campus network shares one address.
     */
    private function fairUseWait(ChatbotQuestionDTO $question, ChatbotSettingsDTO $settings): ?int
    {
        $multiplier = max(1, (int) config('chatbot.fair_use.ip_multiplier', 8));
        $windows = [
            'm' => [60, (int) config('chatbot.fair_use.per_minute', 5)],
            'h' => [3600, $settings->visitorHourlyLimit],
            'd' => [86400, $settings->visitorDailyLimit],
        ];

        $buckets = [];
        foreach ($windows as $name => [$decay, $limit]) {
            if ($limit <= 0) {
                continue;
            }
            if ($question->visitorKey !== null) {
                $buckets[] = ["chatbot-fair:v:{$question->visitorKey}:{$name}", $limit, $decay];
            }
            if ($question->clientKey !== null) {
                // Without a session there is nothing to tell visitors apart.
                $buckets[] = ["chatbot-fair:ip:{$question->clientKey}:{$name}", $question->visitorKey !== null ? $limit * $multiplier : $limit, $decay];
            }
        }

        $wait = null;
        foreach ($buckets as [$key, $limit]) {
            if (RateLimiter::tooManyAttempts($key, $limit)) {
                $wait = max($wait ?? 0, RateLimiter::availableIn($key));
            }
        }

        if ($wait !== null) {
            return $wait;
        }

        foreach ($buckets as [$key, , $decay]) {
            RateLimiter::hit($key, $decay);
        }

        return null;
    }

    private function isRepeated(ChatbotQuestionDTO $question, string $message): bool
    {
        $owner = $question->visitorKey ?? $question->clientKey;
        if ($owner === null) {
            return false;
        }

        $key = 'chatbot.repeat.'.$owner.'.'.sha1(SearchTextNormalizer::normalize((string) preg_replace('/[^\p{L}\p{N}\s]+/u', ' ', $message)));
        $count = (int) Cache::get($key, 0) + 1;
        Cache::put($key, $count, 600);

        return $count > max(1, (int) config('chatbot.fair_use.repeat_limit', 3));
    }

    /**
     * Ask the main model; when it is rate limited, out of quota, unavailable
     * or refuses the request, ask the fallback model once. A rejected key is
     * never retried: it would fail the same way.
     *
     * @return array{0: ChatbotCompletionDTO, 1: string}
     */
    private function respondWithFallback(ChatbotCompletionRequestDTO $request, ?string $fallback): array
    {
        try {
            return [$this->openAi->respond($request), $request->model];
        } catch (ChatbotProviderException $exception) {
            if ($fallback === null || $fallback === $request->model || $exception->reason === ChatbotProviderException::AUTH) {
                throw $exception;
            }

            Log::notice('Chatbot main model failed; asking the fallback model.', ['model' => $request->model, 'fallback' => $fallback, 'reason' => $exception->reason, 'status' => $exception->status]);

            return [$this->openAi->respond(new ChatbotCompletionRequestDTO(
                apiKey: $request->apiKey,
                model: $fallback,
                instructions: $request->instructions,
                messages: $request->messages,
                maxOutputTokens: $request->maxOutputTokens,
                reasoningEffort: $this->reasoningEffort($fallback),
                cacheKey: $request->cacheKey,
                jsonSchema: $request->jsonSchema,
            )), $fallback];
        }
    }

    private function flagged(string $text, string $apiKey): bool
    {
        try {
            return $this->openAi->moderate($text, $apiKey);
        } catch (ChatbotProviderException $exception) {
            if ($exception->reason === ChatbotProviderException::AUTH || $exception->reason === ChatbotProviderException::QUOTA) {
                throw $exception;
            }
            Log::notice('Chatbot moderation check skipped.', ['reason' => $exception->reason, 'status' => $exception->status]);

            return false;
        }
    }

    /**
     * @return list<string>
     */
    private function linkHosts(): array
    {
        $hosts = array_values(array_filter((array) config('chatbot.guard.link_hosts', []), 'is_string'));
        foreach ([config('app.url'), config('edge.canonical_url')] as $url) {
            $host = is_string($url) ? parse_url($url, PHP_URL_HOST) : null;
            if (is_string($host) && $host !== '') {
                $hosts[] = $host;
            }
        }

        return array_values(array_unique($hosts));
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
     * Earlier turns, kept only as whole question-and-answer pairs this server
     * produced (signed), newest last. A forged or edited turn is dropped with
     * its partner.
     *
     * @param  list<array{role: 'user'|'assistant', content: string, signature?: ?string}>  $history
     * @return list<array{role: 'user'|'assistant', content: string}>
     */
    private function history(array $history, string $locale): array
    {
        $maxTurns = (int) config('chatbot.conversation.max_history_turns', 6);
        $maxCharacters = (int) config('chatbot.conversation.max_history_characters', 1500);

        $pairs = [];
        for ($index = 0; $index < count($history) - 1; $index++) {
            $user = $history[$index];
            $assistant = $history[$index + 1];
            if ($user['role'] !== 'user' || $assistant['role'] !== 'assistant') {
                continue;
            }

            if (ConversationSigner::verify($locale, $user['content'], $assistant['content'], $assistant['signature'] ?? null)) {
                $pairs[] = [
                    ['role' => 'user', 'content' => mb_substr(trim($user['content']), 0, $maxCharacters)],
                    ['role' => 'assistant', 'content' => mb_substr(trim($assistant['content']), 0, $maxCharacters)],
                ];
                $index++;
            }
        }

        return array_merge(...array_slice($pairs, -max(1, intdiv($maxTurns, 2))));
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
            $entry = $byDocument[$passage->documentId] ?? ['title' => $passage->documentTitle, 'pages' => [], 'url' => $passage->url];
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
                $url = is_string($item['url'] ?? null) && preg_match('#^/(ar|en)(/[a-z0-9/-]*)?$#', $item['url']) === 1 ? $item['url'] : null;
                $sources[] = new ChatbotSourceDTO($item['title'], array_slice($pages, 0, 6), $url);
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
        // Model ids contain dots (gpt-5.4-mini), so never address them with a config dot path.
        if (! (bool) (((array) config('chatbot.models', []))[$model]['reasoning'] ?? false)) {
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

    private function notice(string $status, string $key, string $locale, bool $withContact = true): ChatbotReplyDTO
    {
        $contacts = $this->contacts($locale);
        $message = (string) __('chatbot.replies.'.$key, [], $locale);
        $reach = array_values(array_filter([$contacts['phone'], $contacts['email']]));

        if ($withContact && $reach !== []) {
            $message .= ' '.__('chatbot.replies.contact', ['contact' => implode(' · ', $reach)], $locale);
        }

        return new ChatbotReplyDTO($status, $message);
    }
}
