<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Filament\Pages\ManageHomepage;
use App\Models\Homepage\HomepageDraft;
use App\Models\User\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\TestCase;

final class PerformanceBudgetTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);
        Cache::clear();
    }

    public function test_cold_homepage_stays_within_query_and_html_budgets(): void
    {
        DB::flushQueryLog();
        DB::enableQueryLog();

        $response = $this->get('/en')->assertOk();
        $queryCount = count(DB::getQueryLog());
        DB::disableQueryLog();

        $this->assertLessThanOrEqual(80, $queryCount, "Cold homepage used {$queryCount} database queries.");
        $this->assertLessThanOrEqual(300_000, strlen((string) $response->getContent()));
    }

    public function test_default_homepage_editor_stays_section_scoped(): void
    {
        $this->actingAs(User::query()->where('role_slug', 'super_admin')->firstOrFail());

        $response = $this->get('/admin/manage-homepage')->assertOk();

        $this->assertLessThanOrEqual(750_000, strlen((string) $response->getContent()));
        $response->assertSee('Choose one section to edit');
    }

    public function test_default_research_editor_does_not_render_every_module(): void
    {
        $this->actingAs(User::query()->where('role_slug', 'super_admin')->firstOrFail());

        $response = $this->get('/admin/manage-research')->assertOk();

        $this->assertLessThanOrEqual(1_500_000, strlen((string) $response->getContent()));
        $response->assertSee('research.index', false);
    }

    public function test_section_scoped_homepage_save_preserves_all_other_sections(): void
    {
        $this->actingAs(User::query()->where('role_slug', 'super_admin')->firstOrFail());

        Livewire::withQueryParams(['section' => 'hero'])
            ->test(ManageHomepage::class)
            ->set('data.hero.en.headline', 'Performance-safe hero update')
            ->call('save')
            ->assertHasNoErrors();

        $sections = HomepageDraft::query()->latest('id')->firstOrFail()->payload_json['homepage']['sections'] ?? [];
        $keys = collect($sections)->pluck('key')->all();

        $this->assertCount(11, $sections);
        $this->assertContains('hero', $keys);
        $this->assertContains('footer', $keys);
        $this->assertContains('research_studies', $keys);
    }
}
