<?php

declare(strict_types=1);

namespace Tests\Feature\HomepageBlade;

use Tests\Support\HomepageBladeTestHelpers;
use Tests\TestCase;

class FooterFallbackTest extends TestCase
{
    use HomepageBladeTestHelpers;

    public function test_fallback_footer_renders_brand_title_from_footer_settings(): void
    {
        $nav = self::makeNavigation('en', [
            'footerSettings' => self::makeFooterSettings('en', [
                'brandTitle' => 'Fallback Brand Title',
            ]),
        ]);

        $data = self::makeLayoutData([
            'navigation' => $nav,
            'homepageFooterSection' => null,
        ]);

        $html = view('layouts.public', $data)->render();

        $this->assertStringContainsString('Fallback Brand Title', $html);
    }
}
