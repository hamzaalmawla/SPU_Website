<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Contracts\Legacy\LegacyQueryRedirectResolverInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class LegacyAlumniQueryResolverTest extends TestCase
{
    use RefreshDatabase;

    public function test_reviewed_global_alumni_list_queries_redirect_to_the_localized_directory(): void
    {
        $resolver = app(LegacyQueryRedirectResolverInterface::class);

        $arabic = $resolver->resolve('/alumni/index.php', 'page=list&ex=2&dir=graduated_students&lang=1&d=2');
        $english = $resolver->resolve('/alumni/index.php', 'lang=2&d=7&dir=graduated_students&ex=2&page=list');

        $this->assertSame('/ar/alumni?faculty=medicine', $arabic?->destinationUrl);
        $this->assertSame('/en/alumni?faculty=business-administration', $english?->destinationUrl);
        $this->assertSame('legacy_query', $arabic?->matchType);
    }

    public function test_unverified_alumni_queries_and_record_shapes_remain_unresolved(): void
    {
        $resolver = app(LegacyQueryRedirectResolverInterface::class);

        $this->assertNull($resolver->resolve('/alumni/index.php', 'page=list&ex=2&dir=graduated_students&lang=1&d=999'));
        $this->assertNull($resolver->resolve('/alumni/index.php', 'page=show&ex=2&dir=graduated_students&lang=1&d=2'));
        $this->assertNull($resolver->resolve('/alumni/index.php', 'page=list&ex=2&dir=graduated_students&lang=1&d=2&id=10'));
        $this->assertNull($resolver->resolve('/alumni/index.php', 'page=list&ex=2&dir=items&lang=1&d=2'));
        $this->assertNull($resolver->resolve('/alumni/index.php', 'page=list&ex=2&dir=graduated_students&lang=1'));
        $this->assertNull($resolver->resolve('/alumni/index.php', 'page=list&ex=2&dir=graduated_students&mylang=1&d=2'));
        $this->assertNull($resolver->resolve('/alumni/index.php', 'page=list&ex=2&dir=graduated_students&lang=1&d=2foo'));
        $this->assertNull($resolver->resolve('/alumni/index.php', 'page=list&ex=2&dir=graduated_students&lang=01&d=2'));
    }

    public function test_unknown_global_alumni_paths_remain_honest_404s(): void
    {
        $this->get('/alumni/index.php?page=show&ex=2&dir=graduated_students&lang=1&d=2&id=10')
            ->assertNotFound();
        $this->get('/en/alumni/10')->assertNotFound();
    }

    public function test_reviewed_global_alumni_list_is_redirected_by_http_continuity(): void
    {
        $this->get('/alumni/index.php?page=list&ex=2&dir=graduated_students&lang=1&d=2')
            ->assertStatus(301)
            ->assertRedirect('/ar/alumni?faculty=medicine');
    }
}
