<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Contracts\Auth\AuthServiceInterface;
use App\Models\User\User;
use App\Services\Auth\TotpAuthenticator;
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

        $this->google2fa = new Google2FA;
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
            ->assertSee('SPU CMS');
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

    public function test_challenge_page_redirects_when_already_verified_for_current_user(): void
    {
        $user = $this->createUserWith2FA();

        $this->actingAs($user, 'web');

        session()->put('2fa_verified', true);
        session()->put('2fa_verified_user_id', $user->id);

        $this->get('/admin/two-factor-challenge')
            ->assertRedirect('/admin');
    }

    public function test_challenge_page_does_not_accept_verification_for_different_user(): void
    {
        $user = $this->createUserWith2FA();

        $this->actingAs($user, 'web');

        session()->put('2fa_verified', true);
        session()->put('2fa_verified_user_id', $user->id + 1);

        $this->get('/admin/two-factor-challenge')
            ->assertOk()
            ->assertSee('SPU CMS');
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
        $this->assertSame($user->id, session('2fa_verified_user_id'));
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
        $recoveryCodes = $this->authenticator->generateRecoveryCodes($user);
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

    public function test_two_factor_user_is_redirected_from_admin_until_verified(): void
    {
        $user = $this->createUserWith2FA();

        $this->actingAs($user, 'web');

        $this->get('/admin')
            ->assertRedirect(route('admin.two-factor.challenge'));
    }

    public function test_super_admin_can_disable_another_users_two_factor_authentication(): void
    {
        $actor = User::factory()->create(['role_slug' => 'super_admin']);
        $target = $this->createUserWith2FA();

        $this->app->make(AuthServiceInterface::class)->updateUser(
            (int) $target->getKey(),
            ['two_factor_enabled' => false],
            (int) $actor->getKey(),
        );

        $target->refresh();

        $this->assertFalse($target->two_factor_enabled);
        $this->assertNull($target->totp_secret_encrypted);
        $this->assertNull($target->recovery_codes_encrypted);
    }

    public function test_two_factor_user_can_use_filament_logout_before_verification(): void
    {
        $user = $this->createUserWith2FA();

        $this->actingAs($user, 'web');

        $this->post('/admin/logout')
            ->assertRedirect('/admin/login');
    }

    public function test_privileged_production_user_must_confirm_enrollment_before_admin_access(): void
    {
        config()->set('auth.two_factor.require_for_privileged_roles', true);
        config()->set('auth.two_factor.privileged_roles', ['super_admin', 'editor', 'faculty_editor', 'hr']);
        $user = User::factory()->create([
            'role_slug' => 'super_admin',
            'two_factor_enabled' => false,
            'two_factor_confirmed_at' => null,
        ]);

        $this->actingAs($user, 'web');

        $this->get('/admin')
            ->assertRedirect(route('filament.admin.pages.two-factor-setup'));

        $this->get('/admin/two-factor-setup')->assertOk();
    }

    public function test_unconfirmed_enabled_flag_does_not_bypass_required_enrollment(): void
    {
        config()->set('auth.two_factor.require_for_privileged_roles', true);
        $user = User::factory()->create([
            'role_slug' => 'editor',
            'two_factor_enabled' => true,
            'two_factor_confirmed_at' => null,
        ]);

        $this->actingAs($user, 'web');

        $this->get('/admin')
            ->assertRedirect(route('filament.admin.pages.two-factor-setup'));
    }

    public function test_non_privileged_role_is_not_forced_into_enrollment(): void
    {
        config()->set('auth.two_factor.require_for_privileged_roles', true);
        config()->set('auth.two_factor.privileged_roles', ['super_admin']);
        $user = User::factory()->create([
            'role_slug' => 'editor',
            'two_factor_enabled' => false,
        ]);

        $this->actingAs($user, 'web');

        $this->get('/admin')
            ->assertOk();
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
