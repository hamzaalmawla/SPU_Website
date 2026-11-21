<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Contracts\Auth\AuthServiceInterface;
use App\DTOs\Auth\LoginCredentialsDTO;
use App\Models\User\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Hashing\HashManager;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Lockout expiry, unlock paths, constant-cost rejection and login throttling.
 */
final class AdminLoginHardeningTest extends TestCase
{
    use RefreshDatabase;

    public function test_fifth_failure_sets_an_automatic_lock_that_expires_after_the_decay_window(): void
    {
        config(['auth.lockout.decay_minutes' => 30]);
        $user = $this->editor('expiring@example.com');

        $this->failTimes('expiring@example.com', 5);

        $user->refresh();
        $this->assertTrue($user->is_locked);
        $this->assertNotNull($user->locked_until);
        $this->assertEqualsWithDelta(now()->addMinutes(30)->getTimestamp(), $user->locked_until->getTimestamp(), 5);
        $this->assertDatabaseHas('audit_logs', ['action' => 'user.locked', 'entity_id' => $user->id]);

        $this->travel(29)->minutes();
        $this->assertFalse($this->attempt('expiring@example.com', 'correct-password'), 'The lock must hold until it expires.');

        $this->travel(2)->minutes();
        $this->assertTrue($this->attempt('expiring@example.com', 'correct-password'), 'An expired lock must not block a correct password.');

        $user->refresh();
        $this->assertFalse($user->is_locked);
        $this->assertNull($user->locked_at);
        $this->assertNull($user->locked_until);
        $this->assertSame(0, $user->failed_login_attempts);
        $this->assertSame(0, $user->failed_attempts);
        $this->assertDatabaseHas('audit_logs', ['action' => 'user.unlocked', 'entity_id' => $user->id]);
    }

    public function test_an_expired_lock_starts_a_fresh_failure_count(): void
    {
        config(['auth.lockout.decay_minutes' => 15]);
        $user = $this->editor('fresh-count@example.com');

        $this->failTimes('fresh-count@example.com', 5);
        $this->travel(16)->minutes();
        $this->failTimes('fresh-count@example.com', 1);

        $user->refresh();
        $this->assertFalse($user->isAccountLocked(), 'One typo after a lock expires must not lock the account again.');
        $this->assertSame(1, $user->failed_login_attempts);
    }

    public function test_zero_decay_keeps_automatic_locks_until_lifted(): void
    {
        config(['auth.lockout.decay_minutes' => 0]);
        $user = $this->editor('permanent@example.com');

        $this->failTimes('permanent@example.com', 5);
        $this->travel(30)->days();

        $user->refresh();
        $this->assertNull($user->locked_until);
        $this->assertTrue($user->isAccountLocked());
        $this->assertFalse($this->attempt('permanent@example.com', 'correct-password'));
    }

    public function test_a_lock_set_by_an_administrator_never_expires(): void
    {
        $admin = $this->superAdmin();
        $user = $this->editor('manual@example.com');

        $this->assertTrue(app(AuthServiceInterface::class)->updateUser((int) $user->id, ['is_locked' => true], (int) $admin->id));

        $user->refresh();
        $this->assertTrue($user->is_locked);
        $this->assertNull($user->locked_until);

        $this->travel(365)->days();
        $this->assertFalse($this->attempt('manual@example.com', 'correct-password'));
    }

    public function test_unlocking_in_the_panel_resets_the_failure_count(): void
    {
        $admin = $this->superAdmin();
        $user = $this->editor('unlocked@example.com');
        $this->failTimes('unlocked@example.com', 5);

        $this->assertTrue(app(AuthServiceInterface::class)->updateUser((int) $user->id, ['is_locked' => false], (int) $admin->id));

        $user->refresh();
        $this->assertFalse($user->isAccountLocked());
        $this->assertSame(0, $user->failed_login_attempts);
        $this->assertSame(0, $user->failed_attempts);

        $this->failTimes('unlocked@example.com', 1);
        $this->assertFalse($user->refresh()->isAccountLocked(), 'A single wrong password right after an unlock must not relock.');
    }

    public function test_saving_a_user_whose_lock_has_expired_does_not_turn_it_into_a_permanent_lock(): void
    {
        config(['auth.lockout.decay_minutes' => 10]);
        $admin = $this->superAdmin();
        $user = $this->editor('stale-toggle@example.com');
        $this->failTimes('stale-toggle@example.com', 5);
        $this->travel(11)->minutes();

        // The edit form shows the live (expired) state, so it posts is_locked = false.
        $this->assertTrue(app(AuthServiceInterface::class)->updateUser((int) $user->id, ['name' => 'Renamed', 'is_locked' => false], (int) $admin->id));

        $user->refresh();
        $this->assertFalse($user->is_locked);
        $this->assertNull($user->locked_at);
        $this->assertTrue($this->attempt('stale-toggle@example.com', 'correct-password'));
    }

