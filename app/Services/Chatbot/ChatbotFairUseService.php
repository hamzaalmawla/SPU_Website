<?php

declare(strict_types=1);

namespace App\Services\Chatbot;

use App\Contracts\Chatbot\ChatbotFairUseServiceInterface;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\RateLimiter;

/**
 * Fair-use windows on Laravel's rate limiter (the cache store), and the
 * pause flags ChatbotService sets for repeated misuse.
 */
final class ChatbotFairUseService implements ChatbotFairUseServiceInterface
{
    public function isPaused(?string $visitorKey, ?string $clientKey): bool
    {
        return ($visitorKey !== null && Cache::get(ChatbotCaches::pauseKey('v', $visitorKey)) === true)
            || ($clientKey !== null && Cache::get(ChatbotCaches::pauseKey('ip', $clientKey)) === true);
    }

    public function consume(string $purpose, array $windows, ?string $visitorKey, ?string $clientKey): ?int
    {
        $multiplier = max(1, (int) config('chatbot.fair_use.ip_multiplier', 8));

        $buckets = [];
        foreach ($windows as $name => [$decay, $limit]) {
            if ($limit <= 0) {
                continue;
            }
            if ($visitorKey !== null) {
                $buckets[] = ["chatbot-fair:{$purpose}:v:{$visitorKey}:{$name}", $limit, $decay];
            }
            if ($clientKey !== null) {
                // Without a session there is nothing to tell visitors apart.
                $buckets[] = ["chatbot-fair:{$purpose}:ip:{$clientKey}:{$name}", $visitorKey !== null ? $limit * $multiplier : $limit, $decay];
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
}
