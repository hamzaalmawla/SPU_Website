<?php

declare(strict_types=1);

namespace Tests\Feature\PX07;

use App\Models\LegacyExactRedirect;
use App\Models\LegacyPatternRule;
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
}
