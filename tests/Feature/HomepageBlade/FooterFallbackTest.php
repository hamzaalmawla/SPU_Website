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

    public function test_shared_footer_uses_published_homepage_footer_payload(): void
    {
        $nav = self::makeNavigation('en', [
            'footerSettings' => self::makeFooterSettings('en', [
                'brandTitle' => 'Settings Brand Title',
                'copyrightText' => 'Settings Copyright',
            ]),
        ]);
        $homepageFooter = self::makeSection('footer', [
            'title' => 'Managed Footer Title',
            'content' => [
                'brandBlock' => ['title' => 'Managed Footer Brand'],
                'copyrightText' => 'Managed Footer Copyright',
                'legalLinks' => [['label' => 'Privacy', 'url' => '/en/privacy']],
            ],
        ]);

        $html = view('layouts.public', self::makeLayoutData([
            'navigation' => $nav,
            'homepageFooterSection' => $homepageFooter,
        ]))->render();

        $this->assertStringContainsString('Managed Footer Brand', $html);
        $this->assertStringContainsString('Managed Footer Copyright', $html);
        $this->assertStringContainsString('/en/privacy', $html);
        $this->assertStringNotContainsString('Settings Brand Title', $html);
    }
}
