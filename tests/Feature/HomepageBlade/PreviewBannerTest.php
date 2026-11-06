<?php

declare(strict_types=1);

namespace Tests\Feature\HomepageBlade;

use Tests\Support\HomepageBladeTestHelpers;
use Tests\TestCase;

class PreviewBannerTest extends TestCase
{
    use HomepageBladeTestHelpers;

    public function test_preview_banner_shown_when_is_preview_true(): void
    {
        $data = self::makeLayoutData(['isPreview' => true]);
        $html = view('layouts.public', $data)->render();

        // The translation key resolves to Arabic by default; check for the banner wrapper class instead
        $this->assertStringContainsString('bg-amber-400/10', $html);
    }

    public function test_preview_banner_hidden_when_is_preview_false(): void
    {
        $data = self::makeLayoutData(['isPreview' => false]);
        $html = view('layouts.public', $data)->render();

        $this->assertStringNotContainsString('bg-amber-400/10', $html);
    }
}
