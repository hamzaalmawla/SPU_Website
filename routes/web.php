<?php

declare(strict_types=1);

use App\Http\Controllers\Admin\AuthController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\PageController;
use App\Http\Controllers\PreviewController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/ar')->name('root');

Route::prefix('{locale}')
    ->where(['locale' => 'ar|en'])
    ->middleware(['locale', 'cache.public'])
    ->group(function (): void {
        Route::get('/', HomeController::class)->name('public.home');

        Route::get('/preview', PreviewController::class)->name('preview.show');

        Route::post('/contact', function (Request $request, string $locale) {
            return response()->json([
                'submitted' => true,
                'locale' => $locale,
                'email' => (string) $request->string('email'),
            ]);
        })->middleware('throttle:public-form')->name('public.contact.submit');

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

                Route::get('/content', function () {
                    return response(
                        '<html lang="en"><body>Admin content</body></html>',
                        200,
                        ['Content-Type' => 'text/html; charset=UTF-8'],
                    );
                })->middleware('can:manage-homepage')->name('content');

                Route::get('/settings', function () {
                    return response(
                        '<html lang="en"><body>Admin settings</body></html>',
                        200,
                        ['Content-Type' => 'text/html; charset=UTF-8'],
                    );
                })->middleware('can:manage-settings')->name('settings');

                Route::get('/users', function () {
                    return response(
                        '<html lang="en"><body>Admin users</body></html>',
                        200,
                        ['Content-Type' => 'text/html; charset=UTF-8'],
                    );
                })->middleware('can:manage-users')->name('users.index');
            });
    });
