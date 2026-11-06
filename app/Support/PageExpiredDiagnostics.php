<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Records why an admin saw "This page has expired".
 *
 * Livewire answers several unrelated failures with the same 419 and that
 * prompt, and Laravel logs none of them: a CSRF mismatch is on its internal
 * don't-report list, and Livewire swallows a TypeError raised by a component
 * method and aborts with 419 when APP_DEBUG is off. Without this log, a lost
 * session and a bug in a component look identical to the editor and to us.
 *
 * Never logs the token, the session id or cookie values.
 */
final class PageExpiredDiagnostics
{
    public static function csrfMismatch(Request $request): void
    {
        try {
            $session = $request->hasSession() ? $request->session() : null;
            $hadCookie = $request->cookies->has((string) config('session.cookie'));
            $signedIn = $session !== null && collect(array_keys($session->all()))
                ->contains(static fn (string $key): bool => str_starts_with($key, 'login_'));
            $startedAt = $session?->get('admin_session_started_at');
            $lastActivityAt = $session?->get('admin_session_last_activity_at');
            $now = now()->getTimestamp();

            Log::warning('Page expired (419): CSRF token mismatch', [
                // cookie sent but no signed-in session: the session itself was lost
                // (expired, logged out elsewhere, garbage-collected). Signed in:
                // the page's token was stale, e.g. a tab left open across a login.
                'cause' => $hadCookie && ! $signedIn ? 'session_lost' : ($hadCookie ? 'stale_page_token' : 'no_session_cookie'),
                'method' => $request->method(),
                'path' => '/'.ltrim($request->path(), '/'),
                'livewire' => $request->headers->has('X-Livewire'),
                'session_age_minutes' => is_int($startedAt) ? intdiv($now - $startedAt, 60) : null,
                'idle_minutes' => is_int($lastActivityAt) ? intdiv($now - $lastActivityAt, 60) : null,
            ]);
        } catch (Throwable) {
            // Diagnostics must never change the response.
        }
    }

    public static function livewireTypeError(object $component, Throwable $exception): void
    {
        if (! $exception instanceof \TypeError || (bool) config('app.debug')) {
            return;
        }

        try {
            Log::error('Page expired (419): Livewire swallowed a TypeError in '.$component::class, [
                'exception' => $exception,
            ]);
        } catch (Throwable) {
            // Diagnostics must never change the response.
        }
    }
}
