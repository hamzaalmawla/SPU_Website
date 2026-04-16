<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
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

    /**
     * It serves the second public request from cache.
     */
    public function test_second_public_request_returns_cache_hit(): void
    {
        $this->get('/en')->assertHeader('X-Cache', 'MISS');

        $this->get('/en')->assertHeader('X-Cache', 'HIT');
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
        $user->forceFill(['role_slug' => 'editor']);

        $this->actingAs($user, 'web');

        $this->get('/admin/content')->assertOk();
    }

    /**
     * It denies access when the authenticated user lacks the required gate permission.
     */
    public function test_gate_protected_admin_routes_deny_unauthorized_access(): void
    {
        $user = new User;
        $user->forceFill(['role_slug' => 'faculty_editor']);

        $this->actingAs($user, 'web');

        $this->get('/admin/content')->assertForbidden();
    }
}
