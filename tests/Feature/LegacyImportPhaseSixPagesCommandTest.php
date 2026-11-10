<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class LegacyImportPhaseSixPagesCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_command_runs_pages_dry_run(): void
    {
        $this->artisan('legacy-import:phase6-pages --batch=pages-test')
            ->expectsOutputToContain('Phase 6 Page Import')
            ->expectsOutputToContain('Written: no')
            ->assertSuccessful();
    }
}
