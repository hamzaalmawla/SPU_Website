<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Contracts\AuditServiceInterface;
use App\Contracts\TotpAuthenticatorInterface;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\TwoFactorChallengeRequest;
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
        private readonly TotpAuthenticatorInterface $authenticator,
        private readonly AuthFactory $authFactory,
        private readonly AuditServiceInterface $auditService,
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

        // Already verified for this exact user - skip challenge.
        if ($request->session()->get('2fa_verified') === true
            && (int) $request->session()->get('2fa_verified_user_id') === (int) $user->getAuthIdentifier()
        ) {
            return redirect('/admin');
        }

        return view('admin.auth.two-factor-challenge');
    }

    /**
     * Verify the submitted TOTP or recovery code.
     */
    public function store(TwoFactorChallengeRequest $request): RedirectResponse
    {
        $user = $this->resolveUser();

        if ($user === null || ! $this->hasTwoFactorEnabled($user)) {
            return redirect('/admin/login');
        }

        $code = $request->code();

        // Try TOTP code first, then recovery code.
        $verified = $this->authenticator->verify($user, $code)
            || $this->authenticator->verifyRecoveryCode($user, $code);

        if (! $verified) {
            $this->auditService->log(
                action: 'user.two_factor_failed',
                userId: (int) $user->getKey(),
                entityType: 'user',
                entityId: (int) $user->getKey(),
            );

            return back()->withErrors([
                'code' => __('The provided two-factor authentication code was invalid.'),
            ]);
        }

        $request->session()->put('2fa_verified', true);
        $request->session()->put('2fa_verified_user_id', (int) $user->getAuthIdentifier());

        $this->auditService->log(
            action: 'user.two_factor_verified',
            userId: (int) $user->getKey(),
            entityType: 'user',
            entityId: (int) $user->getKey(),
        );

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
