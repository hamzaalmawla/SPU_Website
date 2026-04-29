<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Support\HtmlSanitizer;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for HtmlSanitizer.
 *
 * Validates Requirements 1.1–1.6 from the audit remediation plan.
 */
final class HtmlSanitizerTest extends TestCase
{
    private HtmlSanitizer $sanitizer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->sanitizer = new HtmlSanitizer();
    }

    // --- Requirement 1.6: Null/empty input ---

    #[Test]
    public function it_returns_empty_string_for_null_input(): void
    {
        $this->assertSame('', $this->sanitizer->sanitize(null));
    }

    #[Test]
    public function it_returns_empty_string_for_empty_string_input(): void
    {
        $this->assertSame('', $this->sanitizer->sanitize(''));
    }

    // --- Requirement 1.1: Script tag stripping ---

    #[Test]
    public function it_strips_script_tags(): void
    {
        $input = '<p>Hello</p><script>alert("xss")</script><p>World</p>';
        $result = $this->sanitizer->sanitize($input);

        $this->assertStringNotContainsString('<script', $result);
        $this->assertStringNotContainsString('alert', $result);
        $this->assertStringContainsString('Hello', $result);
        $this->assertStringContainsString('World', $result);
    }

    // --- Requirement 1.1: Event handler attribute removal ---

    #[Test]
    public function it_strips_onclick_event_handler(): void
    {
        $input = '<p onclick="alert(1)">Text</p>';
        $result = $this->sanitizer->sanitize($input);

        $this->assertStringNotContainsString('onclick', $result);
        $this->assertStringContainsString('Text', $result);
    }

    #[Test]
    public function it_strips_onerror_event_handler(): void
    {
        $input = '<img onerror="alert(1)" src="x">';
        $result = $this->sanitizer->sanitize($input);

        $this->assertStringNotContainsString('onerror', $result);
    }

    #[Test]
    public function it_strips_onload_event_handler(): void
    {
        $input = '<div onload="alert(1)">Content</div>';
        $result = $this->sanitizer->sanitize($input);

        $this->assertStringNotContainsString('onload', $result);
        $this->assertStringContainsString('Content', $result);
    }

    // --- Requirement 1.3: href scheme restriction ---

    #[Test]
    public function it_allows_http_href(): void
    {
        $input = '<a href="http://example.com">Link</a>';
        $result = $this->sanitizer->sanitize($input);

        $this->assertStringContainsString('href', $result);
        $this->assertStringContainsString('http://example.com', $result);
    }

    #[Test]
    public function it_allows_https_href(): void
    {
        $input = '<a href="https://example.com">Link</a>';
        $result = $this->sanitizer->sanitize($input);

        $this->assertStringContainsString('href', $result);
        $this->assertStringContainsString('https://example.com', $result);
    }

    #[Test]
    public function it_allows_mailto_href(): void
    {
        $input = '<a href="mailto:test@example.com">Email</a>';
        $result = $this->sanitizer->sanitize($input);

        $this->assertStringContainsString('mailto:', $result);
    }

    #[Test]
    public function it_strips_javascript_href(): void
    {
        $input = '<a href="javascript:alert(1)">Click</a>';
        $result = $this->sanitizer->sanitize($input);

        $this->assertStringNotContainsString('javascript:', $result);
        $this->assertStringContainsString('Click', $result);
    }

    // --- Requirement 1.3: img src restriction ---

    #[Test]
    public function it_allows_https_img_src(): void
    {
        $input = '<img src="https://example.com/image.jpg" alt="test">';
        $result = $this->sanitizer->sanitize($input);

        $this->assertStringContainsString('https://example.com/image.jpg', $result);
    }

    #[Test]
    public function it_allows_relative_img_src(): void
    {
        $input = '<img src="/images/photo.jpg" alt="test">';
        $result = $this->sanitizer->sanitize($input);

        $this->assertStringContainsString('/images/photo.jpg', $result);
    }

    // --- Requirement 1.4: Disallowed attribute removal preserving text ---

    #[Test]
    public function it_removes_disallowed_attributes_but_preserves_text(): void
    {
        $input = '<p data-custom="value" class="foo">Preserved text</p>';
        $result = $this->sanitizer->sanitize($input);

        $this->assertStringNotContainsString('data-custom', $result);
        $this->assertStringNotContainsString('class=', $result);
        $this->assertStringContainsString('Preserved text', $result);
        $this->assertStringContainsString('<p', $result);
    }

    // --- Requirement 1.5: CSS expression/behavior stripping ---

    #[Test]
    public function it_strips_css_expression(): void
    {
        $input = '<div style="width: expression(alert(1))">Content</div>';
        $result = $this->sanitizer->sanitize($input);

        $this->assertStringNotContainsString('expression', $result);
        $this->assertStringContainsString('Content', $result);
    }

    #[Test]
    public function it_strips_css_javascript_url(): void
    {
        $input = '<div style="background: url(javascript:alert(1))">Content</div>';
        $result = $this->sanitizer->sanitize($input);

        $this->assertStringNotContainsString('javascript:', $result);
        $this->assertStringContainsString('Content', $result);
    }

    #[Test]
    public function it_strips_css_behavior_property(): void
    {
        $input = '<div style="behavior: url(xss.htc)">Content</div>';
        $result = $this->sanitizer->sanitize($input);

        $this->assertStringNotContainsString('behavior', $result);
        $this->assertStringContainsString('Content', $result);
    }

    // --- Requirement 1.3: Allowed tags preserved ---

    #[Test]
    #[DataProvider('allowedTagsProvider')]
    public function it_preserves_allowed_tags(string $tag, string $input, string $expectedContent): void
    {
        $result = $this->sanitizer->sanitize($input);
        $this->assertStringContainsString($expectedContent, $result);
    }

    public static function allowedTagsProvider(): array
    {
        return [
            'p' => ['p', '<p>Paragraph</p>', 'Paragraph'],
            'br' => ['br', 'Line<br>break', '<br'],
            'strong' => ['strong', '<strong>Bold</strong>', '<strong>Bold</strong>'],
            'em' => ['em', '<em>Italic</em>', '<em>Italic</em>'],
            'ul' => ['ul', '<ul><li>Item</li></ul>', '<ul>'],
            'ol' => ['ol', '<ol><li>Item</li></ol>', '<ol>'],
            'li' => ['li', '<ul><li>Item</li></ul>', '<li>Item</li>'],
            'h1' => ['h1', '<h1>Heading</h1>', '<h1>Heading</h1>'],
            'h2' => ['h2', '<h2>Heading</h2>', '<h2>Heading</h2>'],
            'h3' => ['h3', '<h3>Heading</h3>', '<h3>Heading</h3>'],
            'h4' => ['h4', '<h4>Heading</h4>', '<h4>Heading</h4>'],
            'h5' => ['h5', '<h5>Heading</h5>', '<h5>Heading</h5>'],
            'h6' => ['h6', '<h6>Heading</h6>', '<h6>Heading</h6>'],
            'blockquote' => ['blockquote', '<blockquote>Quote</blockquote>', '<blockquote>Quote</blockquote>'],
            'table' => ['table', '<table><tr><td>Cell</td></tr></table>', '<table>'],
            'thead' => ['thead', '<table><thead><tr><th>H</th></tr></thead></table>', '<thead>'],
            'tbody' => ['tbody', '<table><tbody><tr><td>D</td></tr></tbody></table>', '<tbody>'],
            'tr' => ['tr', '<table><tr><td>Cell</td></tr></table>', '<tr>'],
            'th' => ['th', '<table><tr><th>Header</th></tr></table>', '<th>Header</th>'],
            'td' => ['td', '<table><tr><td>Data</td></tr></table>', '<td>Data</td>'],
            'span' => ['span', '<span>Inline</span>', '<span>Inline</span>'],
            'div' => ['div', '<div>Block</div>', '<div>Block</div>'],
            'figure' => ['figure', '<figure><img src="https://example.com/img.jpg" alt="x"></figure>', '<figure>'],
            'figcaption' => ['figcaption', '<figure><figcaption>Caption</figcaption></figure>', '<figcaption>Caption</figcaption>'],
        ];
    }

    // --- Disallowed tags stripped ---

    #[Test]
    public function it_strips_iframe_tags(): void
    {
        $input = '<iframe src="https://evil.com"></iframe>';
        $result = $this->sanitizer->sanitize($input);

        $this->assertStringNotContainsString('<iframe', $result);
    }

    #[Test]
    public function it_strips_form_tags(): void
    {
        $input = '<form action="/steal"><input type="text"></form>';
        $result = $this->sanitizer->sanitize($input);

        $this->assertStringNotContainsString('<form', $result);
        $this->assertStringNotContainsString('<input', $result);
    }

    // --- Nested encoding XSS vectors ---

    #[Test]
    public function it_handles_nested_encoding_xss(): void
    {
        $input = '<img src=x onerror=&#x61;&#x6C;&#x65;&#x72;&#x74;(1)>';
        $result = $this->sanitizer->sanitize($input);

        $this->assertStringNotContainsString('onerror', $result);
        $this->assertStringNotContainsString('alert', $result);
    }

    // --- isClean method ---

    #[Test]
    public function is_clean_returns_true_for_safe_html(): void
    {
        $safeHtml = '<p>Hello <strong>world</strong></p>';
        $this->assertTrue($this->sanitizer->isClean($safeHtml));
    }

    #[Test]
    public function is_clean_returns_false_for_unsafe_html(): void
    {
        $unsafeHtml = '<p>Hello</p><script>alert(1)</script>';
        $this->assertFalse($this->sanitizer->isClean($unsafeHtml));
    }

    // --- Idempotence ---

    #[Test]
    public function sanitize_is_idempotent(): void
    {
        $input = '<p>Hello <strong>world</strong></p><script>alert(1)</script>';
        $first = $this->sanitizer->sanitize($input);
        $second = $this->sanitizer->sanitize($first);

        $this->assertSame($first, $second);
    }
}
