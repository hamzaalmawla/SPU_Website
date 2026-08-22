<?php

declare(strict_types=1);

namespace Tests\Feature\PX05;

use Tests\TestCase;

final class EdgeRequestTest extends TestCase
{
    public function test_direct_front_controller_paths_are_not_public_routes(): void
    {
        config()->set('edge.enforce_canonical_host', false);

        $this->get('/app.php')->assertNotFound();
        $this->get('/app.php/anything')->assertNotFound();
        $this->get('/index.php/anything')->assertNotFound();
    }

    public function test_production_canonical_redirect_never_reflects_an_untrusted_host(): void
    {
        config()->set('edge.canonical_url', 'https://spu.edu.sy');
        config()->set('edge.enforce_canonical_host', true);

        $this->withServerVariables([
            'REMOTE_ADDR' => '203.0.113.10',
            'HTTP_HOST' => 'attacker.example',
        ])->withHeaders([
            'X-Forwarded-Host' => 'spu.edu.sy',
            'X-Forwarded-Proto' => 'https',
        ])->get('/ar/about?source=edge')
            ->assertRedirect('https://spu.edu.sy/ar/about?source=edge')
            ->assertStatus(301);
    }

    public function test_apache_https_redirect_uses_a_fixed_origin(): void
    {
        $rules = file_get_contents(public_path('.htaccess'));

        $this->assertIsString($rules);
        $this->assertStringContainsString('https://spu.edu.sy%{REQUEST_URI}', $rules);
        $this->assertStringNotContainsString('https://%{HTTP_HOST}', $rules);
    }

    public function test_apache_hardens_static_and_legacy_browser_active_files(): void
    {
        $rules = file_get_contents(public_path('.htaccess'));

        $this->assertIsString($rules);
        $this->assertStringContainsString('Header always set X-Content-Type-Options "nosniff"', $rules);
        $this->assertStringContainsString('Header always set Referrer-Policy "no-referrer"', $rules);
        $this->assertStringContainsString('(?:html?|xhtml|xml|svgz?)', $rules);
        $this->assertStringContainsString('(?:php[0-9]?|phtml|phar|cgi', $rules);
    }
}
