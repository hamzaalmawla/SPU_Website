<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Contracts\Cms\CmsWorkflowServiceInterface;
use App\Contracts\Research\ResearchPageServiceInterface;
use App\Models\Research\ResearchPublication;
use App\Models\Research\ResearchPublicationTranslation;
use App\Models\User\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * The research landing shows the newest publication or project by itself (not
 * a hand-picked one), live publication and project counters, and the six
 * reviewed gateway cards.
 */
final class ResearchLandingLatestResearchTest extends TestCase
{
    use RefreshDatabase;

    private int $userId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
        Cache::flush();
        $this->userId = (int) User::query()->where('role_slug', 'super_admin')->firstOrFail()->getKey();

        $research = app(ResearchPageServiceInterface::class);
        $workflow = app(CmsWorkflowServiceInterface::class);
        foreach (['research.publications', 'research.projects'] as $targetKey) {
            $workflow->saveDraft($targetKey, $research->getEditablePayload($targetKey), $this->userId);
            $workflow->publish($targetKey, $this->userId);
        }
    }

    public function test_the_newest_publication_replaces_the_stored_featured_one(): void
    {
        $this->publishLanding($this->oldLanding());

        $latest = app(ResearchPageServiceInterface::class)->landing('en')->data['latestResearch'];
        $this->assertSame('publication', $latest['kind']);
        $this->assertSame('/en/research/publications/renewable-energy-integration-syrian-grid', $latest['url']);

        $this->createPublication('Newest Landing Paper', now()->subDay()->toDateString(), '10.5555/newest.1');

        $latest = app(ResearchPageServiceInterface::class)->landing('en')->data['latestResearch'];
        $this->assertSame('Newest Landing Paper', $latest['title']);
        $this->assertSame('Latest research', $latest['sectionTitle']);
        $this->assertSame('10.5555/newest.1', $latest['doi']);
        $this->assertSame('https://doi.org/10.5555/newest.1', $latest['secondary']['url']);

        $this->get('/en/research')
            ->assertOk()
            ->assertSee('Newest Landing Paper')
            ->assertSee('Read the publication')
            ->assertDontSee('Hand-picked Landing Paper');
    }

    public function test_a_year_only_paper_shows_its_year_not_the_first_of_january(): void
    {
        $this->publishLanding($this->oldLanding());
        $this->createPublication('Year Only Landing Paper', now()->year.'-01-01', null);

        $latest = app(ResearchPageServiceInterface::class)->landing('ar')->data['latestResearch'];
        $published = collect($latest['meta'])->firstWhere('label', 'تاريخ النشر');

        $this->assertSame('Year Only Landing Paper', $latest['title']);
        $this->assertSame((string) now()->year, $published['value'] ?? null);
        $this->assertNull($latest['secondary']);
    }

    public function test_a_project_started_after_the_newest_paper_is_shown_instead(): void
    {
        $workflow = app(CmsWorkflowServiceInterface::class);
        $projects = $workflow->getPublishedPayload('research.projects');
        foreach (['ar', 'en'] as $locale) {
            $projects['translations'][$locale]['items'][] = [
                ...$projects['translations'][$locale]['items'][0],
                'id' => 'proj-landing', 'slug' => 'landing-newest-project', 'title' => 'Newest Landing Project',
                'status' => 'ongoing', 'startYear' => '2099', 'endYear' => '',
            ];
        }
        $workflow->saveDraft('research.projects', $projects, $this->userId);
        $workflow->publish('research.projects', $this->userId);
        $this->publishLanding($this->oldLanding());

        $latest = app(ResearchPageServiceInterface::class)->landing('en')->data['latestResearch'];

        $this->assertSame('project', $latest['kind']);
        $this->assertSame('Newest Landing Project', $latest['title']);
        $this->assertSame('/en/research/projects/landing-newest-project', $latest['url']);
        $this->assertSame('Research project · Ongoing', $latest['eyebrow']);
        $this->assertSame('Since 2099', collect($latest['meta'])->firstWhere('label', 'Period')['value'] ?? null);
    }

    public function test_the_projects_counter_follows_the_projects_page(): void
    {
        $content = $this->oldLanding();
        $content['stats'][] = ['counter' => 'projects', 'value' => '', 'label' => 'Research projects'];
        $this->publishLanding($content);

        $service = app(ResearchPageServiceInterface::class);
        $stats = $service->landing('en')->data['stats'];

        $this->assertSame(5, $service->projectCount('en'));
        $this->assertSame('5', $stats[3]['value']);
        $this->assertSame('7', $stats[1]['value']);
    }

    public function test_the_migration_completes_the_published_landing_and_keeps_editor_changes(): void
    {
        $this->publishLanding($this->oldLanding());
        $workflow = app(CmsWorkflowServiceInterface::class);

        $migration = require database_path('migrations/2026_11_06_000003_complete_research_landing_stats_and_gateway.php');
        $migration->up();
        $migration->up();
        Cache::flush();

        $published = $workflow->getPublishedPayload('research.index');
        foreach (['ar', 'en'] as $locale) {
            $content = $published['translations'][$locale];
            $this->assertSame('Edited hero '.$locale, $content['hero']['title']);
            $this->assertCount(4, $content['stats']);
            $this->assertSame('Edited years', $content['stats'][2]['value']);
            $this->assertSame('projects', $content['stats'][3]['counter']);
            $this->assertCount(6, $content['gateway']['cards']);
            $this->assertSame('/'.$locale.'/research/library', $content['gateway']['cards'][5]['url']);
            $this->assertArrayNotHasKey('featuredPublication', $content);
        }
        $this->assertSame('أحدث الأبحاث', $published['translations']['ar']['latestResearch']['sectionTitle']);

        $this->get('/ar/research')
            ->assertOk()
            ->assertSee('أحدث الأبحاث')
            ->assertSee('/ar/research/conferences', false)
            ->assertSee('/ar/research/office', false)
            ->assertSee('مشاريع البحث');
    }

    public function test_the_migration_shows_an_update_when_pretending(): void
    {
        $migration = require database_path('migrations/2026_11_06_000003_complete_research_landing_stats_and_gateway.php');

        $queries = DB::connection()->pretend(fn () => $migration->up());

        $this->assertMatchesRegularExpression('/^update /i', $queries[0]['query'] ?? '');
    }

    /** @param array<string, mixed> $content */
    private function publishLanding(array $content): void
    {
        $workflow = app(CmsWorkflowServiceInterface::class);
        $ar = $content;
        $ar['hero']['title'] = 'Edited hero ar';
        $content['hero']['title'] = 'Edited hero en';
        $workflow->saveDraft('research.index', ['translations' => ['ar' => $ar, 'en' => $content]], $this->userId);
        $workflow->publish('research.index', $this->userId);
    }

    /** @return array<string, mixed> The landing as it was published before 2026-11-06. */
    private function oldLanding(): array
    {
        return [
            'hero' => ['eyebrow' => 'Research', 'title' => 'Hero', 'summary' => 'Summary.', 'cta1' => 'Publications', 'cta1Url' => '/en/research/publications', 'cta2' => 'Researchers', 'cta2Url' => '/en/research/researchers', 'backgroundImage' => '/images/uni-main-place.JPG'],
            'stats' => [
                ['counter' => 'publications', 'value' => '', 'label' => 'Publications'],
                ['value' => '7', 'label' => 'Researchers'],
                ['value' => 'Edited years', 'label' => 'Years covered'],
            ],
            'featuredPublication' => [
                'sectionTitle' => 'Featured publication', 'title' => 'Hand-picked Landing Paper', 'slug' => 'hand-picked',
                'summary' => 'Old.', 'doi' => '10.1016/j.injury.2017.02.005',
            ],
            'gateway' => ['sectionTitle' => 'Where to start', 'cards' => [
                ['number' => '01', 'title' => 'Publications', 'summary' => 'Archive.', 'cta' => 'Browse', 'url' => '/en/research/publications'],
                ['number' => '02', 'title' => 'Researchers', 'summary' => 'People.', 'cta' => 'Meet', 'url' => '/en/research/researchers'],
                ['number' => '03', 'title' => 'Expert Finder', 'summary' => 'Experts.', 'cta' => 'Find', 'url' => '/en/research/expert-finder'],
            ]],
        ];
    }

    private function createPublication(string $title, string $publishedAt, ?string $doi): void
    {
        $publication = ResearchPublication::query()->create([
            'published_at' => $publishedAt,
            'publication_year' => (int) substr($publishedAt, 0, 4),
            'doi' => $doi,
            'is_enabled' => true,
        ]);
        ResearchPublicationTranslation::query()->create([
            'research_publication_id' => $publication->getKey(),
            'locale' => 'en',
            'title' => $title,
        ]);
    }
}
