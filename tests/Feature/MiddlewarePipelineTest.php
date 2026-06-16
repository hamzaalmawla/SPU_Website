<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

/**
 * Verifies middleware pipeline behavior for locale, auth, role, and caching.
 */
class MiddlewarePipelineTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);
        Cache::flush();
    }

    /**
     * It sets Arabic locale headers from the URL prefix.
     */
    public function test_arabic_routes_include_locale_headers(): void
    {
        $response = $this->get('/ar');

        $response
            ->assertOk()
            ->assertHeader('Content-Language', 'ar')
            ->assertHeader('X-Page-Direction', 'rtl')
            ->assertHeader('X-Cache', 'MISS');
    }

    /**
     * It sets English locale headers from the URL prefix.
     */
    public function test_english_routes_include_locale_headers(): void
    {
        $response = $this->get('/en');

        $response
            ->assertOk()
            ->assertHeader('Content-Language', 'en')
            ->assertHeader('X-Page-Direction', 'ltr');
    }

    public function test_public_routes_include_security_headers(): void
    {
        $response = $this->get('/en')
            ->assertOk()
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('Referrer-Policy', 'no-referrer')
            ->assertHeader('X-Frame-Options', 'SAMEORIGIN')
            ->assertHeader('Permissions-Policy', 'camera=(), microphone=(), geolocation=(), payment=(), usb=()')
            ->assertHeader('Content-Security-Policy');

        $this->assertStringContainsString("script-src 'self' 'unsafe-inline'", (string) $response->headers->get('Content-Security-Policy'));
        $this->assertStringNotContainsString("'unsafe-eval'", (string) $response->headers->get('Content-Security-Policy'));
        $this->assertStringNotContainsString("script-src 'self' 'unsafe-inline' https:", (string) $response->headers->get('Content-Security-Policy'));
    }

    public function test_admin_routes_include_security_headers(): void
    {
        $this->get('/admin/login')
            ->assertOk()
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('Referrer-Policy', 'no-referrer')
            ->assertHeader('X-Frame-Options', 'SAMEORIGIN');
    }

    /**
     * It serves the second public request from cache.
     */
    public function test_second_public_request_returns_cache_hit(): void
    {
        $this->get('/en')->assertHeader('X-Cache', 'MISS');

        $this->get('/en')->assertHeader('X-Cache', 'HIT');
    }

    public function test_public_page_cache_is_isolated_by_locale_at_runtime(): void
    {
        $this->get('/ar')
            ->assertOk()
            ->assertHeader('X-Cache', 'MISS')
            ->assertSee('كلياتنا الجامعية')
            ->assertDontSee('Our Faculties');

        $this->get('/en')
            ->assertOk()
            ->assertHeader('X-Cache', 'MISS')
            ->assertSee('Our Faculties')
            ->assertDontSee('كلياتنا الجامعية');

        $this->get('/ar')
            ->assertOk()
            ->assertHeader('X-Cache', 'HIT')
            ->assertSee('كلياتنا الجامعية')
            ->assertDontSee('Our Faculties');

        $this->get('/en')
            ->assertOk()
            ->assertHeader('X-Cache', 'HIT')
            ->assertSee('Our Faculties')
            ->assertDontSee('كلياتنا الجامعية');
    }

    public function test_non_get_public_requests_bypass_public_page_cache(): void
    {
        $this->post('/en/contact', [])
            ->assertRedirect()
            ->assertHeader('X-Cache', 'BYPASS');
    }

    /**
     * It bypasses cache for preview requests.
     */
    public function test_preview_requests_bypass_public_cache(): void
    {
        $this->get('/ar/preview?preview_token=scheduled-preview')
            ->assertNotFound()
            ->assertHeader('X-Cache', 'BYPASS');
    }

    /**
     * It redirects unauthenticated admin requests to the admin login page.
     */
    public function test_admin_routes_redirect_guests_to_admin_login(): void
    {
        $this->get('/admin')->assertRedirect('/admin/login');
    }

    /**
     * It throttles repeated admin login attempts after five rapid requests.
     */
    public function test_admin_login_is_throttled_after_five_requests(): void
    {
        foreach (range(1, 5) as $attempt) {
            $this->post('/admin/login', [
                'email' => 'test@example.com',
                'password' => 'wrong-password',
            ])->assertRedirect();
        }

        $this->post('/admin/login', [
            'email' => 'test@example.com',
            'password' => 'wrong-password',
        ])->assertStatus(429);
    }

    /**
     * It bypasses public page cache for authenticated users.
     */
    public function test_authenticated_users_bypass_public_cache(): void
    {
        $user = new User;
        $user->forceFill(['name' => 'Authenticated User', 'email' => 'auth-user@example.com']);

        $this->actingAs($user, 'web');

        $this->get('/ar')
            ->assertOk()
            ->assertHeader('X-Cache', 'BYPASS');
    }

    /**
     * It allows configured admin roles through gate-protected admin routes.
     */
    public function test_gate_protected_admin_routes_allow_editor_access(): void
    {
        $user = new User;
        $user->forceFill(['name' => 'Editor User', 'email' => 'editor@example.com', 'role_slug' => 'editor']);

        $this->actingAs($user, 'web');

        $this->get('/admin/manage-settings')->assertOk();
    }

    /**
     * It denies access when the authenticated user lacks the required gate permission.
     */
    public function test_gate_protected_admin_routes_deny_unauthorized_access(): void
    {
        $user = new User;
        $user->forceFill(['name' => 'Faculty Editor', 'email' => 'faculty-editor@example.com', 'role_slug' => 'faculty_editor']);

        $this->actingAs($user, 'web');

        $this->get('/admin/manage-settings')->assertForbidden();
    }
}
