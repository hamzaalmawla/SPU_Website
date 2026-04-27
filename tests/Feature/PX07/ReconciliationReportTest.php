<?php

declare(strict_types=1);

namespace Tests\Feature\PX07;

use App\Models\LegacyExactRedirect;
use App\Models\LegacyFileInventory;
use App\Models\LegacyRecordSnapshot;
use App\Models\Page;
use App\Models\PageTranslation;
use App\Models\Setting;
use App\Models\UnresolvedLegacyRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Feature tests for continuity:reconciliation-report command.
 *
 * Requirements: 27.6
 */
class ReconciliationReportTest extends TestCase
{
    use RefreshDatabase;

    public function test_command_produces_combined_report(): void
    {
        Storage::fake('local');
        $this->seedSeoDefaults();

        LegacyExactRedirect::create([
            'legacy_path' => '/old-url',
            'destination_url' => '/ar/new-url',
            'status_code' => 301,
            'is_active' => true,
        ]);

        LegacyFileInventory::create([
            'legacy_path' => '/files/doc.pdf',
            'current_path' => '/media/doc.pdf',
            'status' => 'mapped',
        ]);

        LegacyRecordSnapshot::create([
            'module' => 'static_pages',
            'batch_name' => 'test-batch',
            'source_table' => 'legacy_pages',
            'source_id' => 22,
            'legacy_key' => '/legacy-recon-page',
            'classification' => 'candidate_url',
            'locale' => 'ar',
        ]);

        UnresolvedLegacyRequest::insert([
            'url' => '/missing-page',
            'method' => 'GET',
            'request_type' => 'page',
            'hit_count' => 1,
            'first_seen_at' => now(),
            'last_seen_at' => now(),
            'created_at' => now(),
        ]);

        $page = Page::create([
            'slug' => 'recon-page',
            'status' => 'published',
            'is_enabled' => true,
            'published_at' => now(),
            'template' => 'default',
            'type' => 'landing',
        ]);

        PageTranslation::create([
            'page_id' => $page->id,
            'locale' => 'ar',
            'title' => 'صفحة',
        ]);

        $this->artisan('continuity:reconciliation-report', ['--format' => 'json'])
            ->assertSuccessful();

        $files = Storage::disk('local')->allFiles('continuity-exports');
        $jsonFile = collect($files)->first(fn (string $f): bool => str_ends_with($f, '.json'));
        $this->assertNotNull($jsonFile);

        $content = json_decode(Storage::disk('local')->get($jsonFile), true);
        $this->assertArrayHasKey('summary', $content);
        $this->assertArrayHasKey('url_inventory', $content);
        $this->assertArrayHasKey('redirect_validation', $content);
        $this->assertArrayHasKey('file_inventory', $content);
        $this->assertArrayHasKey('unresolved_requests', $content);
        $this->assertArrayHasKey('seo_gaps', $content);
        $this->assertArrayHasKey('ambiguous_structures', $content);
        $this->assertTrue(collect($content['url_inventory'])->contains(fn (array $row): bool => $row['legacy_path'] === '/legacy-recon-page'));
    }

    public function test_command_handles_empty_data(): void
    {
        Storage::fake('local');
        $this->seedSeoDefaults();

        $this->artisan('continuity:reconciliation-report', ['--format' => 'json'])
            ->assertSuccessful();
    }

    private function seedSeoDefaults(): void
    {
        foreach (['ar', 'en'] as $locale) {
            Setting::updateOrCreate(
                ['key' => 'default_seo', 'group_key' => 'seo', 'locale' => $locale],
                [
                    'type' => 'json',
                    'value_json' => [
                        'title' => 'SPU',
                        'meta_description' => 'Syrian Private University',
                        'og_title' => 'SPU',
                        'og_description' => 'Syrian Private University',
                        'og_image' => '',
                        'robots' => 'index,follow',
                    ],
                    'is_public' => true,
                ],
            );
        }
    }
}
