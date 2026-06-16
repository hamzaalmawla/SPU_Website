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
            $this->authFactory->guard($guard)->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->guest(route('admin.login'));
        }

        $this->authService->extendSession();

        return $next($request);
    }

    private function userCanAccessAdmin(Authenticatable $user): bool
    {
        return $this->authService->checkRole($user, 'super_admin')
            || $this->authService->checkRole($user, 'editor')
            || $this->authService->checkRole($user, 'faculty_editor');
    }
}
