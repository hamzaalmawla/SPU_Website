<?php

declare(strict_types=1);

namespace App\Contracts\Chatbot;

/**
 * Per-visitor shares of the assistant, shared by questions and voice.
 *
 * A visitor is a keyed hash of the browser session; a client is a keyed hash
 * of the network address, which may use several visitors' shares (a campus
 * network puts many students behind one address).
 */
interface ChatbotFairUseServiceInterface
{
    /**
     * Whether the visitor or the address is paused for repeated misuse.
     */
    public function isPaused(?string $visitorKey, ?string $clientKey): bool;

    /**
     * Take one unit of the visitor's share for `$purpose`. Returns null when
     * it was within every window (and counts it), or the seconds until it
     * would be (and counts nothing).
     *
     * @param  array<string, array{0: int, 1: int}>  $windows  name => [seconds, limit]; a limit of 0 or less is no limit
     */
    public function consume(string $purpose, array $windows, ?string $visitorKey, ?string $clientKey): ?int;
}
