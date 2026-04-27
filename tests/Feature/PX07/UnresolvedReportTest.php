<?php

declare(strict_types=1);

namespace Tests\Feature\PX07;

use App\Models\UnresolvedLegacyRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Feature tests for continuity:report-unresolved command.
 *
 * Requirements: 27.4
 */
class UnresolvedReportTest extends TestCase
{
    use RefreshDatabase;

    public function test_command_reports_all_unresolved_requests(): void
    {
        $this->seedUnresolvedRequests();

        $this->artisan('continuity:report-unresolved', ['--format' => 'json'])
            ->assertSuccessful()
            ->expectsOutputToContain('"total": 3');
    }

    public function test_command_filters_by_type(): void
    {
        $this->seedUnresolvedRequests();

        $this->artisan('continuity:report-unresolved', ['--type' => 'file', '--format' => 'json'])
            ->assertSuccessful()
            ->expectsOutputToContain('"request_type": "file"');
    }

    public function test_command_filters_by_since(): void
    {
        $this->seedUnresolvedRequests();

        // Filter to only recent entries
        $this->artisan('continuity:report-unresolved', [
            '--since' => now()->subHour()->toIso8601String(),
            '--format' => 'json',
        ])->assertSuccessful();
    }

    public function test_command_handles_empty_results(): void
    {
        $this->artisan('continuity:report-unresolved', ['--format' => 'json'])
            ->assertSuccessful()
            ->expectsOutputToContain('"total": 0');
    }

    private function seedUnresolvedRequests(): void
    {
        UnresolvedLegacyRequest::insert([
            [
                'url' => '/old-page-1',
                'method' => 'GET',
                'request_type' => 'page',
                'hit_count' => 5,
                'first_seen_at' => now()->subDay(),
                'last_seen_at' => now(),
                'created_at' => now(),
            ],
            [
                'url' => '/old-page-2',
                'method' => 'GET',
                'request_type' => 'page',
                'hit_count' => 2,
                'first_seen_at' => now()->subHours(2),
                'last_seen_at' => now(),
                'created_at' => now(),
            ],
            [
                'url' => '/files/old-doc.pdf',
                'method' => 'GET',
                'request_type' => 'file',
                'hit_count' => 1,
                'first_seen_at' => now(),
                'last_seen_at' => now(),
                'created_at' => now(),
            ],
        ]);
    }
}
