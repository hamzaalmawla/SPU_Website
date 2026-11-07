<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Contracts\Faculty\FacultyStudyPlanGuideServiceInterface;
use Tests\TestCase;

final class FacultyStudyPlanGuideServiceTest extends TestCase
{
    public function test_a_plan_saved_before_the_guide_gets_the_default_guide_and_type_explanations(): void
    {
        $service = app(FacultyStudyPlanGuideServiceInterface::class);
        $payload = ['legend' => [['id' => 'university', 'label' => 'University Requirement'], ['id' => 'custom', 'label' => 'Other']]];

        $en = $service->withDefaults($payload, 'en');
        $ar = $service->withDefaults($payload, 'ar');

        $this->assertSame('How to read this study plan', $en['guide']['title']);
        $this->assertSame('كيف تقرأ الخطة الدراسية', $ar['guide']['title']);
        $this->assertSame(['card', 'columns', 'lines', 'highlight', 'electives', 'views'], array_column($en['guide']['steps'], 'id'));
        $this->assertSame(array_column($en['guide']['steps'], 'id'), array_column($ar['guide']['steps'], 'id'));
        $this->assertSame($service->illustratedStepIds(), array_column($en['guide']['steps'], 'id'));
        $this->assertSame('Taken by every SPU student, whatever their faculty.', $en['legend'][0]['description']);
        $this->assertSame('يدرسه جميع طلاب الجامعة مهما كانت كليتهم.', $ar['legend'][0]['description']);
        $this->assertSame('', $en['legend'][1]['description']);
    }

    public function test_what_an_editor_saved_is_kept_even_when_empty(): void
    {
        $service = app(FacultyStudyPlanGuideServiceInterface::class);
        $payload = [
            'guide' => ['title' => 'Reading the plan', 'legendHint' => '', 'steps' => [
                ['id' => 'lines', 'title' => 'Arrows', 'text' => 'Pass the first one first.'],
                ['id' => '', 'title' => '', 'text' => ''],
            ]],
            'legend' => [['id' => 'faculty', 'label' => 'Faculty', 'description' => '']],
        ];

        $result = $service->withDefaults($payload, 'en');

        $this->assertSame('Reading the plan', $result['guide']['title']);
        $this->assertSame('', $result['guide']['legendHint']);
        $this->assertSame([['id' => 'lines', 'title' => 'Arrows', 'text' => 'Pass the first one first.']], $result['guide']['steps']);
        $this->assertSame('', $result['legend'][0]['description']);

        // No steps at all: no guide.
        $this->assertSame([], $service->withDefaults(['guide' => ['steps' => []]], 'ar')['guide']['steps']);
    }
}
