<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Contracts\AuthServiceInterface;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Handles admin authentication entry points.
 */
final class AuthController extends Controller
{
    public function __construct(
        private readonly AuthServiceInterface $authService,
    ) {}

    public function create(): View
    {
        return view('admin.auth.login');
    }

    public function switchLocale(Request $request, string $locale): RedirectResponse
    {
        if (in_array($locale, ['ar', 'en'], true)) {
            $request->session()->put('admin_locale', $locale);
        }

        return back();
    }

    public function destroy(): RedirectResponse
    {
        $this->authService->logout();

        return redirect()->route('admin.login');
    }

    public function store(LoginRequest $request): RedirectResponse
    {
        if (! $this->authService->attempt($request->credentials())) {
            return back()
                ->withErrors(['email' => __('auth.failed')])
                ->onlyInput('email');
        }

        return redirect()->intended('/admin');
    }
}
