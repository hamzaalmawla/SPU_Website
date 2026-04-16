<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Contracts\AuthServiceInterface;
use Closure;
use Illuminate\Contracts\Auth\Factory as AuthFactory;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Enforces role-based access for authenticated admin users.
 */
final class RoleMiddleware
{
    public function __construct(
        private readonly AuthFactory $authFactory,
        private readonly AuthServiceInterface $authService,
    ) {}

    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        if ($roles === []) {
            return $next($request);
        }

        $user = $this->authFactory->guard((string) config('auth.admin_guard', 'web'))->user();

        if ($user === null) {
            return redirect()->guest(route('admin.login'));
        }

        foreach ($roles as $role) {
            if ($this->authService->checkRole($user, $role)) {
                return $next($request);
            }
        }

        abort(403);
    }
}
