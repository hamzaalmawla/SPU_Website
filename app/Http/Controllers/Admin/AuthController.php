<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Contracts\Auth\AuthServiceInterface;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Support\AdminPath;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Handles admin authentication entry points.
 */
final class AuthController extends Controller
{
    public function __construct(
        private readonly AuthServiceInterface $authService,
    ) {}

    public function create(): Response
    {
        // no-store: a phone showing this page again from a background tab must
        // fetch it, or it posts a token from a session that has since expired.
        return response()
            ->view('admin.auth.login')
            ->header('Cache-Control', 'no-store, private');
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

        return redirect()->intended(AdminPath::url());
    }
}
