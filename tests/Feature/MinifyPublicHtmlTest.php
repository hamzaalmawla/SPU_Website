<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Http\Middleware\MinifyPublicHtml;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

final class MinifyPublicHtmlTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_removes_indentation_that_follows_a_tag(): void
    {
        $html = "<div>\n    <p>\n        Hello\n    </p>\n</div>";

        // The run before </p> follows the text node "Hello", not a '>', so the
        // anchored rule deliberately leaves it. That is the safety property:
        // a match can never begin anywhere but immediately after a tag.
        $this->assertSame("<div>\n<p>\n        Hello\n    </p>\n</div>", $this->minify($html));
    }

    public function test_it_leaves_one_newline_so_inline_spacing_survives(): void
    {
        // Two inline-block elements separated by whitespace render with a gap.
        // Removing the whitespace entirely would close that gap up.
        $out = $this->minify("<span>a</span>\n        <span>b</span>");

        $this->assertSame("<span>a</span>\n<span>b</span>", $out);
        $this->assertStringContainsString("</span>\n<span>", $out);
    }

    public function test_it_never_rewrites_an_attribute_value(): void
    {
        // A match can only start after '>', so nothing inside a tag is reachable
        // however much whitespace an attribute carries.
        $html = "<div data-json='[{\n    \"a\": 1\n}]' class=\"x\">\n    <b>y</b>\n</div>";

        $out = $this->minify($html);

        $this->assertStringContainsString("[{\n    \"a\": 1\n}]", $out);
        $this->assertStringContainsString('<b>y</b>', $out);
    }

    public function test_a_gt_inside_an_attribute_value_cannot_trigger_a_collapse(): void
    {
        // Blade escapes '>' to '&gt;', so this needs raw output to occur - but
        // anchoring only on '>' would collapse the whitespace inside the
        // attribute itself, which is unrecoverable and undiagnosable.
        $html = "<div title=\"a>\n      b\">x</div>";

        $this->assertSame($html, $this->minify($html));
    }

    public function test_it_only_collapses_between_tags(): void
    {
        // Whitespace before a text node is left alone: the run must end at a
        // '<' to be provably inter-tag.
        $this->assertSame("<p>\n    text</p>", $this->minify("<p>\n    text</p>"));
        $this->assertSame("<p>\n<b>x</b></p>", $this->minify("<p>\n    <b>x</b></p>"));
    }

    #[DataProvider('preservedTags')]
    public function test_it_preserves_whitespace_significant_blocks(string $tag): void
    {
        $html = "<div>\n    <{$tag}>\n        keep     this\n    </{$tag}>\n</div>";

        $this->assertStringContainsString("\n        keep     this\n    ", $this->minify($html));
    }

    /** @return array<string, array{string}> */
    public static function preservedTags(): array
    {
        return [
            'pre' => ['pre'],
            'textarea' => ['textarea'],
            'script' => ['script'],
            'style' => ['style'],
        ];
    }

    public function test_it_ignores_non_html_responses(): void
    {
        $xml = "<urlset>\n    <url>\n        <loc>https://spu.edu.sy/ar</loc>\n    </url>\n</urlset>";

        $this->assertSame($xml, $this->minify($xml, 'application/xml'));
    }

    public function test_it_can_be_switched_off(): void
    {
        config()->set('edge.minify_html', false);

        $html = "<div>\n    <p>x</p>\n</div>";

        $this->assertSame($html, $this->minify($html));
    }

    public function test_it_corrects_a_stale_content_length(): void
    {
        $html = "<div>\n    <p>x</p>\n</div>";

        $response = new Response($html, 200, [
            'Content-Type' => 'text/html; charset=utf-8',
            'Content-Length' => (string) strlen($html),
        ]);

        $result = (new MinifyPublicHtml)->handle(Request::create('/ar'), static fn (): Response => $response);

        $this->assertSame(
            strlen((string) $result->getContent()),
            (int) $result->headers->get('Content-Length'),
        );
    }

    public function test_a_rendered_public_page_shrinks_without_losing_an_element(): void
    {
        $this->seed(DatabaseSeeder::class);

        $minified = (string) $this->get('/ar')->getContent();

        config()->set('edge.minify_html', false);
        Cache::flush();

        $raw = (string) $this->get('/ar')->getContent();

        // Same document, fewer bytes. Counting elements is what proves the
        // collapse is cosmetic rather than destructive.
        foreach (['<div', '<img', '<a ', '<span', '<button', 'csrf-token'] as $needle) {
            $this->assertSame(
                substr_count($raw, $needle),
                substr_count($minified, $needle),
                $needle.' count changed when the response was collapsed.',
            );
        }

        $this->assertLessThan(
            strlen($raw) * 0.9,
            strlen($minified),
            'The collapse should be removing a meaningful share of the response.',
        );
    }

    private function minify(string $body, string $contentType = 'text/html; charset=utf-8'): string
    {
        $response = new Response($body, 200, ['Content-Type' => $contentType]);

        return (string) (new MinifyPublicHtml)
            ->handle(Request::create('/ar'), static fn (): Response => $response)
            ->getContent();
    }
}
