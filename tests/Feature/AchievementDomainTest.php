<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Contracts\Achievement\AchievementServiceInterface;
use App\Filament\Resources\AchievementResource;
use App\Models\Achievement\Achievement;
use App\Models\Achievement\AchievementCategory;
use App\Models\Achievement\AchievementCategoryTranslation;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class AchievementDomainTest extends TestCase
{
    use RefreshDatabase;

    public function test_homepage_returns_pinned_in_sort_order_then_newest_to_a_maximum_of_three(): void
    {
        $category = $this->category('academic', 'Academic', 'أكاديمي');
        $this->achievement('pinned-second', true, 2, '2026-01-04 10:00:00', [$category->id]);
        $this->achievement('pinned-first', true, 1, '2026-01-01 10:00:00', [$category->id]);
        $this->achievement('newest-fill', false, 0, '2026-01-03 10:00:00', [$category->id]);
        $this->achievement('older-fill', false, 0, '2026-01-02 10:00:00', [$category->id]);
        $this->achievement('private-record', true, 0, '2026-01-05 10:00:00', [$category->id], false);

        $cards = app(AchievementServiceInterface::class)->homepage('en', 3);

        $this->assertSame(['pinned-first', 'pinned-second', 'newest-fill'], $cards->pluck('title')->all());
        $this->assertCount(3, $cards);
        $this->assertCount(3, $cards->unique('id'));
    }

    public function test_archive_combines_multiple_active_categories_with_or_semantics_without_duplicates(): void
    {
        $academic = $this->category('academic', 'Academic', 'أكاديمي');
        $community = $this->category('community', 'Community', 'مجتمعي');
        $inactive = $this->category('inactive', 'Inactive', 'غير نشط', false);
        $this->achievement('both', false, 0, '2026-01-04 10:00:00', [$academic->id, $community->id]);
        $this->achievement('academic-only', false, 0, '2026-01-03 10:00:00', [$academic->id]);
        $this->achievement('inactive-only', false, 0, '2026-01-02 10:00:00', [$inactive->id]);

        $archive = app(AchievementServiceInterface::class)->archive('en', ['academic', 'community', 'inactive']);

        $this->assertSame(['academic', 'community'], $archive->selectedCategories);
        $this->assertSame(['both', 'academic-only'], $archive->results->items->pluck('title')->all());
        $this->assertSame(2, $archive->results->total);
        $this->assertSame(['academic', 'community'], collect($archive->categories)->pluck('slug')->all());
    }

    public function test_public_archive_renders_full_and_ajax_responses(): void
    {
        $category = $this->category('research', 'Research', 'بحثي');
        $this->achievement('Visible achievement', false, 0, '2026-01-01 10:00:00', [$category->id]);

        $this->get('/en/achievements')->assertOk()->assertSee('Visible achievement')->assertSee('Research');
        $this->get('/en/achievements?categories%5B0%5D=research', ['X-Requested-With' => 'XMLHttpRequest'])
            ->assertOk()
            ->assertSee('data-achievement-results', false)
            ->assertDontSee('<html', false);
    }

    public function test_homepage_renders_three_domain_achievements_and_configurable_archive_action(): void
    {
        $this->seed(DatabaseSeeder::class);

        $this->assertDatabaseCount('achievements', 3);
        $this->assertDatabaseHas('achievements', ['legacy_image_path' => '/images/dsc-1060.webp']);

        $this->get('/en')
            ->assertOk()
            ->assertSee('/en/achievements', false)
            ->assertSee('Clinical simulation excellence')
            ->assertSee('Applied research stories')
            ->assertSee('Community and student-life highlights');
    }

    public function test_achievement_admin_category_selector_only_uses_active_categories(): void
    {
        $resource = file_get_contents((new \ReflectionClass(AchievementResource::class))->getFileName());

        $this->assertIsString($resource);
        $this->assertStringContainsString('modifyQueryUsing: fn (Builder $query): Builder => $query->active()', $resource);
        $this->assertTrue(class_exists(AchievementCategoryTranslation::class));
    }

    private function category(string $slug, string $english, string $arabic, bool $active = true): AchievementCategory
    {
        $category = AchievementCategory::query()->create(['slug' => $slug, 'is_active' => $active]);
        $category->translations()->createMany([
            ['locale' => 'ar', 'name' => $arabic],
            ['locale' => 'en', 'name' => $english],
        ]);

        return $category;
    }

    /** @param array<int, int> $categoryIds */
    private function achievement(string $title, bool $pinned, int $sortOrder, string $publishedAt, array $categoryIds, bool $public = true): Achievement
    {
        $achievement = Achievement::query()->create([
            'status' => 'published',
            'published_at' => $publishedAt,
            'is_public' => $public,
            'pin_to_homepage' => $pinned,
            'sort_order' => $sortOrder,
        ]);
        $achievement->translations()->createMany([
            ['locale' => 'ar', 'title' => 'إنجاز '.$title, 'summary' => 'ملخص'],
            ['locale' => 'en', 'title' => $title, 'summary' => 'Summary'],
        ]);
        $achievement->categories()->sync($categoryIds);

        return $achievement;
    }
}
