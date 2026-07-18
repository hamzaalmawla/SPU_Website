<?php

declare(strict_types=1);

namespace Tests\Feature\PX05;

use App\Contracts\Shared\ContinuityServiceInterface;
use App\Models\Legacy\LegacyExactRedirect;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

final class ReferenceHtmlAliasContinuityTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array<string, array{string, string}>
     */
    public static function explicitAliasProvider(): array
    {
        return [
            'top-level html' => ['/en/about.html', '/en/about'],
            'nested index html' => ['/en/research/projects/index.html', '/en/research/projects'],
            'renamed student life' => ['/en/student-life.html', '/en/campus-life'],
            'renamed services' => ['/en/services.html', '/en/e-services'],
            'e-services library' => ['/en/e-services/library.html', '/en/e-services/library'],
            'e-services staff email' => ['/en/e-services/staff-email.html', '/en/e-services/staff-email'],
            'e-services IT support' => ['/en/e-services/it-support.html', '/en/e-services/it-support'],
            'generated career detail' => [
                '/en/campus-life/career-development/jobs/lecturer-computer-science/index.html',
                '/en/campus-life/career-development/jobs/lecturer-computer-science',
            ],
        ];
    }

    public function test_reference_alias_inventory_is_complete_and_safe(): void
    {
        /** @var array<string, string> $aliases */
        $aliases = config('reference_html_aliases');

        $this->assertCount(175, $aliases);
        $this->assertCount(175, array_unique(array_keys($aliases)));
        $this->assertCount(175, array_unique(array_values($aliases)));

        foreach ($aliases as $source => $destination) {
            $this->assertStringStartsWith('/', $source);
            $this->assertStringEndsWith('.html', $source);
            $this->assertStringStartsWith('/', $destination);
            $this->assertStringNotContainsString('?', $destination);
            $this->assertStringNotContainsString('#', $destination);
        }

        $validation = app(ContinuityServiceInterface::class)->validateRedirectRules();

        $this->assertTrue($validation->isValid);
    }

    public function test_unprefixed_alias_negotiates_browser_locale(): void
    {
        $response = $this->withHeader('Accept-Language', 'en-US,en;q=0.9')->get('/about.html');

        $response->assertStatus(302);
        $response->assertRedirect('/en/about');
        $response->assertHeader('Vary', 'Accept-Language');
        $response->assertHeader('Cache-Control', 'no-store, private');
    }

    public function test_unprefixed_alias_defaults_to_arabic(): void
    {
        $this->withServerVariables(['HTTP_ACCEPT_LANGUAGE' => ''])
            ->get('/index.html')
            ->assertStatus(302)
            ->assertRedirect('/ar');
    }

    #[DataProvider('explicitAliasProvider')]
    public function test_explicit_locale_aliases_redirect_permanently(string $source, string $destination): void
    {
        $response = $this->withHeader('Accept-Language', 'ar')->get($source);

        $response->assertStatus(301);
        $response->assertRedirect($destination);
        $response->assertHeader('Cache-Control', 'max-age=86400, public');
        $this->assertFalse($response->headers->has('Vary'));
    }

    public function test_alias_preserves_raw_query_state(): void
    {
        $response = $this->get('/en/about/profile.html?slug=rector&utm_source=legacy%20site');

        $response->assertStatus(301);
        $response->assertRedirect('/en/about/profile?slug=rector&utm_source=legacy%20site');
        $response->assertHeader('Cache-Control', 'no-store, private');
    }

    public function test_head_requests_use_the_same_alias_policy(): void
    {
        $this->withHeader('Accept-Language', 'en')->head('/news.html')
            ->assertStatus(302)
            ->assertRedirect('/en/news');
    }

    public function test_aliases_are_authoritative_over_database_redirects(): void
    {
        LegacyExactRedirect::query()->create([
            'legacy_path' => '/about.html',
            'destination_url' => '/ar/wrong-destination',
            'status_code' => 301,
            'is_active' => true,
        ]);

        $this->withHeader('Accept-Language', 'en')->get('/about.html')
            ->assertStatus(302)
            ->assertRedirect('/en/about');
    }

    public function test_unknown_html_and_unsafe_methods_do_not_redirect(): void
    {
        $this->get('/unapproved-page.html')->assertNotFound();
        $this->post('/about.html')->assertNotFound();
    }
}
