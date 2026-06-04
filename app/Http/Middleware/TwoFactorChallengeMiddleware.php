<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Contracts\Auth\Factory as AuthFactory;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Redirects authenticated users with 2FA enabled to the TOTP challenge page
 * unless they have already verified their code for this session.
 */
final class TwoFactorChallengeMiddleware
{
    public function __construct(
        private readonly AuthFactory $authFactory,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $guard = (string) config('auth.admin_guard', 'web');
        $user = $this->authFactory->guard($guard)->user();

        if ($user === null) {
            return $next($request);
        }

        // Only challenge users who have 2FA enabled.
        $twoFactorEnabled = (bool) ($user->two_factor_enabled ?? false);

        if (! $twoFactorEnabled) {
            return $next($request);
        }

        // Skip only when the verification belongs to this authenticated user.
        if ($request->session()->get('2fa_verified') === true
            && (int) $request->session()->get('2fa_verified_user_id') === (int) $user->getAuthIdentifier()
        ) {
            return $next($request);
        }

        // Allow access to the 2FA challenge route itself to avoid redirect loops.
        $challengeRoute = 'admin.two-factor.challenge';
        $verifyRoute = 'admin.two-factor.verify';

        if ($request->routeIs($challengeRoute) || $request->routeIs($verifyRoute)) {
            return $next($request);
        }

        // Also allow logout so users can escape if needed.
        if ($request->routeIs('admin.logout') || $request->routeIs('filament.admin.auth.logout')) {
            return $next($request);
        }

        return redirect()->route($challengeRoute);
    }
}
