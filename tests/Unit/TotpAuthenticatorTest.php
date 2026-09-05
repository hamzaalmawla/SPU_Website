<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\DTOs\Auth\TotpEnrollmentDTO;
use App\Models\User\User;
use App\Services\Auth\TotpAuthenticator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use PragmaRX\Google2FA\Google2FA;
use Tests\TestCase;

/**
 * Unit tests for the TotpAuthenticator service.
 *
 * Validates: Requirements 17.2, 17.3
 */
class TotpAuthenticatorTest extends TestCase
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
    // generateSecret()
    // ------------------------------------------------------------------

    public function test_generate_secret_returns_totp_enrollment_dto(): void
    {
        $user = User::factory()->create();

        $result = $this->authenticator->generateSecret($user);

        $this->assertInstanceOf(TotpEnrollmentDTO::class, $result);
    }

    public function test_generate_secret_stores_encrypted_secret_on_user(): void
    {
        $user = User::factory()->create();

        $result = $this->authenticator->generateSecret($user);

        $user->refresh();

        $this->assertNotNull($user->totp_secret_encrypted);
        $this->assertSame($result->secret, $user->totp_secret_encrypted);
    }

    public function test_generate_secret_stores_recovery_codes_on_user(): void
    {
        $user = User::factory()->create();

        $result = $this->authenticator->generateSecret($user);

        $user->refresh();

        $this->assertIsArray($user->recovery_codes_encrypted);
        $this->assertCount(8, $user->recovery_codes_encrypted);

        foreach ($result->recoveryCodes as $index => $plainCode) {
            $this->assertTrue(Hash::check($plainCode, $user->recovery_codes_encrypted[$index]));
        }
    }

    public function test_generate_secret_returns_valid_qr_code_url(): void
    {
        $user = User::factory()->create(['email' => 'admin@spu.edu.sy']);

        $result = $this->authenticator->generateSecret($user);

        $this->assertStringStartsWith('otpauth://totp/', $result->qrCodeUrl);
        $this->assertStringContainsString('admin%40spu.edu.sy', $result->qrCodeUrl);
        $this->assertStringContainsString('secret=', $result->qrCodeUrl);
    }

    public function test_generate_secret_returns_eight_recovery_codes(): void
    {
        $user = User::factory()->create();

        $result = $this->authenticator->generateSecret($user);

        $this->assertCount(8, $result->recoveryCodes);

        foreach ($result->recoveryCodes as $code) {
            $this->assertIsString($code);
            // Each code is bin2hex(8 bytes) = 16 hex chars.
            $this->assertMatchesRegularExpression('/^[0-9a-f]{16}$/', $code);
        }
    }

    public function test_generate_secret_produces_unique_codes(): void
    {
        $user = User::factory()->create();

        $result = $this->authenticator->generateSecret($user);

        $this->assertCount(
            count($result->recoveryCodes),
            array_unique($result->recoveryCodes),
            'Recovery codes should all be unique.',
        );
    }

    // ------------------------------------------------------------------
    // verify()
    // ------------------------------------------------------------------

    public function test_verify_accepts_valid_totp_code(): void
    {
        $user = User::factory()->create();
        $enrollment = $this->authenticator->generateSecret($user);

        $validCode = $this->google2fa->getCurrentOtp($enrollment->secret);

        $this->assertTrue($this->authenticator->verify($user, $validCode));
    }

    public function test_verify_rejects_invalid_totp_code(): void
    {
        $user = User::factory()->create();
        $this->authenticator->generateSecret($user);

        $this->assertFalse($this->authenticator->verify($user, '000000'));
    }

    public function test_verify_returns_false_when_no_secret_stored(): void
    {
        $user = User::factory()->create();

        $this->assertFalse($this->authenticator->verify($user, '123456'));
    }

    public function test_verify_enables_two_factor_on_first_valid_code(): void
    {
        $user = User::factory()->create();
        $enrollment = $this->authenticator->generateSecret($user);

        $user->refresh();

        $this->assertFalse((bool) $user->two_factor_enabled);
        $this->assertNull($user->two_factor_confirmed_at);

        $validCode = $this->google2fa->getCurrentOtp($enrollment->secret);
        $this->authenticator->verify($user, $validCode);

        $user->refresh();

        $this->assertTrue($user->two_factor_enabled);
        $this->assertNotNull($user->two_factor_confirmed_at);
    }

    public function test_verify_sets_confirmation_when_enabled_flag_is_true_but_confirmation_is_null(): void
    {
        $user = User::factory()->create([
            'two_factor_enabled' => true,
            'two_factor_confirmed_at' => null,
        ]);
        $enrollment = $this->authenticator->generateSecret($user);

        $validCode = $this->google2fa->getCurrentOtp($enrollment->secret);
        $this->authenticator->verify($user, $validCode);

        $user->refresh();

        $this->assertTrue($user->two_factor_enabled);
        $this->assertNotNull($user->two_factor_confirmed_at);
    }

    public function test_verify_does_not_reset_confirmation_on_subsequent_codes(): void
    {
        $user = User::factory()->create();
        $enrollment = $this->authenticator->generateSecret($user);

        $validCode = $this->google2fa->getCurrentOtp($enrollment->secret);
        $this->authenticator->verify($user, $validCode);

        $user->refresh();
        $firstConfirmedAt = $user->two_factor_confirmed_at;

        // Verify again — confirmation timestamp should not change.
        $this->travel(1)->seconds();
        $validCode = $this->google2fa->getCurrentOtp($enrollment->secret);
        $this->authenticator->verify($user, $validCode);

        $user->refresh();

        $this->assertTrue($user->two_factor_enabled);
        $this->assertEquals($firstConfirmedAt, $user->two_factor_confirmed_at);
    }

    // ------------------------------------------------------------------
    // generateRecoveryCodes()
    // ------------------------------------------------------------------

    public function test_generate_recovery_codes_returns_eight_codes(): void
    {
        $user = User::factory()->create();

        $codes = $this->authenticator->generateRecoveryCodes($user);

        $this->assertCount(8, $codes);

        foreach ($codes as $code) {
            $this->assertMatchesRegularExpression('/^[0-9a-f]{16}$/', $code);
        }
    }

    public function test_generate_recovery_codes_replaces_previous_codes(): void
    {
        $user = User::factory()->create();

        $firstSet = $this->authenticator->generateRecoveryCodes($user);
        $secondSet = $this->authenticator->generateRecoveryCodes($user);

        $user->refresh();

        foreach ($secondSet as $index => $plainCode) {
            $this->assertTrue(Hash::check($plainCode, $user->recovery_codes_encrypted[$index]));
        }

        $this->assertNotSame($firstSet, $secondSet);
    }

    // ------------------------------------------------------------------
    // verifyRecoveryCode()
    // ------------------------------------------------------------------

    public function test_verify_recovery_code_accepts_valid_code(): void
    {
        $user = User::factory()->create();
        $enrollment = $this->authenticator->generateSecret($user);

        $code = $enrollment->recoveryCodes[0];

        $this->assertTrue($this->authenticator->verifyRecoveryCode($user, $code));
    }

    public function test_verify_recovery_code_consumes_code_after_use(): void
    {
        $user = User::factory()->create();
        $enrollment = $this->authenticator->generateSecret($user);

        $code = $enrollment->recoveryCodes[0];

        $this->assertTrue($this->authenticator->verifyRecoveryCode($user, $code));

        // Refresh to get updated codes from DB.
        $user->refresh();

        // Same code should now be rejected (single-use).
        $this->assertFalse($this->authenticator->verifyRecoveryCode($user, $code));
    }

    public function test_verify_recovery_code_rejects_invalid_code(): void
    {
        $user = User::factory()->create();
        $this->authenticator->generateSecret($user);

        $this->assertFalse($this->authenticator->verifyRecoveryCode($user, 'not-a-real-code'));
    }

    public function test_verify_recovery_code_returns_false_when_no_codes_stored(): void
    {
        $user = User::factory()->create();

        $this->assertFalse($this->authenticator->verifyRecoveryCode($user, 'anything'));
    }

    public function test_verify_recovery_code_leaves_remaining_codes_intact(): void
    {
        $user = User::factory()->create();
        $enrollment = $this->authenticator->generateSecret($user);

        $codeToUse = $enrollment->recoveryCodes[0];
        $this->authenticator->verifyRecoveryCode($user, $codeToUse);

        $user->refresh();

        $remaining = $user->recovery_codes_encrypted;

        $this->assertCount(7, $remaining);
        $this->assertNotContains($codeToUse, $remaining);

        // All other original codes should still be accepted.
        foreach (array_slice($enrollment->recoveryCodes, 1) as $otherCode) {
            $this->assertTrue(collect($remaining)->contains(
                static fn (string $storedCode): bool => Hash::check($otherCode, $storedCode),
            ));
        }
    }
}
