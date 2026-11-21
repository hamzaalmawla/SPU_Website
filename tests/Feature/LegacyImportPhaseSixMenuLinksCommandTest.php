<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class LegacyImportPhaseSixMenuLinksCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_command_runs_menu_link_dry_run(): void
    {
        $this->artisan('legacy-import:phase6-menu-links --batch=test-batch')
            ->expectsOutputToContain('Phase 6 Menu Link Import')
            ->expectsOutputToContain('Written: no')
            ->assertSuccessful();
    }
}
