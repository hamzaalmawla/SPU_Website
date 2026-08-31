<?php

declare(strict_types=1);

namespace Tests\Feature\Performance;

use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The homepage is the page that forms first impressions and it is the heaviest
 * document on the site. These assertions run against the rendered HTML, not the
 * templates, so a regression anywhere in the section partials is caught.
 */
final class HomepageImageDeliveryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);
    }

    /**
     * @return array<int, string>
     */
    private function imageTags(string $locale): array
    {
        $html = (string) $this->get('/'.$locale)->assertOk()->getContent();

        // Blade emits "->" inside attributes, so match up to the closing angle
        // bracket that is not inside a quoted value.
        preg_match_all('/<img\b[^>]*>/', $html, $matches);

        $this->assertNotEmpty($matches[0], 'The homepage rendered no images at all.');

        return $matches[0];
    }

    public function test_exactly_one_homepage_image_is_marked_as_the_lcp_candidate(): void
    {
        foreach (['ar', 'en'] as $locale) {
            $priority = array_values(array_filter(
                $this->imageTags($locale),
                static fn (string $tag): bool => str_contains($tag, 'fetchpriority="high"'),
            ));

            $this->assertCount(
                1,
                $priority,
                "Locale {$locale} must mark exactly one image as the LCP candidate, found: ".implode("\n", $priority),
            );

            $this->assertStringNotContainsString(
                'loading="lazy"',
                $priority[0],
                'The LCP image must stay eager.',
            );
        }
    }

    public function test_every_homepage_image_declares_intrinsic_dimensions(): void
    {
        foreach (['ar', 'en'] as $locale) {
            $undeclared = array_values(array_filter(
                $this->imageTags($locale),
                static fn (string $tag): bool => preg_match('/\bwidth="/', $tag) !== 1
                    || preg_match('/\bheight="/', $tag) !== 1,
            ));

            // The footer logo is a CMS upload whose intrinsic ratio is not known
            // at render time; its height is pinned in CSS instead.
            $undeclared = array_values(array_filter(
                $undeclared,
                static fn (string $tag): bool => ! str_contains($tag, 'mb-5 h-12 w-auto'),
            ));

            $this->assertSame(
                [],
                $undeclared,
                "Locale {$locale} has images without width/height, which shift layout during load:\n"
                    .implode("\n", $undeclared),
            );
        }
    }

    public function test_every_homepage_image_below_the_hero_defers_its_fetch(): void
    {
        foreach (['ar', 'en'] as $locale) {
            $eager = array_values(array_filter(
                $this->imageTags($locale),
                static fn (string $tag): bool => ! str_contains($tag, 'loading="lazy"')
                    && ! str_contains($tag, 'fetchpriority="high"')
                    // Header and hero markup renders above the fold and is
                    // deliberately eager; it is identifiable by its own classes.
                    && ! str_contains($tag, 'site-nav')
                    && ! str_contains($tag, 'brightness-0 invert rtl:rotate-180')
                    && ! str_contains($tag, 'h-[1rem] w-[1rem]')
                    && ! str_contains($tag, 'logo-spu.png')
                    && ! str_contains($tag, 'ic_outline-language.svg')
                    && ! str_contains($tag, 'mobileToggleIcon'),
            ));

            $this->assertSame(
                [],
                $eager,
                "Locale {$locale} fetches these below-the-fold images eagerly:\n".implode("\n", $eager),
            );
        }
    }

    public function test_every_homepage_image_decodes_off_the_main_thread(): void
    {
        foreach (['ar', 'en'] as $locale) {
            $blocking = array_values(array_filter(
                $this->imageTags($locale),
                static fn (string $tag): bool => ! str_contains($tag, 'decoding="async"')
                    && ! str_contains($tag, 'fetchpriority="high"'),
            ));

            $this->assertSame(
                [],
                $blocking,
                "Locale {$locale} decodes these images synchronously:\n".implode("\n", $blocking),
            );
        }
    }
}
