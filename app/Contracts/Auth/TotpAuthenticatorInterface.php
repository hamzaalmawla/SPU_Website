<?php

declare(strict_types=1);

namespace App\Contracts\Auth;

use App\DTOs\Auth\TotpEnrollmentDTO;
use Illuminate\Contracts\Auth\Authenticatable;

interface TotpAuthenticatorInterface
{
    public function generateSecret(Authenticatable $user): TotpEnrollmentDTO;

    public function verify(Authenticatable $user, string $code): bool;

    /**
     * @return list<string>
     */
    public function generateRecoveryCodes(Authenticatable $user): array;

    public function verifyRecoveryCode(Authenticatable $user, string $code): bool;

    public function disableTwoFactor(Authenticatable $user): bool;
}
