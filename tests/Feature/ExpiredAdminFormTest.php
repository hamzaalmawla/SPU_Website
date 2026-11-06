<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User\User;
use Illuminate\Contracts\Encryption\Encrypter;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * A stale admin form (a phone tab reshown hours later) must lead back to the
 * form with a fresh token, never to the dead-end 419 page.
 */
final class ExpiredAdminFormTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Laravel skips CSRF checks under PHPUnit; these tests are about them.
        $this->app->bind(ValidateCsrfToken::class, static fn (Application $app): ValidateCsrfToken => new class($app, $app->make(Encrypter::class)) extends ValidateCsrfToken
        {
            protected function runningUnitTests(): bool
            {
                return false;
            }
        });
    }

    public function test_a_stale_sign_in_form_returns_to_the_sign_in_page_with_the_email_kept(): void
    {
        $this->withSession(['admin_locale' => 'en'])
            ->post('/admin/login', [
                '_token' => 'token-from-an-expired-session',
                'email' => 'editor@example.com',
                'password' => 'secret-password',
            ])
            ->assertRedirect('/admin/login')
            ->assertSessionHasErrors(['email' => __('admin.auth.session_expired', [], 'en')])
            ->assertSessionHasInput('email', 'editor@example.com')
            ->assertSessionMissing('_old_input.password');

        $this->assertGuest();

        $this->get('/admin/login')
            ->assertOk()
            ->assertSee('Your session expired while this page was open.')
            ->assertSee('value="editor@example.com"', false);
    }

    public function test_a_stale_logout_while_signed_out_returns_to_the_sign_in_page(): void
    {
        $this->post('/admin/auth/logout', ['_token' => 'token-from-an-expired-session'])
            ->assertRedirect('/admin/login')
            ->assertSessionHasErrors('email');
    }

    public function test_a_stale_language_switch_while_signed_in_returns_to_the_page_unchanged(): void
    {
        $editor = User::factory()->create(['role_slug' => 'super_admin']);

        $this->actingAs($editor)
            ->from('/admin/news-articles')
            ->post('/admin/locale/en', ['_token' => 'stale-page-token'])
            ->assertRedirect('/admin/news-articles')
            ->assertSessionMissing('admin_locale');

        $this->assertAuthenticatedAs($editor);
    }

    public function test_a_valid_token_still_signs_in_normally(): void
    {
        User::factory()->create([
            'email' => 'admin@example.com',
            'password' => 'password',
            'role_slug' => 'super_admin',
        ]);

        $this->withSession(['_token' => 'live-token'])
            ->post('/admin/login', [
                '_token' => 'live-token',
                'email' => 'admin@example.com',
                'password' => 'password',
            ])
            ->assertRedirect('/admin');

        $this->assertAuthenticated();
    }

    public function test_public_forms_keep_the_419_page(): void
    {
        $this->post('/ar/contact', ['_token' => 'token-from-an-expired-session'])
            ->assertStatus(419);
    }

    public function test_the_sign_in_page_is_never_reshown_from_the_browser_cache(): void
    {
        $this->get('/admin/login')
            ->assertOk()
            ->assertHeader('Cache-Control', 'no-store, private');
    }
}
