<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

/**
 * Verifies admin login, lockout, and logout flows.
 */
class AdminAuthFlowTest extends TestCase
{
    use RefreshDatabase;

    /**
     * It renders the admin login page.
     */
    public function test_admin_login_page_loads(): void
    {
        $this->get('/admin/login')
            ->assertOk()
            ->assertSee('<html lang="ar" dir="rtl">', false)
            ->assertSee('SPU CMS')
            ->assertSee('تسجيل دخول الإدارة')
            ->assertSee('الجامعة السورية الخاصة');
    }

    public function test_admin_login_page_renders_english_ltr_with_arabic_brand_fallback(): void
    {
        $this->withSession(['admin_locale' => 'en'])
            ->get('/admin/login')
            ->assertOk()
            ->assertSee('<html lang="en" dir="ltr">', false)
            ->assertSee('SPU CMS')
            ->assertSee('Admin sign in')
            ->assertSee('Syrian Private University')
            ->assertSee('<span class="brand-name-native" lang="ar" dir="rtl">الجامعة السورية الخاصة</span>', false);
    }

    public function test_admin_locale_switcher_updates_login_locale(): void
    {
        $this->from('/admin/login')
            ->post('/admin/locale/en')
            ->assertRedirect('/admin/login')
            ->assertSessionHas('admin_locale', 'en');

        $this->get('/admin/login')
            ->assertOk()
            ->assertSee('<html lang="en" dir="ltr">', false)
            ->assertSee('Admin sign in');
    }

    /**
     * It authenticates a valid admin user and redirects into the panel.
     */
    public function test_successful_admin_login_redirects_to_admin_panel(): void
    {
        $user = User::factory()->create([
            'email' => 'admin@example.com',
            'password' => 'password',
            'role_slug' => 'super_admin',
        ]);

        $this->post('/admin/login', [
            'email' => 'admin@example.com',
            'password' => 'password',
        ])->assertRedirect('/admin');

        $this->assertAuthenticatedAs($user, 'web');
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'user.login',
            'user_id' => $user->id,
            'actor_user_id' => $user->id,
            'entity_type' => User::class,
            'entity_id' => $user->id,
        ]);
    }

    /**
     * It prevents locked users from logging in even with correct credentials.
     */
    public function test_locked_accounts_cannot_log_in_with_correct_password(): void
    {
        $user = User::factory()->create([
            'email' => 'locked@example.com',
            'password' => 'password',
            'role_slug' => 'editor',
        ]);

        foreach (range(1, 5) as $attempt) {
            $this->post('/admin/login', [
                'email' => 'locked@example.com',
                'password' => 'wrong-password',
            ])->assertRedirect();
        }

        $user->refresh();

        $this->assertNotNull($user->locked_at);
        $this->assertSame(5, $user->failed_login_attempts);
        $this->assertSame(5, $user->failed_attempts);
        $this->assertTrue($user->is_locked);

        RateLimiter::clear('admin-login|locked@example.com');
        $this->travel(61)->seconds();

        $this->post('/admin/login', [
            'email' => 'locked@example.com',
            'password' => 'password',
        ])->assertRedirect();

        $this->assertGuest('web');
    }

    /**
     * It logs out the current admin session through the auth controller.
     */
    public function test_logout_clears_the_admin_session_and_writes_audit_log(): void
    {
        $user = User::factory()->create([
            'role_slug' => 'super_admin',
        ]);

        $this->actingAs($user, 'web');

        $this->post('/admin/auth/logout')->assertRedirect('/admin/login');

        $this->assertGuest('web');
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'user.logout',
            'user_id' => $user->id,
            'actor_user_id' => $user->id,
            'entity_type' => User::class,
            'entity_id' => $user->id,
        ]);
    }
}
