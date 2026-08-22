<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Contracts\Cms\CmsWorkflowServiceInterface;
use App\Contracts\Navigation\NavigationServiceInterface;
use App\Contracts\Research\ResearchPageServiceInterface;
use App\Models\User\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class ResearchPublishRetirePolicyTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);
    }

    public function test_empty_research_sections_are_not_exposed_in_ar_or_en_navigation(): void
    {
        foreach (['ar', 'en'] as $locale) {
            $navigation = app(NavigationServiceInterface::class)->getHeaderNavigation($locale);
            $urls = $this->navigationUrls($navigation->items);

            self::assertNotContains('/'.$locale.'/research', $urls);
            self::assertNotContains('/'.$locale.'/research/publications', $urls);
            self::assertNotContains('/'.$locale.'/research/projects', $urls);
            self::assertNotContains('/'.$locale.'/research/researchers', $urls);
            self::assertNotContains('/'.$locale.'/research/conferences', $urls);
            self::assertNotContains('/'.$locale.'/research/library', $urls);
            self::assertNotContains('/'.$locale.'/research/office', $urls);
            self::assertNotContains('/'.$locale.'/research/policies', $urls);
        }

        $this->get('/en/research')
            ->assertOk()
            ->assertSee('Research content is not currently available')
            ->assertDontSee('Research at SPU')
            ->assertDontSee('href="/en/research"', false);
        $this->get('/ar/research')
            ->assertOk()
            ->assertSee('محتوى البحث العلمي غير متاح حالياً')
            ->assertDontSee('href="/ar/research"', false);
    }

    public function test_published_bilingual_research_target_is_restored_to_ar_and_en_navigation_after_retire(): void
    {
        $research = app(ResearchPageServiceInterface::class);
        $workflow = app(CmsWorkflowServiceInterface::class);
        $author = User::query()->where('role_slug', 'super_admin')->firstOrFail();
        $userId = (int) $author->getKey();
        $payload = $research->getEditablePayload('research.projects');

        $workflow->saveDraft('research.projects', $payload, $userId);
        self::assertTrue($workflow->publish('research.projects', $userId));

        foreach (['ar', 'en'] as $locale) {
            $urls = $this->navigationUrls(app(NavigationServiceInterface::class)->getHeaderNavigation($locale)->items);
            self::assertContains('/'.$locale.'/research/projects', $urls);
            self::assertContains('/'.$locale.'/research/projects/ai-dental-diagnostics-system', $urls);
        }

        self::assertTrue($workflow->unpublish('research.projects', $userId));
        foreach (['ar', 'en'] as $locale) {
            $urls = $this->navigationUrls(app(NavigationServiceInterface::class)->getHeaderNavigation($locale)->items);
            self::assertNotContains('/'.$locale.'/research/projects', $urls);
            self::assertNotContains('/'.$locale.'/research/projects/ai-dental-diagnostics-system', $urls);
        }

        $workflow->saveDraft('research.projects', $payload, $userId);
        self::assertTrue($workflow->publish('research.projects', $userId));
        foreach (['ar', 'en'] as $locale) {
            $urls = $this->navigationUrls(app(NavigationServiceInterface::class)->getHeaderNavigation($locale)->items);
            self::assertContains('/'.$locale.'/research/projects', $urls);
        }
    }

    /** @param array<int, object> $items @return array<int, string> */
    private function navigationUrls(array $items): array
    {
        $urls = [];

        foreach ($items as $item) {
            if (is_string($item->resolvedUrl ?? null)) {
                $urls[] = $item->resolvedUrl;
            }

            if (is_array($item->children ?? null)) {
                $urls = [...$urls, ...$this->navigationUrls($item->children)];
            }
        }

        return $urls;
    }
}
