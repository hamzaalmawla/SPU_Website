<?php

declare(strict_types=1);

namespace Tests\Feature\HomepageBlade;

use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\HomepageBladeTestHelpers;
use Tests\TestCase;

class HtmlRootAttributesPropertyTest extends TestCase
{
    use HomepageBladeTestHelpers;

    #[DataProvider('localeDirectionPairs')]
    public function test_html_root_attributes_match_locale_and_direction(string $locale, string $direction): void
    {
        $data = self::makeLayoutData(['locale' => $locale, 'direction' => $direction]);
        $html = view('layouts.public', $data)->render();

        $this->assertMatchesRegularExpression(
            '/<html\s[^>]*lang="'.preg_quote($locale, '/').'"/',
            $html,
            "Expected lang=\"{$locale}\" on <html>"
        );
        $this->assertMatchesRegularExpression(
            '/<html\s[^>]*dir="'.preg_quote($direction, '/').'"/',
            $html,
            "Expected dir=\"{$direction}\" on <html>"
        );
    }

    public static function localeDirectionPairs(): array
    {
        return [
            'arabic RTL' => ['ar', 'rtl'],
            'english LTR' => ['en', 'ltr'],
            'arabic LTR (edge)' => ['ar', 'ltr'],
            'english RTL (edge)' => ['en', 'rtl'],
            'fr LTR (hypothetical)' => ['fr', 'ltr'],
        ];
    }
}
