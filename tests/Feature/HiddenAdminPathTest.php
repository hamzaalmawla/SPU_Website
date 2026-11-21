<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Verifies the admin panel lives only under ADMIN_PATH, and that /admin and
 * IP-rejected requests look exactly like any other unknown URL.
 */
class HiddenAdminPathTest extends TestCase
{
    use RefreshDatabase;

    private const HIDDEN = 'k7x2-panel-9q4m';

    /** @var array<string, string|false> */
    private array $previousEnv = [];

    protected function setUp(): void
    {
        // Routes are registered while the application boots, so the prefix has
        // to be in the environment before it is created. $_SERVER is read first.
        $this->setEnv('ADMIN_PATH', self::HIDDEN);
        $this->setEnv('ADMIN_ALLOWED_IPS', $this->allowedIps());

        parent::setUp();
    }

    protected function tearDown(): void
    {
        parent::tearDown();

        foreach ($this->previousEnv as $key => $value) {
            if ($value === false) {
                unset($_SERVER[$key], $_ENV[$key]);
                putenv($key);
            } else {
                $_SERVER[$key] = $_ENV[$key] = $value;
                putenv($key.'='.$value);
            }
        }
    }

    protected function allowedIps(): string
    {
        return '';
    }

    public function test_panel_and_login_are_served_under_the_hidden_path(): void
    {
        $this->get('/'.self::HIDDEN)->assertRedirect('/'.self::HIDDEN.'/login');
        $this->get('/'.self::HIDDEN.'/login')->assertOk();
    }

    public function test_admin_is_an_ordinary_unknown_url(): void
    {
        foreach (['/admin', '/admin/login', '/admin/news-articles'] as $path) {
            $probe = $this->get($path);
            $unknown = $this->get('/no-such-page-anywhere');

            $probe->assertNotFound();
            $this->assertSame(
                $unknown->headers->get('Content-Security-Policy'),
                $probe->headers->get('Content-Security-Policy'),
                "{$path} must carry the same CSP as any unknown URL",
            );
            $this->assertStringNotContainsString(self::HIDDEN, (string) $probe->getContent());
            $this->assertNull($probe->headers->get('Location'));
        }

        $this->post('/admin/login', ['email' => 'x@example.com', 'password' => 'x'])
            ->assertNotFound();
    }

    public function test_successful_login_lands_in_the_hidden_panel(): void
    {
        User::factory()->create([
            'email' => 'admin@example.com',
            'password' => 'password',
            'role_slug' => 'super_admin',
        ]);

        $this->post('/'.self::HIDDEN.'/login', [
            'email' => 'admin@example.com',
            'password' => 'password',
        ])->assertRedirect('/'.self::HIDDEN);
    }

    public function test_public_pages_never_mention_the_hidden_path(): void
    {
        foreach (['/ar', '/en', '/robots.txt', '/sitemap.xml'] as $path) {
            $this->assertStringNotContainsString(self::HIDDEN, (string) $this->get($path)->getContent(), $path);
        }
    }

    private function setEnv(string $key, string $value): void
    {
        $this->previousEnv[$key] = getenv($key);
        $_SERVER[$key] = $_ENV[$key] = $value;
        putenv($key.'='.$value);
    }
}
