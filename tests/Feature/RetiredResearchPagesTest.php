<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Research\ResearchPublication;
use App\Models\Research\ResearchPublicationTranslation;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * A retired research section must be genuinely gone, not a page that apologises.
 *
 * The empty-state page told visitors "this section will appear after bilingual
 * content is published and reviewed" — that exposes our editorial workflow and
 * reads as unfinished on a university site. None of these paths are linked from
 * navigation while unavailable, so they must simply 404.
 *
 * The landing is the exception: research is not empty — the publications archive
 * carries the migrated legacy research — so it redirects there instead.
 */
final class RetiredResearchPagesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);
    }

    public function test_retired_research_sections_return_404_rather_than_an_apology(): void
    {
        foreach (['centers', 'projects', 'themes', 'conferences', 'library', 'office', 'policies'] as $section) {
            foreach (['ar', 'en'] as $locale) {
                $this->get('/'.$locale.'/research/'.$section)->assertNotFound();
            }
        }
    }

    public function test_the_empty_state_wording_is_never_public(): void
    {
        $this->publishOnePublication();

        $paths = ['/ar/research', '/en/research', '/ar/research/publications', '/en/research/publications'];

        foreach ($paths as $path) {
            $response = $this->followingRedirects()->get($path);

            $response->assertOk()
                ->assertDontSee('غير متاح حالياً', false)
                ->assertDontSee('not currently available', false)
                ->assertDontSee('بعد نشر محتوى ثنائي اللغة', false)
                ->assertDontSee('after reviewed bilingual content is published', false);
        }
    }

    public function test_the_landing_redirect_target_always_renders(): void
    {
        // The redirect must never land on a 404. The publications archive is
        // data-backed, so it renders even with no matching records - that is an
        // ordinary empty-results state, not a retirement.
        foreach (['ar', 'en'] as $locale) {
            $this->get('/'.$locale.'/research')->assertRedirect('/'.$locale.'/research/publications');
            $this->get('/'.$locale.'/research/publications')->assertOk();
        }
    }

    public function test_the_research_landing_sends_visitors_to_the_publications_archive(): void
    {
        $this->publishOnePublication();

        foreach (['ar', 'en'] as $locale) {
            $this->get('/'.$locale.'/research')
                ->assertRedirect('/'.$locale.'/research/publications');
        }
    }

    public function test_sections_backed_by_real_data_stay_available(): void
    {
        $this->publishOnePublication();

        // Researcher profiles and publications come from the database, not the
        // CMS, so they must not be swept up by the retirement.
        $this->get('/ar/research/researchers')->assertOk();
        $this->get('/ar/research/publications')->assertOk();
    }

    /** Give the archive one real record, as production has 253. */
    private function publishOnePublication(): void
    {
        $publication = ResearchPublication::query()->create([
            'category_key' => 'journal',
            'publication_year' => 2024,
            'is_enabled' => true,
            'sort_order' => 1,
            // A native (non-legacy) publication is only public once dated.
            'published_at' => now()->subDay(),
        ]);

        foreach (['ar', 'en'] as $locale) {
            ResearchPublicationTranslation::query()->create([
                'research_publication_id' => $publication->getKey(),
                'locale' => $locale,
                'title' => 'Sample migrated publication '.$locale,
            ]);
        }
    }
}
