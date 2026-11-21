<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Support\Facades\Vite;

/**
 * The Content-Security-Policy nonce for this request.
 *
 * The public policy lets inline <style> elements run only when they carry this
 * nonce, so a <style> injected into a page (through stored content or a
 * reflected value) is ignored by the browser. Templates print it with the
 * `@cspNonce` Blade directive; SecurityHeadersMiddleware adds it to
 * style-src-elem.
 *
 * Bound as a scoped instance: one value per request. The public page cache
 * stores pages with the nonce masked and puts the current request's nonce back
 * on every hit (CachePublicPages), the same way it handles the CSRF token, so a
 * cached page never reuses an old nonce.
 */
final class CspNonce
{
    private ?string $value = null;

    public function value(): string
    {
        if ($this->value === null) {
            $this->value = base64_encode(random_bytes(18));
            // Livewire's injected styles and Vite's tags take their nonce
            // from Vite, so everything on the page shares this one.
            Vite::useCspNonce($this->value);
        }

        return $this->value;
    }

    /**
     * The nonce if this request has already used one, without creating it.
     */
    public function current(): ?string
    {
        return $this->value;
    }
}
