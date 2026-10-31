<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class UnprefixedRouteAndFallbackTest extends TestCase
{
    use RefreshDatabase;

    /**
     * /faculties stopped resolving when the colleges moved there from
     * /facilities: the routes and the sitemap were updated and this pattern was
     * not, so the obvious address answered 404 while the old one still worked.
     */
    public function test_unprefixed_section_paths_negotiate_a_locale_instead_of_404ing(): void
    {
        foreach (['/faculties', '/faculties/medicine', '/facilities', '/search', '/events', '/student-life', '/about', '/news'] as $path) {
            $response = $this->get($path);

            self::assertTrue(
                $response->isRedirect(),
                "{$path} must redirect to a locale-prefixed URL, got {$response->getStatusCode()}",
            );
        }
    }

    public function test_a_php_suffixed_path_is_still_excluded(): void
    {
        // The negative lookahead keeps legacy index.php URLs out of this route so
        // the continuity middleware can handle them.
        $this->get('/about.php')->assertNotFound();
    }

    public function test_the_legacy_project_alias_falls_back_to_the_project_listing(): void
    {
        $this->get('/ar/projects/detail')
            ->assertRedirect(route('public.research.projects.index', ['locale' => 'ar']));
    }

    public function test_the_legacy_article_alias_falls_back_to_the_news_index(): void
    {
        $this->get('/ar/news/article')
            ->assertRedirect(route('public.news.index', ['locale' => 'ar']));
    }
}
