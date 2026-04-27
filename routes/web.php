<?php

declare(strict_types=1);

use App\Http\Controllers\Admin\AuthController;
use App\Http\Controllers\Admin\ShellController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\PageController;
use App\Http\Controllers\PreviewController;
use App\Http\Controllers\PublicContactController;
use App\Http\Controllers\SitemapController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/ar')->name('root');

Route::get('/sitemap.xml', [SitemapController::class, 'sitemap'])->name('sitemap');
Route::get('/robots.txt', [SitemapController::class, 'robots'])->name('robots');

Route::prefix('{locale}')
    ->where(['locale' => 'ar|en'])
    ->middleware(['locale', 'cache.public'])
    ->group(function (): void {
        Route::get('/', HomeController::class)->name('public.home');

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
    ->group(function (): void {
        Route::get('/login', [AuthController::class, 'create'])->name('login');
        Route::post('/login', [AuthController::class, 'store'])
            ->middleware('throttle:admin-login')
            ->name('login.attempt');

        Route::middleware(['admin.auth'])
            ->group(function (): void {
                Route::post('/auth/logout', [AuthController::class, 'destroy'])->name('logout');

                Route::get('/content', [ShellController::class, 'content'])
                    ->middleware('can:manage-homepage')
                    ->name('content');

                Route::get('/settings', [ShellController::class, 'settings'])
                    ->middleware('can:manage-settings')
                    ->name('settings');

                Route::get('/users', [ShellController::class, 'users'])
                    ->middleware('can:manage-users')
                    ->name('users.index');
            });
    });
