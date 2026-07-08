<?php

declare(strict_types=1);

namespace Tests\Feature\HomepageBlade;

use Tests\Support\HomepageBladeTestHelpers;
use Tests\TestCase;

class FooterFallbackTest extends TestCase
{
    use HomepageBladeTestHelpers;

    public function test_shared_footer_renders_cms_navigation_identity(): void
    {
        $nav = self::makeNavigation('en', [
            'footerSettings' => self::makeFooterSettings('en', [
                'brandTitle' => 'CMS Brand Title',
            ]),
        ]);

        $data = self::makeLayoutData([
            'navigation' => $nav,
            'homepageFooterSection' => null,
        ]);

        $html = view('layouts.public', $data)->render();

        $this->assertStringContainsString('CMS Brand Title', $html);
        $this->assertStringContainsString('Excellence in education', $html);
        $this->assertStringNotContainsString('SYRIAN PRIVATE UNIVERSITY', $html);
    }
}
