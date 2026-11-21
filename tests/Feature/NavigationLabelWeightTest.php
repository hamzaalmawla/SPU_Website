<?php

declare(strict_types=1);

namespace Tests\Feature;

use Tests\TestCase;

/**
 * A layout guard that PHPUnit cannot express directly -- there is no layout
 * engine here to ask whether a label overflows -- so it pins the input that
 * decides the answer, and records the measurement that fixed it.
 *
 * The header's eight English labels ellipsised at 1280px. HacenTunisia is
 * scoped by unicode-range to the Arabic blocks, so Latin labels fall through
 * to Segoe UI / Tahoma / Arial; on a stack with no semibold face 600 and 700
 * rasterise identically. Measured live on 2026-09-04 at 1280px:
 *
 *     weight 700, old padding   7 of 8 clipped   (the state before Nav 1a)
 *     weight 600, old padding   7 of 8 clipped
 *     weight 700, new padding   8 of 8 clipped
 *     weight 600, new padding   8 of 8 clipped   (what Nav 1a shipped)
 *     weight 400, new padding   0 of 8 clipped
 *
 * Arabic never clipped in any configuration. So 600 is not a smaller 700 here,
 * it is the same 700, and the resting weight has to be 400 for the row to fit.
 * Restoring 600 as a tidy-up would silently bring the ellipsis back.
 */
final class NavigationLabelWeightTest extends TestCase
{
    public function test_the_resting_nav_label_weight_stays_at_400(): void
    {
        $css = (string) file_get_contents(
            resource_path('css/frontend/navigation.css')
        );

        $block = $this->ruleBody($css, '.site-nav-link');

        $this->assertMatchesRegularExpression(
            '~font-weight:\s*400\s*;~',
            $block,
            'The resting label weight decides whether the English row fits at 1280px.',
        );
    }

    public function test_the_active_item_keeps_the_weight_contrast(): void
    {
        $block = $this->ruleBody(
            (string) file_get_contents(resource_path('css/frontend/navigation.css')),
            '.site-nav-link--active',
        );

        $this->assertMatchesRegularExpression(
            '~font-weight:\s*700\s*;~',
            $block,
            'With the resting state at 400, the active item is what carries the hierarchy.',
        );
    }

    private function ruleBody(string $css, string $selector): string
    {
        $matched = preg_match(
            '~^\s*'.preg_quote($selector, '~').'\s*\{(.*?)^\s*\}~ms',
            $css,
            $matches,
        );

        $this->assertSame(1, $matched, "Could not find the {$selector} rule.");

        return $matches[1];
    }
}
