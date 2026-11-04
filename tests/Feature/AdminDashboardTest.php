<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Contracts\Cms\CmsWorkflowServiceInterface;
use App\Contracts\Page\FacultyPageServiceInterface;
use App\Contracts\SiteStats\SiteStatsRecorderInterface;
use App\DTOs\SiteStats\SiteStatsHitDTO;
use App\Filament\Widgets\NeedsAttention;
use App\Filament\Widgets\RecentPublishing;
use App\Filament\Widgets\SiteSearches;
use App\Filament\Widgets\SiteTrafficChart;
use App\Filament\Widgets\SiteTrafficOverview;
use App\Filament\Widgets\TopPages;
use App\Filament\Widgets\TrafficSources;
use App\Models\User\User;
use Database\Seeders\DatabaseSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * The admin dashboard: today's visitors and the AI assistant, the work queue
 * (only what the user can act on), traffic, pages, sources, searches and recent
 * publishing. Visitor statistics need `site_stats.view` (editors have it).
 */
final class AdminDashboardTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);
        $this->admin = User::query()->where('role_slug', 'super_admin')->firstOrFail();
        app()->setLocale('en');
    }

    public function test_the_dashboard_shows_visitors_pages_sources_and_searches(): void
    {
        $recorder = app(SiteStatsRecorderInterface::class);
        $host = (string) parse_url(url('/'), PHP_URL_HOST);
        $browser = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0 Safari/537.36';

        foreach (['/en', '/en/about', '/en/search?q=tuition+fees'] as $path) {
            $recorder->recordPageView(new SiteStatsHitDTO($path, $host, $host, 'https://www.google.com/', '10.1.1.1', $browser, 0));
        }
        $recorder->recordChatbotVisitor('10.1.1.1', $browser);

        $this->actingAs($this->admin, 'web');
        $this->get(Filament::getPanel('admin')->getUrl())->assertOk();
        // The admin request switched to the user's admin language.
        app()->setLocale('en');

        Livewire::test(SiteTrafficOverview::class)
            ->assertSee('Visitors today')
            ->assertSee('Pages viewed today')
            ->assertSee('On the site now')
            ->assertSee('Talked with the AI assistant');
        Livewire::test(SiteTrafficChart::class)->assertSee('Visitors and page views')->assertSee('3 page views in this period');
        Livewire::test(TopPages::class)->assertSee('Most viewed pages')->assertSee('/en/about');
        Livewire::test(TrafficSources::class)->assertSee('Search engines')->assertSee('google.com')->assertSee('Computer');
        Livewire::test(SiteSearches::class)->assertSee('tuition fees')->assertSee('Searched but not found');
    }

    public function test_the_work_queue_lists_new_messages_and_unpublished_pages(): void
    {
        DB::table('contact_messages')->insert([
            'locale' => 'en', 'name' => 'Visitor', 'email' => 'visitor@example.com', 'subject' => 'Question',
            'message' => 'Hello', 'status' => 'new', 'created_at' => now(), 'updated_at' => now(),
        ]);
        $target = 'facilities.artificial-intelligence.programs';
        app(CmsWorkflowServiceInterface::class)->saveDraft($target, app(FacultyPageServiceInterface::class)->getEditablePayload($target), (int) $this->admin->getKey());

        $this->actingAs($this->admin, 'web');

        Livewire::test(NeedsAttention::class)
            ->assertSee('Needs your attention')
            ->assertSee('New contact messages')
            ->assertSee('Pages with unpublished changes')
            ->assertSee(__('admin.cms.targets.facilities.programs'));

        $workflow = app(CmsWorkflowServiceInterface::class);
        $this->assertTrue($workflow->publish($target, (int) $this->admin->getKey()));

        Livewire::test(NeedsAttention::class)->assertDontSee('Pages with unpublished changes');
        Livewire::test(RecentPublishing::class)->assertSee('Recently published')->assertSee(__('admin.cms.targets.facilities.programs'))->assertSee('Published');
    }

    public function test_visitor_statistics_need_their_permission(): void
    {
        $editor = User::factory()->create(['role_slug' => 'editor', 'is_locked' => false]);
        $hr = User::factory()->create(['role_slug' => 'hr', 'is_locked' => false]);

        $this->actingAs($editor, 'web');
        $this->assertTrue(SiteTrafficOverview::canView());

        $this->actingAs($hr, 'web');
        $this->assertFalse(SiteTrafficOverview::canView());
        $this->assertFalse(TopPages::canView());
        $this->assertFalse(SiteSearches::canView());
        $this->get(Filament::getPanel('admin')->getUrl())->assertOk();
        app()->setLocale('en');
        // HR sees no contact inbox or page drafts it cannot act on.
        Livewire::test(NeedsAttention::class)->assertDontSee('New contact messages')->assertDontSee('Pages with unpublished changes');
    }
}
