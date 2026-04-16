<?php

declare(strict_types=1);

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/ar')->name('root');

Route::prefix('{locale}')
    ->where(['locale' => 'ar|en'])
    ->middleware(['locale', 'cache.public'])
    ->group(function (): void {
        Route::get('/', function (string $locale) {
            return response(
                sprintf('<html lang="%s"><body>Public %s homepage</body></html>', $locale, strtoupper($locale)),
                200,
                ['Content-Type' => 'text/html; charset=UTF-8'],
            );
        })->name('public.home');

        Route::get('/preview', function (Request $request, string $locale) {
            $token = (string) $request->query('preview_token', 'preview');

            return response(
                sprintf('<html lang="%s"><body>Preview %s %s</body></html>', $locale, strtoupper($locale), $token),
                200,
                ['Content-Type' => 'text/html; charset=UTF-8'],
            );
        })->name('preview.show');

        Route::post('/contact', function (Request $request, string $locale) {
            return response()->json([
                'submitted' => true,
                'locale' => $locale,
                'email' => (string) $request->string('email'),
            ]);
        })->middleware('throttle:public-form')->name('public.contact.submit');
    });

Route::prefix('admin')
    ->name('admin.')
    ->group(function (): void {
        Route::get('/login', function () {
            return response(
                '<html lang="en"><body>Admin login</body></html>',
                200,
                ['Content-Type' => 'text/html; charset=UTF-8'],
            );
        })->name('login');

        Route::post('/login', function (Request $request) {
            return response()->json([
                'submitted' => true,
                'email' => (string) $request->string('email'),
            ]);
        })->middleware('throttle:admin-login')->name('login.attempt');

        Route::middleware(['admin.auth'])
            ->group(function (): void {
                Route::get('/', function () {
                    return response(
                        '<html lang="en"><body>Admin dashboard</body></html>',
                        200,
                        ['Content-Type' => 'text/html; charset=UTF-8'],
                    );
                })->name('dashboard');

                Route::get('/content', function () {
                    return response(
                        '<html lang="en"><body>Admin content</body></html>',
                        200,
                        ['Content-Type' => 'text/html; charset=UTF-8'],
                    );
                })->middleware('role:super_admin,editor')->name('content');
            });
    });
