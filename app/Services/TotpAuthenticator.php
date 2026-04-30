<?php

declare(strict_types=1);

namespace App\Services;

use App\DTOs\TotpEnrollmentDTO;
use App\Models\User;
use PragmaRX\Google2FA\Google2FA;

/**
 * TOTP two-factor authentication service.
 *
 * Handles secret generation, code verification, and one-time recovery codes
 * for admin accounts. Uses the pragmarx/google2fa library for TOTP operations.
 *
 * Sensitive fields (totp_secret_encrypted, recovery_codes_encrypted) are
 * written via forceFill() because they are intentionally excluded from
 * the User model's $fillable array.
 */
final class TotpAuthenticator
{
    private const RECOVERY_CODE_COUNT = 8;

    private const APP_NAME = 'SPU Admin';

    public function __construct(
        private readonly Google2FA $google2fa,
    ) {}

    /**
     * Generate a new TOTP secret for the user and return enrollment data.
     *
     * Stores the encrypted secret and a fresh set of recovery codes on the
     * user record. Returns a DTO containing the secret, a provisioning URI
     * for QR code display, and the plaintext recovery codes (shown once).
     */
    public function generateSecret(User $user): TotpEnrollmentDTO
    {
        $secret = $this->google2fa->generateSecretKey();

        $recoveryCodes = $this->buildRecoveryCodes();

        $user->forceFill([
            'totp_secret_encrypted' => $secret,
            'recovery_codes_encrypted' => $recoveryCodes,
        ])->save();

        $qrCodeUrl = $this->google2fa->getQRCodeUrl(
            self::APP_NAME,
            (string) $user->email,
            $secret,
        );

        return new TotpEnrollmentDTO(
            secret: $secret,
            qrCodeUrl: $qrCodeUrl,
            recoveryCodes: $recoveryCodes,
        );
    }

    /**
     * Verify a TOTP code against the user's stored secret.
     *
     * On first successful verification (2FA not yet confirmed), the method
     * enables 2FA and records the confirmation timestamp.
     *
     * @return bool True when the code is valid.
     */
    public function verify(User $user, string $code): bool
    {
        $secret = $user->totp_secret_encrypted;

        if (! is_string($secret) || $secret === '') {
            return false;
        }

        $valid = (bool) $this->google2fa->verifyKey($secret, $code);

        if ($valid && ! $user->two_factor_enabled) {
            $user->forceFill([
                'two_factor_enabled' => true,
                'two_factor_confirmed_at' => now(),
            ])->save();
        }

        return $valid;
    }

    /**
     * Generate a fresh set of recovery codes for the user.
     *
     * Replaces any previously stored codes. The plaintext codes are returned
     * exactly once for the user to store securely.
     *
     * @return list<string> The new recovery codes.
     */
    public function generateRecoveryCodes(User $user): array
    {
        $codes = $this->buildRecoveryCodes();

        $user->forceFill([
            'recovery_codes_encrypted' => $codes,
        ])->save();

        return $codes;
    }

    /**
     * Verify a one-time recovery code and consume it.
     *
     * If the code matches one of the stored recovery codes, it is removed
     * from the set so it cannot be reused.
     *
     * @return bool True when the code was valid and consumed.
     */
    public function verifyRecoveryCode(User $user, string $code): bool
    {
        /** @var list<string>|null $storedCodes */
        $storedCodes = $user->recovery_codes_encrypted;

        if (! is_array($storedCodes) || $storedCodes === []) {
            return false;
        }

        $index = array_search($code, $storedCodes, true);

        if ($index === false) {
            return false;
        }

        // Remove the consumed code and re-index.
        array_splice($storedCodes, (int) $index, 1);

        $user->forceFill([
            'recovery_codes_encrypted' => array_values($storedCodes),
        ])->save();

        return true;
    }

    /**
     * Build a fresh set of random recovery codes.
     *
     * @return list<string>
     */
    private function buildRecoveryCodes(): array
    {
        $codes = [];

        for ($i = 0; $i < self::RECOVERY_CODE_COUNT; $i++) {
            $codes[] = bin2hex(random_bytes(8));
        }

        return $codes;
    }
}
