<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Support\AdminPath;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\IpUtils;
use Symfony\Component\HttpFoundation\Response;

/**
 * Hides the admin panel from addresses outside ADMIN_ALLOWED_IPS.
 *
 * Registered as global middleware so it runs before the session starts: a
 * rejected request gets no session cookie and the same public 404 as any
 * unknown URL. Never a 403, which would confirm something lives there.
 */
final class RestrictAdminAccess
{
    public function handle(Request $request, Closure $next): Response
    {
        $allowedIps = config('admin.allowed_ips', []);

        if (! is_array($allowedIps) || $allowedIps === [] || ! AdminPath::matches($request)) {
            return $next($request);
        }

        $ip = $request->ip();

        if ($ip === null || ! IpUtils::checkIp($ip, $allowedIps)) {
            abort(404);
        }

        return $next($request);
    }
}
