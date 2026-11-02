<?php

declare(strict_types=1);

namespace Tests\Feature\Performance;

use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

final class HomepageInlineStylesheetTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);
        Cache::flush();
    }

    public function test_the_homepage_inlines_the_stylesheet_and_prefetches_it_for_the_next_page(): void
    {
        $file = $this->stylesheetFile();

        foreach (['ar', 'en'] as $locale) {
            $html = (string) $this->get('/'.$locale)->assertOk()->getContent();

            $this->assertStringContainsString('<style id="public-stylesheet">', $html);
            $this->assertStringContainsString('<link rel="prefetch" href="'.asset('build/'.$file).'">', $html);
            $this->assertStringNotContainsString('<link rel="stylesheet" href="'.asset('build/'.$file).'">', $html);
        }
    }

    public function test_other_pages_keep_the_cacheable_stylesheet_link(): void
    {
        $html = (string) $this->get('/en/contact')->assertOk()->getContent();

        $this->assertStringContainsString('<link rel="stylesheet" href="'.asset('build/'.$this->stylesheetFile()).'">', $html);
        $this->assertStringNotContainsString('id="public-stylesheet"', $html);
    }

    private function stylesheetFile(): string
    {
        $manifest = json_decode((string) file_get_contents(public_path('build/manifest.json')), true);

        return (string) $manifest['resources/css/app.css']['file'];
    }
}
