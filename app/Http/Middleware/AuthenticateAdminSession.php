<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Illuminate\Session\Middleware\AuthenticateSession;

/**
 * Filament's session check, sending a session that no longer matches its
 * user (password changed elsewhere) to the admin's own login page. The panel
 * has no Filament login route, so Filament's version failed with a 500.
 */
final class AuthenticateAdminSession extends AuthenticateSession
{
    protected function redirectTo(Request $request): ?string
    {
        return route('admin.login');
    }
}
