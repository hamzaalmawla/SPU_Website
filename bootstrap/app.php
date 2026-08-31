<?php

use App\Contracts\ErrorPage\ErrorPageRendererInterface;
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
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

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

        // Branded, bilingual error pages.
        //
        // Views live in resources/views/errors/{status}.blade.php, which
        // Laravel resolves on its own as `errors::{status}` — that path alone
        // already covers raw 500s and maintenance-mode 503s, and it renders
        // the self-contained standalone shell.
        //
        // This callback adds the layer Laravel has no hook for: for the
        // application-level statuses it attempts the full public shell
        // (navigation, footer, language switch) and silently degrades back to
        // the standalone view when the services behind that shell are
        // unreachable. ErrorPageRenderer returns null — deferring completely
        // to Laravel — for JSON/API callers, and while APP_DEBUG is on for
        // non-HTTP exceptions so the debug page survives.
        //
        // Typed to HttpExceptionInterface on purpose: AuthenticationException,
        // ValidationException and HttpResponseException must keep reaching
        // Laravel's own handling, which runs after render callbacks, so admin
        // login redirects and form validation redirects are unaffected.
        $exceptions->render(function (HttpExceptionInterface $e, Request $request): ?Response {
            return app(ErrorPageRendererInterface::class)->render($e, $request);
        });
    })->create();
