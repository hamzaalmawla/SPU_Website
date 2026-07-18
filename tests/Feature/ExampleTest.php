<?php

declare(strict_types=1);

namespace Tests\Feature;

use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Baseline application tests.
 */
class ExampleTest extends TestCase
{
    use RefreshDatabase;

    /**
     * A basic test example.
     */
    public function test_root_defaults_to_arabic_without_a_browser_preference(): void
    {
        $this->withServerVariables(['HTTP_ACCEPT_LANGUAGE' => ''])
            ->get('/')
            ->assertRedirect('/ar')
            ->assertHeader('Vary', 'Accept-Language')
            ->assertHeader('Cache-Control', 'no-store, private');
    }

    public function test_root_uses_the_browser_english_preference(): void
    {
        $this->withHeader('Accept-Language', 'en-US,en;q=0.9,ar;q=0.8')
            ->get('/')
            ->assertRedirect('/en');
    }

    public function test_root_uses_the_browser_arabic_preference(): void
    {
        $this->withHeader('Accept-Language', 'ar-SY,ar;q=0.9,en;q=0.8')
            ->get('/')
            ->assertRedirect('/ar');
    }

    public function test_explicit_locale_homepage_renders_without_browser_override(): void
    {
        $this->seed(DatabaseSeeder::class);

        $this->withHeader('Accept-Language', 'ar-SY,ar;q=0.9')
            ->get('/en')
            ->assertOk()
            ->assertSee('lang="en" dir="ltr"', false)
            ->assertHeader('Content-Language', 'en');
    }

    public function test_unprefixed_reference_deep_links_preserve_path_and_query(): void
    {
        $this->withHeader('Accept-Language', 'en-US,en;q=0.9')
            ->get('/news/events-list/register?event=evt-001')
            ->assertRedirect('/en/news/events-list/register?event=evt-001')
            ->assertHeader('Vary', 'Accept-Language');

        $this->withHeader('Accept-Language', 'ar-SY,ar;q=0.9')
            ->get('/about/history')
            ->assertRedirect('/ar/about/history');
    }
}
