<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Throwable;

/**
 * Turns a CSRF mismatch on an admin form into a way back in.
 *
 * The admin sign-in, two-factor, language and logout forms are plain HTML
 * posts. A phone keeps such a page in a background tab for hours and shows
 * it again without reloading, so the token it posts belongs to a session
 * that has since expired. Laravel answers with the 419 page, which offers the
 * editor only the public homepage. This sends them to the page they were
 * trying to use instead, with a fresh token and a line saying why.
 *
 * Nothing is done for the failed request: the user is not signed in, signed
 * out or switched language, so the CSRF protection is unchanged.
 */
final class ExpiredAdminForm
{
    public static function redirect(Request $request): ?RedirectResponse
    {
        if (! $request->isMethod('POST')
            || $request->expectsJson()
            || $request->headers->has('X-Livewire')
            || ! AdminPath::matches($request)
        ) {
            return null;
        }

        // The admin locale middleware runs after the CSRF check, so read the
        // editor's language from the session here.
        $locale = $request->session()->get('admin_locale');
        $message = __('admin.auth.session_expired', [], in_array($locale, ['ar', 'en'], true) ? $locale : 'ar');

        if ($request->routeIs('admin.login.attempt')) {
            return redirect()->route('admin.login')
                ->withInput($request->only('email', 'remember'))
                ->withErrors(['email' => $message]);
        }

        if ($request->routeIs('admin.two-factor.verify')) {
            return redirect()->route('admin.two-factor.challenge')
                ->withErrors(['code' => $message]);
        }

        // Logout or language switch: a signed-in editor only had a stale page,
        // so take them back to it; anyone else has to sign in again anyway.
        if (self::signedIn()) {
            return redirect()->back(fallback: AdminPath::url());
        }

        return redirect()->route('admin.login')->withErrors(['email' => $message]);
    }

    private static function signedIn(): bool
    {
        try {
            return auth()->guard((string) config('auth.admin_guard', 'web'))->check();
        } catch (Throwable) {
            return false;
        }
    }
}
