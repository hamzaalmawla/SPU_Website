<?php

declare(strict_types=1);

namespace Tests\Unit;

use Tests\TestCase;

final class LegacyPhaseThreeConfigurationTest extends TestCase
{
    public function test_cleaning_reports_cover_known_legacy_modules(): void
    {
        $this->assertConfiguredModules('old_database.cleaning_inspection_fields', [
            'admins',
            'settings',
            'homepage',
            'static_pages',
            'links',
            'news',
            'faculties',
            'faculty_members',
            'research',
            'councils',
            'faqs',
            'complaints',
            'career_links',
            'alumni',
            'honor_students',
            'countries',
            'cities',
        ]);
    }

    public function test_integrity_reports_cover_known_legacy_modules(): void
    {
        $this->assertConfiguredModules('old_database.integrity_inspection_rules', [
            'admins',
            'settings',
            'homepage',
            'static_pages',
            'links',
            'news',
            'faculties',
            'faculty_members',
            'research',
            'councils',
            'faqs',
            'complaints',
            'career_links',
            'alumni',
            'honor_students',
            'countries',
            'cities',
        ]);
    }

    public function test_internal_link_reports_cover_content_bearing_legacy_modules(): void
    {
        $this->assertConfiguredModules('old_database.internal_link_extraction_fields', [
            'settings',
            'homepage',
            'static_pages',
            'links',
            'news',
            'faculties',
            'faculty_members',
            'research',
            'councils',
            'faqs',
            'complaints',
            'career_links',
            'alumni',
            'honor_students',
        ]);
    }

    /** @param array<int, string> $expectedModules */
    private function assertConfiguredModules(string $key, array $expectedModules): void
    {
        $configured = config($key, []);

        $this->assertIsArray($configured);

        foreach ($expectedModules as $module) {
            $this->assertArrayHasKey($module, $configured, "Missing Phase 3 config for [{$module}] in [{$key}].");
            $this->assertNotSame([], $configured[$module], "Empty Phase 3 config for [{$module}] in [{$key}].");
        }
    }
}
