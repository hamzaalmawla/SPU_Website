<?php

declare(strict_types=1);

namespace Tests\Feature;

/**
 * With ADMIN_ALLOWED_IPS set, the hidden path itself 404s for any other
 * address - before a session starts, so no cookie gives it away either.
 */
class AdminIpAllowlistTest extends HiddenAdminPathTest
{
    private const HIDDEN = 'k7x2-panel-9q4m';

    protected function allowedIps(): string
    {
        return '10.20.0.0/16,192.0.2.7';
    }

    public function test_panel_and_login_are_served_under_the_hidden_path(): void
    {
        $this->withServerVariables(['REMOTE_ADDR' => '10.20.4.5'])
            ->get('/'.self::HIDDEN.'/login')
            ->assertOk();
        $this->withServerVariables(['REMOTE_ADDR' => '192.0.2.7'])
            ->get('/'.self::HIDDEN)
            ->assertRedirect('/'.self::HIDDEN.'/login');
    }

    public function test_successful_login_lands_in_the_hidden_panel(): void
    {
        $this->withServerVariables(['REMOTE_ADDR' => '10.20.4.5']);

        parent::test_successful_login_lands_in_the_hidden_panel();
    }

    public function test_other_addresses_get_the_public_404(): void
    {
        $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.9']);

        foreach (['/'.self::HIDDEN, '/'.self::HIDDEN.'/login'] as $path) {
            $probe = $this->get($path);
            $unknown = $this->get('/no-such-page-anywhere');

            $probe->assertNotFound()->assertCookieMissing((string) config('session.cookie'));
            $this->assertSame(
                $unknown->headers->get('Content-Security-Policy'),
                $probe->headers->get('Content-Security-Policy'),
            );
        }

        $this->post('/'.self::HIDDEN.'/login', ['email' => 'x@example.com', 'password' => 'x'])
            ->assertNotFound();
    }
}
