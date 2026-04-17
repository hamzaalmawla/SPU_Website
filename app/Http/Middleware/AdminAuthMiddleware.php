<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
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

        return $next($request);
    }
}
