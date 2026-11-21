<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Navigation\MenuItem;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The menu migration that puts Centers in the live header, after Faculties.
 */
final class NavigationCentersMenuMigrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_centers_goes_after_faculties_and_the_rest_move_down(): void
    {
        $about = $this->item(['label' => 'About', 'sort_order' => 1]);
        $faculties = $this->item(['label' => 'Faculties', 'url' => '/en/faculties', 'sort_order' => 2]);
        $admissions = $this->item(['label' => 'Admissions', 'sort_order' => 3]);
        $contact = $this->item(['label' => 'Contact', 'sort_order' => 8]);
        $arabicFaculties = $this->item(['locale' => 'ar', 'label' => 'الكليات', 'url' => '/ar/faculties', 'sort_order' => 2]);

        $this->migration()->up();

        $centers = MenuItem::query()->where('url', '/en/centers')->firstOrFail();
        $this->assertSame(['Centers', 3, true], [$centers->label, (int) $centers->sort_order, (bool) $centers->is_enabled]);
        $this->assertSame([1, 2, 4, 9], [(int) $about->fresh()->sort_order, (int) $faculties->fresh()->sort_order, (int) $admissions->fresh()->sort_order, (int) $contact->fresh()->sort_order]);
        $this->assertFalse(MenuItem::query()->where('parent_id', $centers->id)->exists());

        $arabic = MenuItem::query()->where('url', '/ar/centers')->firstOrFail();
        $this->assertSame(['المراكز', 3], [$arabic->label, (int) $arabic->sort_order]);
        $this->assertSame(2, (int) $arabicFaculties->fresh()->sort_order);

        // Running it again adds nothing and moves nothing.
        $this->migration()->up();
        $this->assertSame(1, MenuItem::query()->where('url', '/en/centers')->count());
        $this->assertSame(4, (int) $admissions->fresh()->sort_order);
    }

    public function test_a_header_that_already_links_to_centers_is_left_alone(): void
    {
        $this->item(['label' => 'Faculties', 'url' => '/en/faculties', 'sort_order' => 2]);
        $own = $this->item(['label' => 'Our Centers', 'url' => '/en/centers', 'sort_order' => 7]);

        $this->migration()->up();

        $this->assertSame(1, MenuItem::query()->where('url', '/en/centers')->count());
        $this->assertSame(['Our Centers', 7], [$own->fresh()->label, (int) $own->fresh()->sort_order]);
    }

    public function test_the_seeded_header_shows_centers(): void
    {
        $this->seed(DatabaseSeeder::class);

        $this->get('/en')->assertOk()->assertSee('href="/en/centers"', false);
        $this->get('/ar')->assertOk()->assertSee('href="/ar/centers"', false)->assertSee('المراكز');
    }

    private function migration(): object
    {
        return require database_path('migrations/2026_11_02_000003_add_centers_to_header_menu.php');
    }

    /** @param array<string, mixed> $attributes */
    private function item(array $attributes): MenuItem
    {
        $item = new MenuItem;
        $item->forceFill(array_merge([
            'type' => 'header',
            'group_key' => 'header',
            'locale' => 'en',
            'target_kind' => 'url',
            'url' => null,
            'is_enabled' => true,
            'is_utility' => false,
            'open_in_new_tab' => false,
            'sort_order' => 1,
            'depth' => 0,
        ], $attributes))->save();

        return $item;
    }
}
