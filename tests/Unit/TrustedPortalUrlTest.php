<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Rules\TrustedPortalUrlRule;
use App\Support\TrustedPortalUrl;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

/**
 * Covers the portal-link policy and the two defects that let a bad value reach
 * production: a present-but-empty TRUSTED_PORTAL_HOSTS collapsing the allow-list,
 * and an admin form that accepted hosts the public resolver would reject.
 */
final class TrustedPortalUrlTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config()->set('security.trusted_portal_hosts', ['my.spu.edu.sy']);
    }

    public function test_it_accepts_an_https_url_on_a_trusted_host(): void
    {
        $this->assertSame(
            'https://my.spu.edu.sy/ar/login',
            TrustedPortalUrl::sanitize('https://my.spu.edu.sy/ar/login'),
        );
    }

    public function test_it_accepts_a_site_relative_path(): void
    {
        $this->assertSame('/e-services/it-support', TrustedPortalUrl::sanitize('/e-services/it-support'));
    }

    public function test_it_rejects_an_untrusted_host(): void
    {
        $this->assertNull(TrustedPortalUrl::sanitize('https://portal.example.com/login'));
    }

    public function test_it_rejects_plain_http_even_on_a_trusted_host(): void
    {
        $this->assertNull(TrustedPortalUrl::sanitize('http://my.spu.edu.sy/ar/login'));
    }

    public function test_it_rejects_protocol_relative_and_credentialed_urls(): void
    {
        $this->assertNull(TrustedPortalUrl::sanitize('//my.spu.edu.sy/ar/login'));
        $this->assertNull(TrustedPortalUrl::sanitize('https://user:pass@my.spu.edu.sy/ar/login'));
    }

    public function test_an_empty_allow_list_rejects_every_absolute_url(): void
    {
        // This is the production failure mode: with no trusted hosts, a perfectly
        // ordinary portal URL resolves to null and the public route aborts 503.
        config()->set('security.trusted_portal_hosts', []);

        $this->assertNull(TrustedPortalUrl::sanitize('https://my.spu.edu.sy/ar/login'));
    }

    /**
     * The regression that caused the outage. env() falls back to its default only
     * when the key is ABSENT; a bare `TRUSTED_PORTAL_HOSTS=` yields '', which used
     * to collapse to an empty allow-list and take the route offline silently.
     *
     * @param  string|null  $envValue
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('blankEnvValues')]
    public function test_a_blank_env_value_falls_back_to_the_default_host(mixed $envValue): void
    {
        $hosts = $this->trustedHostsForEnv($envValue);

        $this->assertSame(['my.spu.edu.sy'], $hosts);
    }

    /** @return array<string, array{0: string|null}> */
    public static function blankEnvValues(): array
    {
        return [
            'absent' => [null],
            'empty string' => [''],
            'whitespace only' => ['   '],
        ];
    }

    public function test_an_explicit_env_value_still_wins(): void
    {
        $this->assertSame(
            ['portal.spu.edu.sy', 'my.spu.edu.sy'],
            $this->trustedHostsForEnv('portal.spu.edu.sy, MY.spu.edu.sy'),
        );
    }

    public function test_validation_rule_rejects_an_untrusted_host(): void
    {
        $validator = Validator::make(
            ['url' => 'https://portal.example.com/login'],
            ['url' => [new TrustedPortalUrlRule]],
        );

        $this->assertTrue($validator->fails());
        $this->assertStringContainsString('my.spu.edu.sy', (string) $validator->errors()->first('url'));
    }

    public function test_validation_rule_allows_a_trusted_host_and_an_empty_value(): void
    {
        foreach (['https://my.spu.edu.sy/ar/login', '/e-services/it-support', ''] as $value) {
            $validator = Validator::make(['url' => $value], ['url' => [new TrustedPortalUrlRule]]);

            $this->assertFalse($validator->fails(), sprintf('Expected %s to be accepted.', var_export($value, true)));
        }
    }

    /**
     * Re-evaluates config/security.php with a given raw env value, exactly as the
     * config cache would bake it.
     *
     * @return array<int, string>
     */
    private function trustedHostsForEnv(mixed $envValue): array
    {
        $previous = $_ENV['TRUSTED_PORTAL_HOSTS'] ?? null;

        if ($envValue === null) {
            unset($_ENV['TRUSTED_PORTAL_HOSTS'], $_SERVER['TRUSTED_PORTAL_HOSTS']);
        } else {
            $_ENV['TRUSTED_PORTAL_HOSTS'] = $envValue;
            $_SERVER['TRUSTED_PORTAL_HOSTS'] = $envValue;
        }

        try {
            /** @var array{trusted_portal_hosts: array<int, string>} $config */
            $config = require base_path('config/security.php');

            return $config['trusted_portal_hosts'];
        } finally {
            if ($previous === null) {
                unset($_ENV['TRUSTED_PORTAL_HOSTS'], $_SERVER['TRUSTED_PORTAL_HOSTS']);
            } else {
                $_ENV['TRUSTED_PORTAL_HOSTS'] = $previous;
                $_SERVER['TRUSTED_PORTAL_HOSTS'] = $previous;
            }
        }
    }
}
