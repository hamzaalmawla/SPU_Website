<?php

declare(strict_types=1);

namespace App\Services;

use App\Contracts\TotpAuthenticatorInterface;
use App\DTOs\TotpEnrollmentDTO;
use App\Models\User;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\Hash;
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
final class TotpAuthenticator implements TotpAuthenticatorInterface
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
    public function generateSecret(Authenticatable $user): TotpEnrollmentDTO
    {
        $user = $this->assertUserModel($user);
        $secret = $this->google2fa->generateSecretKey();

        $recoveryCodes = $this->buildRecoveryCodes();

        $user->forceFill([
            'totp_secret_encrypted' => $secret,
            'recovery_codes_encrypted' => $this->hashRecoveryCodes($recoveryCodes),
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
    public function verify(Authenticatable $user, string $code): bool
    {
        if (! $user instanceof User) {
            return false;
        }

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
    public function generateRecoveryCodes(Authenticatable $user): array
    {
        $user = $this->assertUserModel($user);
        $codes = $this->buildRecoveryCodes();

        $user->forceFill([
            'recovery_codes_encrypted' => $this->hashRecoveryCodes($codes),
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
    public function verifyRecoveryCode(Authenticatable $user, string $code): bool
    {
        if (! $user instanceof User) {
            return false;
        }

        /** @var list<string>|null $storedCodes */
        $storedCodes = $user->recovery_codes_encrypted;

        if (! is_array($storedCodes) || $storedCodes === []) {
            return false;
        }

        $index = null;

        foreach ($storedCodes as $candidateIndex => $storedCode) {
            if (! is_string($storedCode)) {
                continue;
            }

            if ($this->recoveryCodeMatches($code, $storedCode)) {
                $index = $candidateIndex;
                break;
            }
        }

        if ($index === null) {
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

    /**
     * @param  list<string>  $codes
     * @return list<string>
     */
    private function hashRecoveryCodes(array $codes): array
    {
        return array_values(array_map(
            static fn (string $code): string => Hash::make($code),
            $codes,
        ));
    }

    private function recoveryCodeMatches(string $plainCode, string $storedCode): bool
    {
        if (hash_equals($storedCode, $plainCode)) {
            return true;
        }

        $hashInfo = password_get_info($storedCode);

        return ($hashInfo['algo'] ?? 0) !== 0 && Hash::check($plainCode, $storedCode);
    }

    private function assertUserModel(Authenticatable $user): User
    {
        if (! $user instanceof User) {
            throw new \InvalidArgumentException('TOTP authentication requires the application user model.');
        }

        return $user;
    }
}
