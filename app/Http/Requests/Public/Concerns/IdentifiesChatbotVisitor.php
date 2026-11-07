<?php

declare(strict_types=1);

namespace App\Http\Requests\Public\Concerns;

/**
 * The keys the assistant's fair use and pauses are counted on: keyed hashes,
 * so a stored value can never be turned back into an address or a session.
 */
trait IdentifiesChatbotVisitor
{
    protected function chatbotClientKey(): string
    {
        return hash_hmac('sha256', (string) $this->ip(), 'chatbot-client|'.(string) config('app.key'));
    }

    protected function chatbotVisitorKey(): ?string
    {
        return $this->hasSession() ? hash_hmac('sha256', $this->session()->getId(), 'chatbot-visitor|'.(string) config('app.key')) : null;
    }
}
