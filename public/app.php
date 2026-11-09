<?php

use Illuminate\Foundation\Application;
use Illuminate\Http\Request;

define('LARAVEL_START', microtime(true));

/*
|--------------------------------------------------------------------------
| Front controller
|--------------------------------------------------------------------------
|
| This file, not index.php, is the front controller the web server routes to.
|
| On the old site "/index.php?page=..&lang=..&service=..&cat_id=.." is by far the
| largest legacy URL family (~14k of the ~28k URLs in the old sitemap). If the
| front controller were named index.php, Apache would serve those requests as the
| script itself: SCRIPT_NAME and REQUEST_URI would both be "/index.php", Symfony
| would compute baseUrl="/index.php", $request->path() would collapse to "/", and
| RedirectContinuityMiddleware would never see the real path. Every redirect it
| generated would also be prefixed with "/index.php".
|
| Naming the front controller app.php keeps "/index.php" an ordinary request path
| that the legacy redirect engine can resolve. public/.htaccess maps both "/" and
| a literal "/index.php" here. public/index.php is kept only so `artisan serve`
| and non-Apache environments still work.
|
*/

// Supports both the standard layout (application in the parent directory) and the
// cPanel split layout, where the application lives outside the web root.
$appRoot = is_file(dirname(__DIR__).'/vendor/autoload.php')
    ? dirname(__DIR__)
    : dirname(__DIR__, 3).'/spu_v2_app';

// Determine if the application is in maintenance mode...
//
// Every deploy runs `artisan down --render`, and maintenance.php then prints
// the pre-rendered 503 page and exits before Laravel (and so
// SecurityHeadersMiddleware) runs. Send the security headers first, so pages
// fetched during a deploy are not served bare (2026-11-08 ZAP scan). The page
// is self-contained: one inline <style>, the site's own fonts, a data: logo,
// no script.
// If maintenance.php returns instead (bypass cookie, excluded path), Laravel
// handles the request and sends its own copies, so these are taken back.
if (file_exists($maintenance = $appRoot.'/storage/framework/maintenance.php')) {
    $maintenanceHeaders = [
        'X-Content-Type-Options' => 'nosniff',
        'Referrer-Policy' => 'strict-origin-when-cross-origin',
        'X-Frame-Options' => 'SAMEORIGIN',
        'Content-Security-Policy' => "default-src 'none'; base-uri 'none'; form-action 'none'; frame-ancestors 'self'; img-src 'self' data:; font-src 'self'; style-src 'unsafe-inline'",
        'Cache-Control' => 'no-store',
    ];

    // Same value as public/.htaccess and config/security.php.
    if (($_SERVER['HTTPS'] ?? '') === 'on' || ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https') {
        $maintenanceHeaders['Strict-Transport-Security'] = 'max-age=604800; includeSubDomains';
    }

    foreach ($maintenanceHeaders as $name => $value) {
        header($name.': '.$value);
    }

    require $maintenance;

    foreach (array_keys($maintenanceHeaders) as $name) {
        header_remove($name);
    }
}

// Register the Composer autoloader...
require $appRoot.'/vendor/autoload.php';

// Bootstrap Laravel and handle the request...
/** @var Application $app */
$app = require_once $appRoot.'/bootstrap/app.php';

$app->handleRequest(Request::capture());
