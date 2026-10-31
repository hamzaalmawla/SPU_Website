<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Contracts\Auth\Factory as AuthFactory;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Enforces production enrollment for privileged users and challenges confirmed
 * TOTP users unless they have verified this session.
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

        $twoFactorEnabled = (bool) ($user->two_factor_enabled ?? false);
        $twoFactorConfirmed = ($user->two_factor_confirmed_at ?? null) !== null;
        $requiresEnrollment = $this->requiresConfirmedTwoFactor($user)
            && (! $twoFactorEnabled || ! $twoFactorConfirmed);

        if ($requiresEnrollment) {
            if ($request->routeIs('filament.admin.pages.two-factor-setup')
                || $this->isEnrollmentLivewireRequest($request)
                || $request->routeIs('admin.logout')
                || $request->routeIs('filament.admin.auth.logout')) {
                return $next($request);
            }

            return redirect()->route('filament.admin.pages.two-factor-setup');
        }

        if (! $twoFactorEnabled || ! $twoFactorConfirmed) {
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

    private function requiresConfirmedTwoFactor(object $user): bool
    {
        if (! (bool) config('auth.two_factor.require_for_privileged_roles', false)) {
            return false;
        }

        $role = $user->role_slug ?? null;

        return is_string($role)
            && in_array($role, (array) config('auth.two_factor.privileged_roles', []), true);
    }

    private function isEnrollmentLivewireRequest(Request $request): bool
    {
        if (! $request->routeIs('livewire.update', '*.livewire.update')) {
            return false;
        }

        $components = $request->input('components');
        if (! is_array($components) || $components === []) {
            return false;
        }

        foreach ($components as $component) {
            if (! is_array($component)) {
                return false;
            }

            $snapshot = $component['snapshot'] ?? null;
            if (is_string($snapshot)) {
                $snapshot = json_decode($snapshot, true);
            }

            if (! is_array($snapshot)
                || ($snapshot['memo']['name'] ?? null) !== 'app.filament.pages.two-factor-setup') {
                return false;
            }
        }

        return true;
    }
}
