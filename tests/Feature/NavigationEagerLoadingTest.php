<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Contracts\Navigation\MenuServiceInterface;
use App\Services\Navigation\MenuService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

/**
 * The navigation tree is built on every cold public request, on a host with five
 * PHP workers and no OPcache. A lazy load here is not one query — it is one per
 * menu item, and it is invisible because the page renders correctly either way.
 *
 * Laravel's own lazy-loading guard is the assertion: it throws the moment a
 * relation is read that was not loaded, which no amount of output comparison can
 * detect.
 */
final class NavigationEagerLoadingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);
    }

    protected function tearDown(): void
    {
        Model::preventLazyLoading(false);

        parent::tearDown();
    }

    public function test_building_a_tree_never_lazy_loads(): void
    {
        Cache::flush();
        Model::preventLazyLoading();

        $menus = app(MenuServiceInterface::class);

        // Every tree, in both locales — the eager-load spec is shared, but the
        // trees differ in depth and a shallow one would hide the defect.
        foreach (['ar', 'en'] as $locale) {
            $header = $menus->getHeaderTree($locale);
            $footer = $menus->getFooterTree($locale);
            $utility = $menus->getUtilityTree($locale);

            $this->assertNotSame([], $header->items, "The {$locale} header tree is empty, so this proves nothing.");
            $this->assertSame($locale, $header->locale);
            $this->assertSame($locale, $footer->locale);
            $this->assertSame($locale, $utility->locale);
        }
    }

    public function test_rendering_a_public_page_never_lazy_loads_navigation(): void
    {
        Cache::flush();
        Model::preventLazyLoading();

        // The guard is global, so this also covers everything else the homepage
        // touches on a cold cache.
        $this->get('/ar')->assertOk();
    }

    public function test_the_eager_load_reaches_one_level_past_the_deepest_item(): void
    {
        // mapItem() reads ->children on the deepest items too. If the spec ever
        // stops covering that level, the guard above starts failing — this test
        // says why, so the next reader does not have to rediscover it.
        $reflection = new \ReflectionClass(MenuService::class);
        $maxDepth = $reflection->getConstant('MAX_DEPTH');

        $this->assertIsInt($maxDepth);

        $method = $reflection->getMethod('treeEagerLoad');
        $method->setAccessible(true);

        /** @var array<int|string, mixed> $spec */
        $spec = $method->invoke(app(MenuService::class), 'ar');

        $paths = array_merge(array_keys(array_filter($spec, 'is_string', ARRAY_FILTER_USE_KEY)), array_values(array_filter($spec, 'is_string')));
        $deepest = str_repeat('children.', $maxDepth).'children';

        $this->assertContains(
            $deepest,
            $paths,
            'The spec must load one level past MAX_DEPTH, or the deepest items lazy-load their empty children.',
        );
    }
}
