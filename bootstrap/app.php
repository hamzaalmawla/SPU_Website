<?php

use App\Http\Middleware\AdminAuthMiddleware;
use App\Http\Middleware\CachePublicPages;
use App\Http\Middleware\MinifyPublicHtml;
use App\Http\Middleware\EnforcePublicOrigin;
use App\Http\Middleware\LocaleSetterMiddleware;
use App\Http\Middleware\RedirectContinuityMiddleware;
use App\Http\Middleware\SecurityHeadersMiddleware;
use App\Http\Middleware\TwoFactorChallengeMiddleware;
use App\Http\Middleware\VerifyWebhookSignature;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Sentry\Laravel\Integration;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->trustProxies(
            at: ['127.0.0.1', '::1'],
            headers: Request::HEADER_X_FORWARDED_FOR
                | Request::HEADER_X_FORWARDED_HOST
                | Request::HEADER_X_FORWARDED_PORT
                | Request::HEADER_X_FORWARDED_PROTO,
        );
        $middleware->prepend(RedirectContinuityMiddleware::class);
        $middleware->prepend(EnforcePublicOrigin::class);
        $middleware->append(SecurityHeadersMiddleware::class);

        $middleware->validateCsrfTokens(except: [
            'webhook/*',
        ]);

        $middleware->alias([
            'locale' => LocaleSetterMiddleware::class,
            'admin.auth' => AdminAuthMiddleware::class,
            'two.factor' => TwoFactorChallengeMiddleware::class,
            'cache.public' => CachePublicPages::class,
            'minify.html' => MinifyPublicHtml::class,
            'verify.webhook' => VerifyWebhookSignature::class,
        ]);

        $middleware->redirectGuestsTo('/admin/login');
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        Integration::handles($exceptions);
    })->create();
