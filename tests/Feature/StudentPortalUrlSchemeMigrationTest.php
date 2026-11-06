<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Contracts\Settings\SettingsServiceInterface;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * The production 503 was a stored http:// portal URL against an https-only policy.
 * This covers the data migration that corrects it, including the guard that stops
 * it overwriting a fix already applied through the admin panel.
 */
final class StudentPortalUrlSchemeMigrationTest extends TestCase
{
    use RefreshDatabase;

    private const BROKEN = 'http://my.spu.edu.sy/ar/login';

    private const FIXED = 'https://my.spu.edu.sy/ar/login';

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);
    }

    public function test_it_rewrites_the_broken_http_value_to_https(): void
    {
        $this->setStoredPortalUrl(self::BROKEN);

        // Precondition: this is exactly the production symptom.
        cache()->flush();
        $this->assertNull(
            app(SettingsServiceInterface::class)->getStudentPortalUrl(),
            'An http:// portal URL must resolve to null - that is what triggers the 503.',
        );
        $this->get('/en/campus-life/transport/registration')->assertStatus(503);

        $this->runMigration();

        $this->assertSame(self::FIXED, $this->storedPortalUrl());

        cache()->flush();
        $this->assertSame(self::FIXED, app(SettingsServiceInterface::class)->getStudentPortalUrl());
        $this->get('/en/campus-life/transport/registration')->assertRedirect(self::FIXED);
    }

    public function test_it_does_not_overwrite_a_value_already_corrected_by_hand(): void
    {
        $manualFix = 'https://my.spu.edu.sy/en/login';
        $this->setStoredPortalUrl($manualFix);

        $this->runMigration();

        $this->assertSame(
            $manualFix,
            $this->storedPortalUrl(),
            'The migration must match the broken value, not just the row.',
        );
    }

    public function test_it_is_idempotent(): void
    {
        $this->setStoredPortalUrl(self::BROKEN);

        $this->runMigration();
        $this->runMigration();

        $this->assertSame(self::FIXED, $this->storedPortalUrl());
    }

    private function runMigration(): void
    {
        $migration = require database_path('migrations/2026_09_02_000001_fix_student_portal_url_scheme.php');

        $this->assertInstanceOf(Migration::class, $migration);

        $migration->up();
    }

    private function setStoredPortalUrl(string $value): void
    {
        DB::table('settings')
            ->where('group_key', 'navigation')
            ->where('key', 'student_portal_url')
            ->where('locale', '')
            ->update(['value_text' => $value]);
    }

    private function storedPortalUrl(): ?string
    {
        return DB::table('settings')
            ->where('group_key', 'navigation')
            ->where('key', 'student_portal_url')
            ->where('locale', '')
            ->value('value_text');
    }
}
