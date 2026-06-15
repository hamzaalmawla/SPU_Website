<?php

declare(strict_types=1);

namespace Tests\Feature\PX05;

use App\Models\LegacyExactRedirect;
use App\Models\LegacyPatternRule;
use App\Models\UnresolvedLegacyRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Feature tests for redirect continuity middleware.
 *
 * Requirements: 17.1, 17.2, 17.3, 17.4, 17.5
 */
class RedirectContinuityTest extends TestCase
{
    use RefreshDatabase;

    public function test_exact_match_returns_301_redirect(): void
    {
        LegacyExactRedirect::create([
            'legacy_path' => '/old-about',
            'destination_url' => '/ar/about',
            'status_code' => 301,
            'is_active' => true,
        ]);

        $response = $this->get('/old-about');

        $response->assertRedirect('/ar/about');
        $response->assertStatus(301);
    }

    public function test_exact_match_with_unsafe_external_destination_is_blocked(): void
    {
        LegacyExactRedirect::create([
            'legacy_path' => '/unsafe-external',
            'destination_url' => 'https://evil.example/phishing',
            'status_code' => 301,
            'is_active' => true,
        ]);

        $this->get('/unsafe-external')->assertNotFound();
    }

    public function test_exact_match_with_unsafe_scheme_destination_is_blocked(): void
    {
        LegacyExactRedirect::create([
            'legacy_path' => '/unsafe-scheme',
            'destination_url' => 'javascript:alert(1)',
            'status_code' => 301,
            'is_active' => true,
        ]);

        $this->get('/unsafe-scheme')->assertNotFound();
    }

    public function test_pattern_match_returns_301_redirect(): void
    {
        LegacyPatternRule::create([
            'pattern' => '#^/faculty/(.+)$#',
            'replacement' => '/ar/faculties/$1',
            'status_code' => 301,
            'priority' => 100,
            'is_active' => true,
        ]);

        $response = $this->get('/faculty/engineering');

        $response->assertRedirect('/ar/faculties/engineering');
        $response->assertStatus(301);
    }

    public function test_no_match_passes_through_to_normal_routing(): void
    {
        $response = $this->get('/nonexistent-legacy-path-xyz');

        // Should pass through middleware and hit normal routing (404)
        $response->assertNotFound();
    }

    public function test_unresolved_request_is_logged(): void
    {
        $this->get('/some-missing-page');

        $this->assertDatabaseHas('unresolved_legacy_requests', [
            'method' => 'GET',
            'request_type' => 'page',
        ]);
    }

    public function test_repeated_unresolved_request_increments_hit_count(): void
    {
        $this->get('/same-missing-page');
        $this->get('/same-missing-page');

        $record = UnresolvedLegacyRequest::query()
            ->where('method', 'GET')
            ->latest('id')
            ->first();

        $this->assertNotNull($record);
        $this->assertSame(1, UnresolvedLegacyRequest::query()->count());
        $this->assertSame(2, $record->hit_count);
    }

    public function test_admin_prefix_is_skipped_by_middleware(): void
    {
        // Admin routes should not be processed by redirect middleware
        LegacyExactRedirect::create([
            'legacy_path' => '/admin/login',
            'destination_url' => '/ar/admin-redirect',
            'status_code' => 301,
            'is_active' => true,
        ]);

        $response = $this->get('/admin/login');

        // Should NOT redirect — admin prefix is skipped
        $this->assertNotEquals(301, $response->getStatusCode());
    }

    public function test_livewire_prefix_is_skipped_by_middleware(): void
    {
        LegacyExactRedirect::create([
            'legacy_path' => '/livewire/something',
            'destination_url' => '/ar/livewire-redirect',
            'status_code' => 301,
            'is_active' => true,
        ]);

        $response = $this->get('/livewire/something');

        $this->assertNotEquals(301, $response->getStatusCode());
    }

    public function test_filament_prefix_is_skipped_by_middleware(): void
    {
        LegacyExactRedirect::create([
            'legacy_path' => '/filament/resource',
            'destination_url' => '/ar/filament-redirect',
            'status_code' => 301,
            'is_active' => true,
        ]);

        $response = $this->get('/filament/resource');

        $this->assertNotEquals(301, $response->getStatusCode());
    }

    public function test_redirect_loops_terminate_within_max_hops(): void
    {
        // Create a loop: /a -> /b -> /c -> /a
        LegacyExactRedirect::create([
            'legacy_path' => '/loop-a',
            'destination_url' => '/loop-b',
            'status_code' => 301,
            'is_active' => true,
        ]);

        LegacyExactRedirect::create([
            'legacy_path' => '/loop-b',
            'destination_url' => '/loop-c',
            'status_code' => 301,
            'is_active' => true,
        ]);

        LegacyExactRedirect::create([
            'legacy_path' => '/loop-c',
            'destination_url' => '/loop-a',
            'status_code' => 301,
            'is_active' => true,
        ]);

        $response = $this->get('/loop-a');

        // Should terminate (not hang) and return a redirect to the last valid destination
        $this->assertContains($response->getStatusCode(), [301, 302]);
    }
}