    public function test_unlock_command_lifts_a_lock_and_audits_it(): void
    {
        config(['auth.lockout.decay_minutes' => 0]);
        $user = $this->editor('console@example.com');
        $this->failTimes('console@example.com', 5);

        $this->artisan('auth:unlock', ['email' => ' Console@Example.com '])
            ->expectsOutputToContain('unlocked')
            ->assertSuccessful();

        $user->refresh();
        $this->assertFalse($user->isAccountLocked());
        $this->assertSame(0, $user->failed_login_attempts);
        $this->assertDatabaseHas('audit_logs', ['action' => 'user.unlocked', 'entity_id' => $user->id, 'actor_user_id' => null]);
        $this->assertTrue($this->attempt('console@example.com', 'correct-password'));
    }

    public function test_unlock_command_fails_for_an_unknown_email(): void
    {
        $this->artisan('auth:unlock', ['email' => 'nobody@example.com'])->assertFailed();
    }

    /**
     * An unknown email, a locked account and an account without admin access
     * must cost the same hashing work as a wrong password, or response time
     * tells an attacker which staff emails exist.
     */
    public function test_every_rejected_sign_in_runs_one_password_hash(): void
    {
        $hasher = new class($this->app) extends HashManager
        {
            public int $operations = 0;

            public function make(#[\SensitiveParameter] $value, array $options = []): string
            {
                $this->operations++;

                return parent::make($value, $options);
            }

            public function check(#[\SensitiveParameter] $value, $hashedValue, array $options = []): bool
            {
                $this->operations++;

                return parent::check($value, $hashedValue, $options);
            }
        };
        $this->app->instance('hash', $hasher);
        Hash::clearResolvedInstance('hash');

        $this->editor('real@example.com');
        User::factory()->create(['email' => 'locked@example.com', 'password' => 'correct-password', 'role_slug' => 'editor', 'is_locked' => true, 'locked_at' => now()]);
        User::factory()->create(['email' => 'visitor@example.com', 'password' => 'correct-password', 'role_slug' => 'viewer']);

        foreach (['unknown@example.com', 'locked@example.com', 'visitor@example.com', 'real@example.com'] as $email) {
            $before = $hasher->operations;

            $this->assertFalse($this->attempt($email, 'wrong-password'));

            $this->assertSame(1, $hasher->operations - $before, "Rejecting {$email} must run exactly one password hash.");
        }
    }

    public function test_rejected_sign_ins_take_at_least_the_timebox(): void
    {
        config(['auth.timebox_duration' => 120_000]);
        User::factory()->create(['email' => 'locked@example.com', 'password' => 'correct-password', 'role_slug' => 'editor', 'is_locked' => true, 'locked_at' => now()]);

        foreach (['unknown@example.com', 'locked@example.com'] as $email) {
            $started = microtime(true);
            $this->assertFalse($this->attempt($email, 'wrong-password'));

            $this->assertGreaterThanOrEqual(0.12, microtime(true) - $started, "Rejecting {$email} returned before the timebox.");
        }
    }

    public function test_a_successful_sign_in_is_not_held_back_by_the_timebox(): void
    {
        config(['auth.timebox_duration' => 2_000_000]);
        $this->editor('quick@example.com');

        $started = microtime(true);
        $this->assertTrue($this->attempt('quick@example.com', 'correct-password'));

        $this->assertLessThan(1.5, microtime(true) - $started);
    }

    public function test_login_throttle_limits_one_client_spraying_many_accounts(): void
    {
        foreach (range(1, 20) as $index) {
            $this->post('/admin/login', ['email' => "spray{$index}@example.com", 'password' => 'Summer2026!'])->assertRedirect();
        }

        $this->post('/admin/login', ['email' => 'spray21@example.com', 'password' => 'Summer2026!'])->assertTooManyRequests();
    }

    public function test_login_throttle_still_limits_guesses_at_one_account(): void
    {
        foreach (range(1, 5) as $attempt) {
            $this->post('/admin/login', ['email' => 'target@example.com', 'password' => 'guess-'.$attempt])->assertRedirect();
        }

        $this->post('/admin/login', ['email' => 'TARGET@example.com', 'password' => 'guess-6'])->assertTooManyRequests();
    }

    public function test_webhook_endpoint_is_throttled_before_signature_checks(): void
    {
        foreach (range(1, 60) as $attempt) {
            $this->postJson('/webhook/incoming', [])->assertForbidden();
        }

        $this->postJson('/webhook/incoming', [])->assertTooManyRequests();
    }

    private function editor(string $email): User
    {
        return User::factory()->create([
            'email' => $email,
            'password' => 'correct-password',
            'role_slug' => 'editor',
        ]);
    }

    private function superAdmin(): User
    {
        return User::factory()->create([
            'email' => 'root-'.uniqid().'@example.com',
            'password' => 'correct-password',
            'role_slug' => 'super_admin',
        ]);
    }

    private function attempt(string $email, string $password): bool
    {
        $authenticated = app(AuthServiceInterface::class)->attempt(new LoginCredentialsDTO($email, $password, false));

        if ($authenticated) {
            auth()->guard('web')->logout();
        }

        return $authenticated;
    }

    private function failTimes(string $email, int $times): void
    {
        foreach (range(1, $times) as $attempt) {
            $this->assertFalse($this->attempt($email, 'wrong-password-'.$attempt));
        }
    }
}
