<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Http\Request;

/**
 * The admin panel's URL prefix, read from config/admin.php.
 *
 * Middleware and the error renderer used to test `$request->is('admin')`
 * directly. With the panel on a secret prefix that check would have kept
 * treating /admin specially - a different 404 page and CSP header there than
 * on any other unknown URL, which is exactly what a scanner looks for.
 */
final class AdminPath
{
    public static function prefix(): string
    {
        return (string) config('admin.path', 'admin');
    }

    /**
     * Absolute path of the panel root, or of a page under it.
     */
    public static function url(string $suffix = ''): string
    {
        $suffix = ltrim($suffix, '/');

        return '/'.self::prefix().($suffix !== '' ? '/'.$suffix : '');
    }

    public static function matches(Request $request): bool
    {
        $prefix = self::prefix();

        return $request->is($prefix) || $request->is($prefix.'/*');
    }
}
