<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use App\Services\TotpAuthenticator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PragmaRX\Google2FA\Google2FA;
use Tests\TestCase;

/**
 * Feature tests for the 2FA challenge flow and middleware.
 *
 * Validates: Requirements 17.2, 17.3, 17.4
 */
class TwoFactorChallengeTest extends TestCase
{
    use RefreshDatabase;

    private TotpAuthenticator $authenticator;

    private Google2FA $google2fa;

    protected function setUp(): void
    {
        parent::setUp();

        $this->google2fa = new Google2FA();
        $this->authenticator = new TotpAuthenticator($this->google2fa);
    }

    // ------------------------------------------------------------------
    // 2FA Challenge Page
    // ------------------------------------------------------------------

    public function test_challenge_page_loads_for_user_with_2fa_enabled(): void
    {
        $user = $this->createUserWith2FA();

        $this->actingAs($user, 'web');

        $this->get('/admin/two-factor-challenge')
            ->assertOk()
            ->assertSee('Two-Factor Authentication');
    }

    public function test_challenge_page_redirects_to_admin_when_2fa_not_enabled(): void
    {
        $user = User::factory()->create([
            'role_slug' => 'super_admin',
            'two_factor_enabled' => false,
        ]);

        $this->actingAs($user, 'web');

        $this->get('/admin/two-factor-challenge')
            ->assertRedirect('/admin');
    }

    public function test_challenge_page_redirects_when_already_verified(): void
    {
        $user = $this->createUserWith2FA();

        $this->actingAs($user, 'web');

        // Simulate already verified session.
        session()->put('2fa_verified', true);

        $this->get('/admin/two-factor-challenge')
            ->assertRedirect('/admin');
    }

    // ------------------------------------------------------------------
    // 2FA Code Verification
    // ------------------------------------------------------------------

    public function test_valid_totp_code_sets_session_flag_and_redirects(): void
    {
        $user = $this->createUserWith2FA();
        $secret = $user->totp_secret_encrypted;
        $validCode = $this->google2fa->getCurrentOtp($secret);

        $this->actingAs($user, 'web');

        $this->post('/admin/two-factor-challenge', ['code' => $validCode])
            ->assertRedirect('/admin');

        $this->assertTrue(session('2fa_verified'));
    }

    public function test_invalid_totp_code_returns_error(): void
    {
        $user = $this->createUserWith2FA();

        $this->actingAs($user, 'web');

        $this->post('/admin/two-factor-challenge', ['code' => '000000'])
            ->assertRedirect()
            ->assertSessionHasErrors('code');

        $this->assertNull(session('2fa_verified'));
    }

    public function test_valid_recovery_code_sets_session_flag(): void
    {
        $user = $this->createUserWith2FA();
        $recoveryCodes = $user->recovery_codes_encrypted;
        $recoveryCode = $recoveryCodes[0];

        $this->actingAs($user, 'web');

        $this->post('/admin/two-factor-challenge', ['code' => $recoveryCode])
            ->assertRedirect('/admin');

        $this->assertTrue(session('2fa_verified'));
    }

    public function test_code_field_is_required(): void
    {
        $user = $this->createUserWith2FA();

        $this->actingAs($user, 'web');

        $this->post('/admin/two-factor-challenge', ['code' => ''])
            ->assertSessionHasErrors('code');
    }

    // ------------------------------------------------------------------
    // Middleware Behavior
    // ------------------------------------------------------------------

    public function test_user_without_2fa_is_not_challenged(): void
    {
        $user = User::factory()->create([
            'role_slug' => 'super_admin',
            'two_factor_enabled' => false,
        ]);

        $this->actingAs($user, 'web');

        // Should not be redirected to 2FA challenge.
        $this->post('/admin/auth/logout')
            ->assertRedirect('/admin/login');
    }

    // ------------------------------------------------------------------
    // Helpers
    // ------------------------------------------------------------------

    private function createUserWith2FA(): User
    {
        $user = User::factory()->create([
            'role_slug' => 'super_admin',
        ]);

        $this->authenticator->generateSecret($user);

        // Simulate confirming 2FA by verifying a code.
        $secret = $user->totp_secret_encrypted;
        $validCode = $this->google2fa->getCurrentOtp($secret);
        $this->authenticator->verify($user, $validCode);

        $user->refresh();

        return $user;
    }
}
