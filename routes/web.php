<?php

declare(strict_types=1);

use App\Http\Controllers\Admin\AuthController;
use App\Http\Controllers\Admin\TwoFactorChallengeController;
use App\Http\Controllers\AboutController;
use App\Http\Middleware\AdminLocaleMiddleware;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\PageController;
use App\Http\Controllers\PreviewController;
use App\Http\Controllers\PublicContactController;
use App\Http\Controllers\SitemapController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/ar')->name('root');

Route::get('/sitemap.xml', [SitemapController::class, 'sitemap'])->name('sitemap');
Route::get('/robots.txt', [SitemapController::class, 'robots'])->name('robots');

Route::prefix('{locale}')
    ->where(['locale' => 'ar|en'])
    ->middleware(['locale', 'cache.public'])
    ->group(function (): void {
        Route::get('/', HomeController::class)->name('public.home');

        Route::controller(AboutController::class)
            ->prefix('about')
            ->name('public.about.')
            ->group(function (): void {
                Route::get('/', 'landing')->name('landing');
                Route::get('/history', 'history')->name('history');
                Route::get('/leadership', 'leadership')->name('leadership');
                Route::get('/central-directorates', 'directorates')->name('central-directorates');
                Route::get('/directorates', 'directorates')->name('directorates');
                Route::get('/directorates/staff', 'staffDirectory')->name('directorates.staff');
                Route::get('/directorates/{directorate}', 'directorateDetail')->name('directorates.show');
                Route::get('/partnerships', 'partnerships')->name('partnerships');
                Route::get('/vision-mission', 'visionMission')->name('vision-mission');
            });

        Route::get('/preview', PreviewController::class)->name('preview.show');

        Route::post('/contact', PublicContactController::class)
            ->middleware('throttle:public-form')
            ->name('public.contact.submit');

        Route::get('/{slugPath}', PageController::class)
            ->where('slugPath', '.+')
            ->name('public.page');
    });

Route::prefix('admin')
    ->name('admin.')
    ->middleware(AdminLocaleMiddleware::class)
    ->group(function (): void {
        Route::get('/login', [AuthController::class, 'create'])->name('login');
        Route::post('/locale/{locale}', [AuthController::class, 'switchLocale'])
            ->where(['locale' => 'ar|en'])
            ->name('locale');
        Route::post('/login', [AuthController::class, 'store'])
            ->middleware('throttle:admin-login')
            ->name('login.attempt');

        Route::middleware(['admin.auth', 'two.factor'])
            ->group(function (): void {
                Route::post('/auth/logout', [AuthController::class, 'destroy'])->name('logout');

                Route::get('/two-factor-challenge', [TwoFactorChallengeController::class, 'create'])
                    ->name('two-factor.challenge');
                Route::post('/two-factor-challenge', [TwoFactorChallengeController::class, 'store'])
                    ->middleware('throttle:two-factor')
                    ->name('two-factor.verify');
            });
    });

/*
|--------------------------------------------------------------------------
| Webhook Routes
|--------------------------------------------------------------------------
|
| Webhook routes are excluded from CSRF verification in bootstrap/app.php
| and instead protected by HMAC-SHA256 signature verification middleware.
| Add new webhook consumer routes inside this group.
|
*/
Route::prefix('webhook')
    ->name('webhook.')
    ->middleware('verify.webhook')
    ->group(function (): void {
        Route::post('/incoming', function (Request $request) {
            return response()->json(['status' => 'received'], 200);
        })->name('incoming');
    });
