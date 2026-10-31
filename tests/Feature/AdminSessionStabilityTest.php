<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Contracts\Auth\AuthServiceInterface;
use App\Models\User\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Admin pages fire overlapping requests (Livewire updates, clicks while a page is still loading).
 * If every request rotated the session id, any request still carrying the previous cookie found its
 * session deleted and was sent to the login page, logging the admin out at random.
 */
final class AdminSessionStabilityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);
    }

    public function test_extending_the_admin_session_does_not_change_the_session_id(): void
    {
        // The feature-test client sends no cookie between requests, so ids differ there regardless;
        // the invariant is checked on the service the admin middleware calls on every request.
        $session = $this->app['session.store'];
        $session->start();
        $id = $session->getId();

        $service = $this->app->make(AuthServiceInterface::class);
        $service->extendSession();
        $service->extendSession();
        $service->extendSession();

        $this->assertSame($id, $session->getId());
        $this->assertNotNull($session->get('admin_session_last_activity_at'));
        $this->assertNotNull($session->get('admin_session_started_at'));
    }

    public function test_admin_activity_is_still_recorded_for_the_idle_timeout(): void
    {
        $user = User::factory()->create(['role_slug' => 'editor', 'is_locked' => false]);
        $this->actingAs($user, 'web')->withSession(['admin_session_last_activity_at' => 1]);

        $this->get('/admin/manage-petroleum-faculty')->assertRedirect(route('admin.login'));

        $this->actingAs($user, 'web')->withSession(['admin_session_last_activity_at' => now()->getTimestamp() - 60]);
        $this->get('/admin/manage-petroleum-faculty')->assertOk();

        $this->assertGreaterThan(now()->getTimestamp() - 30, (int) session('admin_session_last_activity_at'));
    }

    public function test_logging_in_still_rotates_the_session_id(): void
    {
        $user = User::factory()->create([
            'role_slug' => 'editor',
            'is_locked' => false,
            'password' => 'a-long-test-password-1',
        ]);

        $this->get('/admin/login')->assertOk();
        $before = $this->app['session.store']->getId();

        $this->post('/admin/login', ['email' => $user->email, 'password' => 'a-long-test-password-1']);

        $this->assertAuthenticatedAs($user);
        $this->assertNotSame($before, $this->app['session.store']->getId());
    }
}
