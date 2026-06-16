<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Contracts\Auth\AuthServiceInterface;
use App\DTOs\Auth\LoginCredentialsDTO;
use App\Models\User\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\Support\PropertyTestHelpers;
use Tests\TestCase;

/**
 * Property-based tests for locked account authentication rejection.
 *
 * Feature: codebase-audit-remediation, Property 6: Locked Account Authentication Rejection
 *
 * For any user with is_locked = true and any password (correct or incorrect),
 * authentication SHALL be rejected, ensuring locked accounts cannot gain access
 * regardless of credential validity.
 *
 * **Validates: Requirements 18.3**
 */
#[Group('property')]
class LockedAccountPropertyTest extends TestCase
{
    use PropertyTestHelpers;
    use RefreshDatabase;

    private AuthServiceInterface $authService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->authService = app(AuthServiceInterface::class);
    }

    // ──────────────────────────────────────────────────────────────────────
    // Constants
    // ──────────────────────────────────────────────────────────────────────

    private const ROLES = ['super_admin', 'editor', 'faculty_editor'];

    private const PASSWORD_CHARS = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789!@#$%^&*';

    // ──────────────────────────────────────────────────────────────────────
    // Generators
    // ──────────────────────────────────────────────────────────────────────

    /**
     * Generate a random password string.
     */
    private static function randomPassword(): string
    {
        $length = random_int(8, 24);
        $password = '';

        for ($i = 0; $i < $length; $i++) {
            $password .= self::PASSWORD_CHARS[random_int(0, strlen(self::PASSWORD_CHARS) - 1)];
        }

        return $password;
    }

    /**
     * Generate a random email address.
     */
    private static function randomEmail(): string
    {
        $localPart = self::randomSlugSegment();

        return $localPart . '@example.com';
    }

    // ──────────────────────────────────────────────────────────────────────
    // Data Providers
    // ──────────────────────────────────────────────────────────────────────

    /**
     * Generate locked user scenarios with random passwords.
     *
     * Each case provides: [role, email, actualPassword, attemptPassword, useCorrectPassword]
     *
     * @return array<string, array{0: string, 1: string, 2: string, 3: string, 4: bool}>
     */
    public static function lockedAccountProvider(): array
    {
        $cases = [];

        for ($i = 0; $i < 20; $i++) {
            $role = self::ROLES[random_int(0, count(self::ROLES) - 1)];
            $email = self::randomEmail();
            $actualPassword = self::randomPassword();
            $useCorrectPassword = random_int(0, 1) === 1;
            $attemptPassword = $useCorrectPassword ? $actualPassword : self::randomPassword();

            $cases["locked_iteration_{$i}"] = [
                $role,
                $email,
                $actualPassword,
                $attemptPassword,
                $useCorrectPassword,
            ];
        }

        return $cases;
    }

    // ──────────────────────────────────────────────────────────────────────
    // Property 6 — Locked Account Authentication Rejection
    // ──────────────────────────────────────────────────────────────────────

    /**
     * Property 6: For any user with is_locked = true and any password,
     * authentication is rejected.
     *
     * **Validates: Requirements 18.3**
     */
    #[Test]
    #[DataProvider('lockedAccountProvider')]
    public function locked_account_authentication_is_always_rejected(
        string $role,
        string $email,
        string $actualPassword,
        string $attemptPassword,
        bool $useCorrectPassword,
    ): void {
        // Create a locked user
        $user = User::factory()->create([
            'email' => $email,
            'password' => $actualPassword,
            'role_slug' => $role,
            'is_locked' => true,
            'locked_at' => now(),
            'failed_login_attempts' => 5,
            'failed_attempts' => 5,
        ]);

        // Attempt authentication with the given password
        $result = $this->authService->attempt(new LoginCredentialsDTO(
            email: $email,
            password: $attemptPassword,
        ));

        $this->assertFalse(
            $result,
            sprintf(
                'Locked account (%s, role=%s) must reject authentication with %s password',
                $email,
                $role,
                $useCorrectPassword ? 'correct' : 'incorrect',
            )
        );

        // Verify the user is still not authenticated
        $this->assertGuest('web');

        // Verify the account remains locked
        $user->refresh();
        $this->assertTrue(
            $user->is_locked,
            'Account must remain locked after rejected authentication attempt'
        );
    }
}
