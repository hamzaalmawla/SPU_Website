<?php

declare(strict_types=1);

namespace App\DTOs;

/**
 * Structured admin login input for the auth service.
 */
final readonly class LoginCredentialsDTO
{
    public function __construct(
        public string $email,
        public string $password,
        public bool $remember = false,
    ) {}
}
