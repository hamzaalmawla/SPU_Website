<?php

declare(strict_types=1);

namespace Tests\Feature\PX07;

use App\Models\Legacy\LegacyExactRedirect;
use App\Models\Legacy\LegacyPatternRule;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Feature tests for continuity:validate-redirects command.
 *
 * Requirements: 27.2
 */
class RedirectValidationTest extends TestCase
{
    use RefreshDatabase;

    public function test_command_reports_no_issues_with_valid_rules(): void
    {
        LegacyExactRedirect::create([
            'legacy_path' => '/valid-path',
            'destination_url' => '/ar/valid-dest',
            'status_code' => 301,
            'is_active' => true,
        ]);

        $this->artisan('continuity:validate-redirects')
            ->assertSuccessful();
    }

    public function test_command_detects_duplicate_exact_rules(): void
    {
        LegacyExactRedirect::create([
            'legacy_path' => '/duplicate-path',
            'destination_url' => '/ar/dest-a',
            'status_code' => 301,
            'is_active' => true,
        ]);

        LegacyExactRedirect::create([
            'legacy_path' => '/duplicate-path',
            'destination_url' => '/ar/dest-b',
            'status_code' => 301,
            'is_active' => true,
        ]);

        $this->artisan('continuity:validate-redirects')
            ->assertFailed();
    }

    public function test_command_detects_conflicting_pattern_rules(): void
    {
        LegacyPatternRule::create([
            'pattern' => '#^/conflict/(.+)$#',
            'replacement' => '/ar/conflict-a/$1',
            'status_code' => 301,
            'priority' => 100,
            'is_active' => true,
        ]);

        LegacyPatternRule::create([
            'pattern' => '#^/conflict/(.+)$#',
            'replacement' => '/en/conflict-b/$1',
            'status_code' => 301,
            'priority' => 101,
            'is_active' => true,
        ]);

        $this->artisan('continuity:validate-redirects')
            ->assertFailed();
    }

    public function test_fix_flag_deactivates_duplicate_rules(): void
    {
        $first = LegacyExactRedirect::create([
            'legacy_path' => '/fix-dup',
            'destination_url' => '/ar/fix-a',
            'status_code' => 301,
            'is_active' => true,
        ]);

        $second = LegacyExactRedirect::create([
            'legacy_path' => '/fix-dup',
            'destination_url' => '/ar/fix-b',
            'status_code' => 301,
            'is_active' => true,
        ]);

        $this->artisan('continuity:validate-redirects', ['--fix' => true])
            ->assertFailed();

        // First rule should remain active, second should be deactivated
        $this->assertTrue((bool) $first->fresh()->is_active);
        $this->assertFalse((bool) $second->fresh()->is_active);
    }

    public function test_command_detects_unsafe_redirect_destinations(): void
    {
        LegacyExactRedirect::create([
            'legacy_path' => '/unsafe-exact',
            'destination_url' => 'https://evil.example/phishing',
            'status_code' => 301,
            'is_active' => true,
        ]);

        LegacyPatternRule::create([
            'pattern' => '#^/unsafe-pattern/(.+)$#',
            'replacement' => 'javascript:alert(1)',
            'status_code' => 301,
            'priority' => 100,
            'is_active' => true,
        ]);

        $this->artisan('continuity:validate-redirects')
            ->assertFailed();
    }

    public function test_fix_flag_deactivates_unsafe_redirect_destinations(): void
    {
        $exact = LegacyExactRedirect::create([
            'legacy_path' => '/fix-unsafe-exact',
            'destination_url' => 'https://evil.example/phishing',
            'status_code' => 301,
            'is_active' => true,
        ]);

        $pattern = LegacyPatternRule::create([
            'pattern' => '#^/fix-unsafe-pattern/(.+)$#',
            'replacement' => 'javascript:alert(1)',
            'status_code' => 301,
            'priority' => 100,
            'is_active' => true,
        ]);

        $this->artisan('continuity:validate-redirects', ['--fix' => true])
            ->assertFailed();

        $this->assertFalse((bool) $exact->fresh()->is_active);
        $this->assertFalse((bool) $pattern->fresh()->is_active);
    }

    public function test_probe_flag_passes_when_every_destination_answers(): void
    {
        $this->seed(DatabaseSeeder::class);

        LegacyExactRedirect::create([
            'legacy_path' => '/probe-ok',
            'destination_url' => '/ar/news',
            'status_code' => 301,
            'is_active' => true,
        ]);

        $this->artisan('continuity:validate-redirects', ['--probe' => true])
            ->assertSuccessful();
    }

    public function test_probe_flag_reports_a_destination_that_does_not_answer(): void
    {
        // The failure the maintenance guide forbids: a well-formed rule whose
        // destination is a 404. Rule validation alone cannot see this.
        LegacyExactRedirect::create([
            'legacy_path' => '/probe-broken',
            'destination_url' => '/ar/this-page-does-not-exist',
            'status_code' => 301,
            'is_active' => true,
        ]);

        $this->artisan('continuity:validate-redirects', ['--probe' => true])
            ->expectsOutputToContain('/ar/this-page-does-not-exist')
            ->assertFailed();
    }

    public function test_probe_flag_ignores_inactive_rules(): void
    {
        LegacyExactRedirect::create([
            'legacy_path' => '/probe-inactive',
            'destination_url' => '/ar/this-page-does-not-exist',
            'status_code' => 301,
            'is_active' => false,
        ]);

        $this->artisan('continuity:validate-redirects', ['--probe' => true])
            ->assertSuccessful();
    }

    public function test_probe_flag_is_opt_in(): void
    {
        LegacyExactRedirect::create([
            'legacy_path' => '/probe-not-requested',
            'destination_url' => '/ar/this-page-does-not-exist',
            'status_code' => 301,
            'is_active' => true,
        ]);

        // Without --probe the command only validates rule integrity, so a broken
        // destination must not change its result.
        $this->artisan('continuity:validate-redirects')
            ->assertSuccessful();
    }
}
