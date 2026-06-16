<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Support\HtmlSanitizer;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Property-based tests for HtmlSanitizer.
 *
 * Feature: codebase-audit-remediation, Property 3: HTML Sanitization on Write Paths
 * Feature: codebase-audit-remediation, Property 4: Sanitization Preserves Valid Content
 *
 * **Validates: Requirements 16.1, 16.2, 16.3, 16.4**
 */
#[Group('property')]
final class HtmlSanitizerPropertyTest extends TestCase
{
    private HtmlSanitizer $sanitizer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->sanitizer = new HtmlSanitizer;
    }

    // ──────────────────────────────────────────────────────────────────────
    // Constants
    // ──────────────────────────────────────────────────────────────────────

    /**
     * Allowed HTML tags per the HtmlSanitizer allowlist.
     */
    private const ALLOWED_TAGS = [
        'p', 'br', 'strong', 'em', 'ul', 'ol', 'li', 'a', 'h1', 'h2', 'h3',
        'h4', 'h5', 'h6', 'blockquote', 'img', 'table', 'thead', 'tbody',
        'tr', 'th', 'td', 'span', 'div', 'figure', 'figcaption',
    ];

    /**
     * Unsafe tags that should be stripped by the sanitizer.
     */
    private const UNSAFE_TAGS = [
        'script', 'iframe', 'object', 'embed', 'form', 'input', 'textarea',
        'select', 'button', 'style', 'link', 'meta', 'base', 'applet',
    ];

    /**
     * Unsafe attributes that should be stripped.
     */
    private const UNSAFE_ATTRS = [
        'onclick', 'onerror', 'onload', 'onmouseover', 'onfocus', 'onblur',
        'onsubmit', 'onchange', 'onkeydown', 'onkeyup',
    ];

    /**
     * Arabic text samples for RTL content testing.
     */
    private const ARABIC_TEXTS = [
        'الجامعة السورية الخاصة',
        'كلية الطب البشري',
        'كلية الصيدلة',
        'كلية الهندسة المعلوماتية',
        'كلية إدارة الأعمال',
        'البحث العلمي والدراسات',
        'القبول والتسجيل',
        'الأخبار والفعاليات',
        'المرافق الطبية والخدمات',
        'مرحباً بكم في جامعتنا',
        'التعليم العالي والبحث العلمي',
        'برامج الدراسات العليا',
        'الحياة الجامعية',
        'خدمات الطلاب',
        'المكتبة الإلكترونية',
    ];

    // ──────────────────────────────────────────────────────────────────────
    // Generators — Property 3 (mixed safe/unsafe HTML)
    // ──────────────────────────────────────────────────────────────────────

    /**
     * Generate a random HTML string mixing safe and unsafe elements.
     */
    private static function randomHtmlString(): string
    {
        $parts = [];
        $segmentCount = random_int(1, 6);

        for ($i = 0; $i < $segmentCount; $i++) {
            $choice = random_int(0, 5);
            $parts[] = match ($choice) {
                0 => self::randomSafeElement(),
                1 => self::randomUnsafeElement(),
                2 => self::randomEventHandlerElement(),
                3 => self::randomNestedEncodingVector(),
                4 => self::randomJavascriptHref(),
                5 => self::randomPlainText(),
            };
        }

        return implode('', $parts);
    }

    /**
     * Generate a random element using an allowed tag.
     */
    private static function randomSafeElement(): string
    {
        $tag = self::ALLOWED_TAGS[random_int(0, count(self::ALLOWED_TAGS) - 1)];
        $text = 'Content'.random_int(1, 9999);

        return match ($tag) {
            'br' => '<br>',
            'img' => '<img src="https://example.com/img'.random_int(1, 999).'.jpg" alt="'.$text.'">',
            'a' => '<a href="https://example.com/'.random_int(1, 999).'">'.$text.'</a>',
            'ul', 'ol' => "<{$tag}><li>{$text}</li></{$tag}>",
            'li' => "<ul><li>{$text}</li></ul>",
            'table' => "<table><tbody><tr><td>{$text}</td></tr></tbody></table>",
            'thead' => "<table><thead><tr><th>{$text}</th></tr></thead></table>",
            'tbody' => "<table><tbody><tr><td>{$text}</td></tr></tbody></table>",
            'tr' => "<table><tbody><tr><td>{$text}</td></tr></tbody></table>",
            'th' => "<table><thead><tr><th>{$text}</th></tr></thead></table>",
            'td' => "<table><tbody><tr><td>{$text}</td></tr></tbody></table>",
            'figure' => "<figure><figcaption>{$text}</figcaption></figure>",
            'figcaption' => "<figure><figcaption>{$text}</figcaption></figure>",
            default => "<{$tag}>{$text}</{$tag}>",
        };
    }

    /**
     * Generate a random element using an unsafe tag.
     */
    private static function randomUnsafeElement(): string
    {
        $tag = self::UNSAFE_TAGS[random_int(0, count(self::UNSAFE_TAGS) - 1)];
        $text = 'Unsafe'.random_int(1, 9999);

        return match ($tag) {
            'script' => '<script>alert("xss'.random_int(1, 999).'")</script>',
            'iframe' => '<iframe src="https://evil.com/'.random_int(1, 999).'"></iframe>',
            'style' => '<style>body{display:none}</style>',
            'link' => '<link rel="stylesheet" href="https://evil.com/style.css">',
            'meta' => '<meta http-equiv="refresh" content="0;url=https://evil.com">',
            'input' => '<input type="text" value="'.$text.'">',
            default => "<{$tag}>{$text}</{$tag}>",
        };
    }

    /**
     * Generate an element with an unsafe event handler attribute.
     */
    private static function randomEventHandlerElement(): string
    {
        $attr = self::UNSAFE_ATTRS[random_int(0, count(self::UNSAFE_ATTRS) - 1)];
        $tag = self::ALLOWED_TAGS[random_int(0, count(self::ALLOWED_TAGS) - 1)];
        $text = 'Handler'.random_int(1, 9999);

        if ($tag === 'br' || $tag === 'img') {
            return '<div '.$attr.'="alert('.random_int(1, 99).')">'.$text.'</div>';
        }

        return '<'.$tag.' '.$attr.'="alert('.random_int(1, 99).')">'.$text.'</'.$tag.'>';
    }

    /**
     * Generate a nested encoding XSS vector.
     */
    private static function randomNestedEncodingVector(): string
    {
        $vectors = [
            '<img src=x onerror=&#x61;&#x6C;&#x65;&#x72;&#x74;(1)>',
            '<div style="background:url(&#106;avascript:alert(1))">Encoded</div>',
            '<a href="&#106;&#97;&#118;&#97;&#115;&#99;&#114;&#105;&#112;&#116;:alert(1)">Link</a>',
            '<p>&#60;script&#62;alert(1)&#60;/script&#62;</p>',
        ];

        return $vectors[random_int(0, count($vectors) - 1)];
    }

    /**
     * Generate a javascript: href vector.
     */
    private static function randomJavascriptHref(): string
    {
        $vectors = [
            '<a href="javascript:alert(1)">JSLink</a>',
            '<a href="JAVASCRIPT:alert(1)">JSLink</a>',
            '<a href="javascript:void(0)">JSLink</a>',
            '<a href=" javascript:alert(1)">JSLink</a>',
        ];

        return $vectors[random_int(0, count($vectors) - 1)];
    }

    /**
     * Generate random plain text.
     */
    private static function randomPlainText(): string
    {
        $words = ['university', 'education', 'research', 'faculty', 'campus',
            'student', 'program', 'academic', 'science', 'technology'];
        $count = random_int(1, 5);
        $sentence = [];

        for ($i = 0; $i < $count; $i++) {
            $sentence[] = $words[random_int(0, count($words) - 1)];
        }

        return implode(' ', $sentence);
    }

    // ──────────────────────────────────────────────────────────────────────
    // Generators — Property 4 (valid Arabic HTML)
    // ──────────────────────────────────────────────────────────────────────

    /**
     * Generate valid HTML using only allowed tags with Arabic RTL text.
     *
     * Produces structurally valid HTML that should pass through the sanitizer
     * with all text content preserved. Avoids <a> tags since HTMLPurifier's
     * Nofollow/TargetBlank transformers produce non-deterministic attribute
     * ordering between passes (a known HTMLPurifier behavior, not a bug).
     */
    private static function randomValidArabicHtml(): string
    {
        $parts = [];
        $segmentCount = random_int(1, 5);

        for ($i = 0; $i < $segmentCount; $i++) {
            $parts[] = self::randomValidArabicElement();
        }

        return implode('', $parts);
    }

    /**
     * Generate a single valid HTML element with Arabic text.
     *
     * Only uses tags from the allowed list and valid attributes.
     */
    private static function randomValidArabicElement(): string
    {
        $inlineTags = ['p', 'strong', 'em', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6',
            'blockquote', 'span', 'div'];

        $choice = random_int(0, 4);
        $text = self::ARABIC_TEXTS[random_int(0, count(self::ARABIC_TEXTS) - 1)];

        return match ($choice) {
            // Simple inline tag with Arabic text
            0, 1 => self::randomSimpleArabicTag($inlineTags, $text),
            // List with Arabic items
            2 => self::randomArabicList($text),
            // Figure with Arabic caption
            3 => '<figure><figcaption>'.$text.'</figcaption></figure>',
            // Nested structure: div > p with Arabic text
            4 => '<div><p>'.$text.'</p></div>',
        };
    }

    /**
     * Generate a simple tag wrapping Arabic text.
     *
     * @param  list<string>  $tags
     */
    private static function randomSimpleArabicTag(array $tags, string $text): string
    {
        $tag = $tags[random_int(0, count($tags) - 1)];

        return "<{$tag}>{$text}</{$tag}>";
    }

    /**
     * Generate an Arabic list element.
     */
    private static function randomArabicList(string $text): string
    {
        $listTag = random_int(0, 1) === 0 ? 'ul' : 'ol';
        $itemCount = random_int(1, 3);
        $items = '';

        for ($i = 0; $i < $itemCount; $i++) {
            $itemText = self::ARABIC_TEXTS[random_int(0, count(self::ARABIC_TEXTS) - 1)];
            $items .= '<li>'.$itemText.'</li>';
        }

        return "<{$listTag}>{$items}</{$listTag}>";
    }

    /**
     * Extract visible text content from HTML by stripping all tags.
     */
    private static function extractTextContent(string $html): string
    {
        return trim(preg_replace('/\s+/', ' ', strip_tags($html)));
    }

    /**
     * Normalize HTML attribute order within tags for comparison.
     *
     * HTMLPurifier may reorder attributes between passes (e.g., rel/target on <a> tags).
     * This normalizes attribute order so we can compare semantic equivalence.
     */
    private static function normalizeAttributeOrder(string $html): string
    {
        return preg_replace_callback(
            '/<([a-z][a-z0-9]*)\s+([^>]+?)(\s*\/?)>/i',
            function (array $matches): string {
                $tag = $matches[1];
                $attrString = $matches[2];
                $selfClose = $matches[3];

                // Parse attributes
                preg_match_all('/([a-z\-]+)="([^"]*)"/', $attrString, $attrMatches, PREG_SET_ORDER);

                $attrs = [];
                foreach ($attrMatches as $attr) {
                    $attrs[$attr[1]] = $attr[2];
                }

                // Sort by attribute name
                ksort($attrs);

                $normalized = [];
                foreach ($attrs as $name => $value) {
                    $normalized[] = $name.'="'.$value.'"';
                }

                return '<'.$tag.' '.implode(' ', $normalized).$selfClose.'>';
            },
            $html
        );
    }

    // ──────────────────────────────────────────────────────────────────────
    // Property 3 — HTML Sanitization on Write Paths
    // ──────────────────────────────────────────────────────────────────────

    /**
     * Data provider generating 100 random HTML strings with mixed safe/unsafe content.
     *
     * @return iterable<string, array{string}>
     */
    public static function randomHtmlProvider(): iterable
    {
        for ($i = 0; $i < 100; $i++) {
            yield "html_input_{$i}" => [self::randomHtmlString()];
        }
    }

    /**
     * Property 3: sanitize(sanitize(html)) === sanitize(html) — idempotency.
     *
     * For any HTML string submitted through a content write path, the persisted
     * value equals HtmlSanitizer::sanitize(input). Applying sanitize twice must
     * produce the same result as applying it once, confirming that already-sanitized
     * content is stable.
     *
     * HTMLPurifier may reorder attributes (e.g., rel/target on anchor tags) between
     * passes, so we normalize attribute order before comparing semantic equivalence.
     *
     * **Validates: Requirements 16.1, 16.2, 16.3**
     */
    #[Test]
    #[DataProvider('randomHtmlProvider')]
    public function sanitization_is_idempotent_for_any_html_input(string $html): void
    {
        $firstPass = $this->sanitizer->sanitize($html);
        $secondPass = $this->sanitizer->sanitize($firstPass);

        $this->assertSame(
            self::normalizeAttributeOrder($firstPass),
            self::normalizeAttributeOrder($secondPass),
            "Sanitization must be idempotent: sanitize(sanitize(html)) === sanitize(html).\n"
            .'Input: '.mb_substr($html, 0, 200)."\n"
            .'First pass:  '.mb_substr($firstPass, 0, 200)."\n"
            .'Second pass: '.mb_substr($secondPass, 0, 200)
        );
    }

    /**
     * Property 3: Unsafe elements are always removed after sanitization.
     *
     * For any HTML string containing unsafe tags or attributes, the sanitized
     * output must not contain those unsafe elements.
     *
     * **Validates: Requirements 16.1, 16.2, 16.3**
     */
    #[Test]
    #[DataProvider('randomHtmlProvider')]
    public function sanitized_output_never_contains_unsafe_elements(string $html): void
    {
        $result = $this->sanitizer->sanitize($html);

        // Verify no script tags remain
        $this->assertStringNotContainsString(
            '<script',
            strtolower($result),
            'Sanitized output must not contain <script> tags'
        );

        // Verify no iframe tags remain
        $this->assertStringNotContainsString(
            '<iframe',
            strtolower($result),
            'Sanitized output must not contain <iframe> tags'
        );

        // Verify no event handler attributes remain
        $this->assertDoesNotMatchRegularExpression(
            '/\bon\w+\s*=/i',
            $result,
            'Sanitized output must not contain event handler attributes (onclick, onerror, etc.)'
        );

        // Verify no javascript: URIs remain
        $this->assertStringNotContainsString(
            'javascript:',
            strtolower($result),
            'Sanitized output must not contain javascript: URIs'
        );
    }

    // ──────────────────────────────────────────────────────────────────────
    // Property 4 — Sanitization Preserves Valid Content
    // ──────────────────────────────────────────────────────────────────────

    /**
     * Data provider generating 100 valid HTML strings with Arabic RTL text.
     *
     * @return iterable<string, array{string}>
     */
    public static function validArabicHtmlProvider(): iterable
    {
        for ($i = 0; $i < 100; $i++) {
            yield "arabic_html_{$i}" => [self::randomValidArabicHtml()];
        }
    }

    /**
     * Property 4: Sanitization preserves all text content in valid HTML with Arabic RTL text.
     *
     * For any valid HTML composed of allowed tags containing Arabic RTL text,
     * HtmlSanitizer::sanitize(html) preserves all text content without corruption.
     *
     * **Validates: Requirements 16.4**
     */
    #[Test]
    #[DataProvider('validArabicHtmlProvider')]
    public function sanitization_preserves_arabic_text_content(string $html): void
    {
        $result = $this->sanitizer->sanitize($html);

        $originalText = self::extractTextContent($html);
        $sanitizedText = self::extractTextContent($result);

        $this->assertSame(
            $originalText,
            $sanitizedText,
            "Sanitization must preserve all Arabic text content.\n"
            .'Input HTML: '.mb_substr($html, 0, 300)."\n"
            ."Original text:  '{$originalText}'\n"
            ."Sanitized text: '{$sanitizedText}'"
        );
    }

    /**
     * Property 4: Sanitization preserves structural elements in valid HTML.
     *
     * For any valid HTML composed of allowed tags, the sanitized output must
     * retain the same structural tags (no allowed tags should be stripped).
     *
     * **Validates: Requirements 16.4**
     */
    #[Test]
    #[DataProvider('validArabicHtmlProvider')]
    public function sanitization_preserves_structural_elements(string $html): void
    {
        $result = $this->sanitizer->sanitize($html);

        // Extract all tag names from the input
        preg_match_all('/<([a-z][a-z0-9]*)\b/i', $html, $inputTags);
        $inputTagNames = array_map('strtolower', $inputTags[1]);

        // Extract all tag names from the output
        preg_match_all('/<([a-z][a-z0-9]*)\b/i', $result, $outputTags);
        $outputTagNames = array_map('strtolower', $outputTags[1]);

        // Every allowed tag in the input should appear in the output
        $inputCounts = array_count_values($inputTagNames);
        $outputCounts = array_count_values($outputTagNames);

        foreach ($inputCounts as $tag => $count) {
            if (in_array($tag, self::ALLOWED_TAGS, true)) {
                $outputCount = $outputCounts[$tag] ?? 0;

                $this->assertGreaterThanOrEqual(
                    $count,
                    $outputCount,
                    "Allowed tag <{$tag}> appeared {$count} time(s) in input but only {$outputCount} time(s) in output.\n"
                    .'Input:  '.mb_substr($html, 0, 300)."\n"
                    .'Output: '.mb_substr($result, 0, 300)
                );
            }
        }
    }

    /**
     * Property 4: Sanitization is idempotent for valid Arabic HTML.
     *
     * Valid HTML composed of allowed tags should pass through sanitization
     * unchanged (or at least stably after the first pass).
     *
     * **Validates: Requirements 16.4**
     */
    #[Test]
    #[DataProvider('validArabicHtmlProvider')]
    public function valid_arabic_html_is_stable_after_sanitization(string $html): void
    {
        $firstPass = $this->sanitizer->sanitize($html);
        $secondPass = $this->sanitizer->sanitize($firstPass);

        $this->assertSame(
            $firstPass,
            $secondPass,
            "Valid Arabic HTML must be stable after sanitization (idempotent).\n"
            .'Input: '.mb_substr($html, 0, 300)."\n"
            .'First pass:  '.mb_substr($firstPass, 0, 300)."\n"
            .'Second pass: '.mb_substr($secondPass, 0, 300)
        );
    }
}
