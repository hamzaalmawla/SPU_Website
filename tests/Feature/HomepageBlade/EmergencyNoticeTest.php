<?php

declare(strict_types=1);

namespace Tests\Feature\HomepageBlade;

use Tests\Support\HomepageBladeTestHelpers;
use Tests\TestCase;

class EmergencyNoticeTest extends TestCase
{
    use HomepageBladeTestHelpers;

    public function test_emergency_notice_shown_when_enabled(): void
    {
        $nav = self::makeNavigation('en', [
            'emergencyNotice' => self::makeEmergencyNotice(true),
        ]);

        $data = self::makeLayoutData(['navigation' => $nav]);
        $html = view('layouts.public', $data)->render();

        $this->assertStringContainsString('Emergency Title', $html);
        $this->assertStringContainsString('Emergency message body', $html);
    }

    public function test_emergency_notice_hidden_when_disabled(): void
    {
        $nav = self::makeNavigation('en', [
            'emergencyNotice' => self::makeEmergencyNotice(false),
        ]);

        $data = self::makeLayoutData(['navigation' => $nav]);
        $html = view('layouts.public', $data)->render();

        $this->assertStringNotContainsString('Emergency Title', $html);
    }
}
