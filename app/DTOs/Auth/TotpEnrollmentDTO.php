<?php

declare(strict_types=1);

namespace App\DTOs\Auth;

/**
 * Enrollment payload returned when a user enables TOTP two-factor authentication.
 *
 * Contains the shared secret, a provisioning URI for QR code generation,
 * and a set of one-time recovery codes.
 */
final readonly class TotpEnrollmentDTO
{
    /**
     * @param  string        $secret        The TOTP shared secret (base32-encoded).
     * @param  string        $qrCodeUrl     The otpauth:// provisioning URI for QR display.
     * @param  list<string>  $recoveryCodes One-time recovery codes for backup access.
     */
    public function __construct(
        public string $secret,
        public string $qrCodeUrl,
        public array $recoveryCodes,
    ) {}
}
