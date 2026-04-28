<?php

declare(strict_types=1);

namespace Tests\Feature\HomepageBlade;

use Tests\Support\HomepageBladeTestHelpers;
use Tests\TestCase;

class EventCardPropertyTest extends TestCase
{
    use HomepageBladeTestHelpers;

    public function test_events_section_injects_events_data_to_window(): void
    {
        $events = [
            self::makeEvent(['title' => 'Event Alpha']),
            self::makeEvent(['title' => 'Event Beta']),
        ];

        $section = self::makeSection('events_activities', [
            'title' => 'Events Title',
            'events' => $events,
            'content' => ['calendarHighlights' => []],
        ]);

        $html = view('public.partials.homepage-section', [
            'section' => $section,
            'locale' => 'en',
        ])->render();

        $this->assertStringContainsString('window.spuEventsData', $html);
        $this->assertStringContainsString('Events Title', $html);
        $this->assertStringContainsString('calendarApp()', $html);
    }

    public function test_calendar_highlights_render_when_present(): void
    {
        $section = self::makeSection('events_activities', [
            'title' => 'Events',
            'events' => [],
            'content' => [
                'calendarHighlights' => [
                    ['label' => 'Exam Period', 'date' => 'March 20'],
                    ['label' => 'Holiday', 'date' => 'April 1'],
                ],
            ],
        ]);

        $html = view('public.partials.homepage-section', [
            'section' => $section,
            'locale' => 'en',
        ])->render();

        $this->assertStringContainsString('Exam Period', $html);
        $this->assertStringContainsString('March 20', $html);
        $this->assertStringContainsString('Holiday', $html);
    }

    public function test_empty_calendar_highlights_render_no_entries(): void
    {
        $section = self::makeSection('events_activities', [
            'title' => 'Events',
            'events' => [],
            'content' => ['calendarHighlights' => []],
        ]);

        $html = view('public.partials.homepage-section', [
            'section' => $section,
            'locale' => 'en',
        ])->render();

        $this->assertStringContainsString('Events', $html);
        $this->assertStringNotContainsString('Exam Period', $html);
    }
}
