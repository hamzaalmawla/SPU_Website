<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Contracts\Research\ResearchPageServiceInterface;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The research landing is editorial chrome SPU has not published yet, so
 * ResearchController::index redirects /{locale}/research to the publications
 * archive instead of showing an apology page.
 *
 * MenuService used to ask whether that path was *indexable*, get false, and
 * drop the href. The header then fell to its no-url branch and rendered
 * "Research" as a <button>: on desktop the dropdown was already open from
 * hover, so clicking the item only toggled it shut and the click appeared to
 * do nothing. It was the one header item with no href on v2.spu.edu.sy.
 *
 * This class deliberately does NOT publish research.index. That unpublished
 * state is the shipped one, and the sibling HeaderNavigationRenderingTest
 * publishes it in setUp, which is why the regression never surfaced there.
 */
final class ResearchNavigationLandingGateTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);
    }

    public function test_the_unpublished_landing_is_navigable_without_becoming_indexable(): void
    {
        $research = app(ResearchPageServiceInterface::class);

        foreach (['en', 'ar'] as $locale) {
            $path = '/'.$locale.'/research';

            $this->assertFalse(
                $research->isPubliclyAvailablePath($locale, $path),
                'An unpublished landing is a redirect, so the sitemap must keep excluding it.',
            );

            $this->assertTrue(
                $research->isNavigablePath($locale, $path),
                'A click on the landing still has to reach a page.',
            );
        }
    }

    public function test_the_landing_redirects_rather_than_failing(): void
    {
        // The premise the navigable answer rests on. If this ever stops
        // redirecting, isNavigablePath() is lying and the menu item goes back
        // to pointing at a dead end.
        foreach (['en', 'ar'] as $locale) {
            $this->get('/'.$locale.'/research')
                ->assertRedirect('/'.$locale.'/research/publications');

            $this->get('/'.$locale.'/research/publications')->assertOk();
        }
    }

    public function test_the_research_header_item_keeps_its_link_while_the_landing_is_unpublished(): void
    {
        foreach (['en', 'ar'] as $locale) {
            $html = (string) $this->get('/'.$locale)->assertOk()->getContent();

            $item = $this->headerItemMarkupFor($html, '/'.$locale.'/research');

            $this->assertStringContainsString(
                'href="/'.$locale.'/research"',
                $item,
                'The research item must render as a link, not a dropdown-only button.',
            );

            $this->assertStringContainsString(
                'site-nav-link-composite',
                $item,
                'A parent with both a url and children renders the link + chevron pair.',
            );
        }
    }

    public function test_no_header_item_renders_without_a_destination(): void
    {
        // Generalises the bug rather than only pinning research: any header
        // item that reaches the no-url branch while holding children becomes
        // the same silent dead click.
        foreach (['en', 'ar'] as $locale) {
            $html = (string) $this->get('/'.$locale)->assertOk()->getContent();

            $this->assertSame(
                0,
                preg_match_all('~<button[^>]*\bclass="site-nav-link[ "]~', $this->primaryNavMarkup($html)),
                'A header item with no href only toggles its own dropdown, so clicking it does nothing.',
            );
        }
    }

    public function test_restoring_the_link_does_not_cost_the_dropdown_its_featured_profiles(): void
    {
        // $isResearchMenu used to be derived from the parent's resolvedUrl, so
        // handing "Research" its href back also flipped the dropdown to flat
        // links and dropped every third-level entry -- the featured researcher
        // profiles among them. Fixing a dead click must not quietly delete
        // content visitors can reach today.
        $html = (string) $this->get('/en')->assertOk()->getContent();
        $dropdown = $this->headerItemMarkupFor($html, '/en/research');

        $this->assertStringContainsString('site-nav-dropdown-featured', $dropdown);
        $this->assertStringContainsString('/en/about/profile/', $dropdown);
    }

    private function primaryNavMarkup(string $html): string
    {
        $matched = preg_match('~<ul class="site-nav-list">(.*?)</ul>~s', $html, $matches);

        $this->assertSame(1, $matched, 'The primary navigation list did not render.');

        return $matches[1];
    }

    private function headerItemMarkupFor(string $html, string $url): string
    {
        foreach (preg_split('~(?=<li class="site-nav-item")~', $this->primaryNavMarkup($html)) ?: [] as $item) {
            if (str_contains($item, 'href="'.$url.'"')) {
                return $item;
            }
        }

        return '';
    }
}
