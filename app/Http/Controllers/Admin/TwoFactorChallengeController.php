<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\TotpAuthenticator;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Auth\Factory as AuthFactory;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Handles the 2FA TOTP challenge during admin login.
 *
 * After successful password authentication, users with 2FA enabled
 * are redirected here to enter their TOTP code or a recovery code.
 */
final class TwoFactorChallengeController extends Controller
{
    public function __construct(
        private readonly TotpAuthenticator $authenticator,
        private readonly AuthFactory $authFactory,
    ) {}

    /**
     * Show the 2FA challenge form.
     */
    public function create(Request $request): View|RedirectResponse
    {
        $user = $this->resolveUser();

        if ($user === null || ! $this->hasTwoFactorEnabled($user)) {
            return redirect('/admin');
        }

        // Already verified — skip challenge.
        if ($request->session()->get('2fa_verified') === true) {
            return redirect('/admin');
        }

        return view('admin.auth.two-factor-challenge');
    }

    /**
     * Verify the submitted TOTP or recovery code.
     */
    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'code' => ['required', 'string'],
        ]);

        $user = $this->resolveUser();

        if ($user === null || ! $this->hasTwoFactorEnabled($user)) {
            return redirect('/admin/login');
        }

        $code = trim((string) $request->input('code'));

        // Try TOTP code first, then recovery code.
        // TotpAuthenticator accepts Authenticatable instances that are User models.
        /** @var \App\Models\User $user */
        $verified = $this->authenticator->verify($user, $code)
            || $this->authenticator->verifyRecoveryCode($user, $code);

        if (! $verified) {
            return back()->withErrors([
                'code' => __('The provided two-factor authentication code was invalid.'),
            ]);
        }

        $request->session()->put('2fa_verified', true);

        return redirect()->intended('/admin');
    }

    private function resolveUser(): ?Authenticatable
    {
        $guard = (string) config('auth.admin_guard', 'web');

        return $this->authFactory->guard($guard)->user();
    }

    private function hasTwoFactorEnabled(Authenticatable $user): bool
    {
        return (bool) ($user->two_factor_enabled ?? false);
    }
}
