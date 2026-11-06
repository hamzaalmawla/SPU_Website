<?php

declare(strict_types=1);

namespace Tests\Feature;

use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class PublicAccessibilitySemanticsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    public function test_navigation_renders_disclosure_relationships_without_hash_fallbacks(): void
    {
        $this->get('/en/contact')
            ->assertOk()
            ->assertSee('aria-controls="site-mobile-navigation"', false)
            ->assertSee('id="site-mobile-navigation"', false)
            ->assertSee('aria-controls="site-nav-dropdown-', false)
            ->assertSee('aria-controls="site-mobile-submenu-', false)
            ->assertDontSee('href="#"', false);
    }

    public function test_campus_life_and_virtual_tour_templates_guard_empty_hash_links(): void
    {
        $campus = file_get_contents(resource_path('views/public/campus-life/landing.blade.php'));
        $tour = file_get_contents(resource_path('views/public/virtual-tour/show.blade.php'));

        $this->assertIsString($campus);
        $this->assertStringContainsString('$hasDestination', $campus);
        $this->assertIsString($tour);
        $this->assertStringNotContainsString("href=\"{{ \$item['href'] ?? '#' }}\"", $tour);
    }

    public function test_dynamic_form_template_exposes_accessible_field_relationships(): void
    {
        $field = file_get_contents(resource_path('views/public/forms/partials/dynamic-field.blade.php'));
        $form = file_get_contents(resource_path('views/public/forms/dynamic-form.blade.php'));

        $this->assertIsString($field);
        $this->assertStringContainsString('x-bind:for="fieldId(field)"', $field);
        $this->assertStringContainsString('x-bind:aria-invalid="invalid(field)"', $field);
        $this->assertStringContainsString('x-bind:aria-describedby="describedBy(field)"', $field);
        $this->assertStringContainsString('x-bind:required="field.required"', $field);
        $this->assertIsString($form);
        $this->assertStringContainsString('aria-live="polite"', $form);
        $this->assertStringContainsString('role="alert"', $form);
    }
}
