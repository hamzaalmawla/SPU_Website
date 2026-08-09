<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Contracts\Auth\AuthServiceInterface;
use Closure;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Auth\Factory as AuthFactory;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Ensures admin routes are only accessible to authenticated admin-guard users.
 */
final class AdminAuthMiddleware
{
    private const ADMIN_SESSION_STARTED_AT = 'admin_session_started_at';

    private const ADMIN_SESSION_LAST_ACTIVITY_AT = 'admin_session_last_activity_at';

    public function __construct(
        private readonly AuthFactory $authFactory,
        private readonly AuthServiceInterface $authService,
    ) {}

    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next, ?string $guard = null): Response
    {
        $guard ??= (string) config('auth.admin_guard', 'web');

        if (! $this->authFactory->guard($guard)->check()) {
            return redirect()->guest(route('admin.login'));
        }

        $user = $this->authFactory->guard($guard)->user();

        if ($user === null
            || $this->authService->isLocked($user)
            || ! $this->userCanAccessAdmin($user)
        ) {
            $this->authService->logout();

            return redirect()->guest(route('admin.login'));
        }

        if ($this->adminSessionTimedOut($request)) {
            $this->authService->logout();

            return redirect()->guest(route('admin.login'));
        }

        $this->authService->extendSession();

        return $next($request);
    }

    private function userCanAccessAdmin(Authenticatable $user): bool
    {
        return $this->authService->checkRole($user, 'super_admin')
            || $this->authService->checkRole($user, 'editor')
            || $this->authService->checkRole($user, 'faculty_editor')
            || $this->authService->checkRole($user, 'hr');
    }

    private function adminSessionTimedOut(Request $request): bool
    {
        $now = now()->getTimestamp();
        $idleTimeoutMinutes = $this->positiveConfigMinutes('auth.admin_session.idle_timeout_minutes');
        $lastActivityAt = $this->sessionTimestamp($request, self::ADMIN_SESSION_LAST_ACTIVITY_AT);

        if ($idleTimeoutMinutes !== null
            && $lastActivityAt !== null
            && ($now - $lastActivityAt) > ($idleTimeoutMinutes * 60)
        ) {
            return true;
        }

        $absoluteTimeoutMinutes = $this->positiveConfigMinutes('auth.admin_session.absolute_timeout_minutes');
        $startedAt = $this->sessionTimestamp($request, self::ADMIN_SESSION_STARTED_AT);

        return $absoluteTimeoutMinutes !== null
            && $startedAt !== null
            && ($now - $startedAt) > ($absoluteTimeoutMinutes * 60);
    }

    private function positiveConfigMinutes(string $key): ?int
    {
        $minutes = (int) config($key, 0);

        return $minutes > 0 ? $minutes : null;
    }

    private function sessionTimestamp(Request $request, string $key): ?int
    {
        $value = $request->session()->get($key);

        if (is_int($value)) {
            return $value;
        }

        if (is_string($value) && ctype_digit($value)) {
            return (int) $value;
        }

        return null;
    }
}
