<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Support\BusinessFacultyName;
use PHPUnit\Framework\TestCase;

final class BusinessFacultyNameTest extends TestCase
{
    public function test_phrases_that_name_the_faculty_are_renamed_in_both_languages(): void
    {
        $this->assertSame('عميد كلية الأعمال', BusinessFacultyName::text('عميد كلية إدارة الأعمال'));
        $this->assertSame('أرحب بكم في كلية الأعمال.', BusinessFacultyName::text('أرحب بكم في كلية العلوم الإدارية.'));
        $this->assertSame('برنامج الامتحان النهائي لكلية الأعمال', BusinessFacultyName::text('برنامج الامتحان النهائي لكلية إدارة الأعمال'));
        $this->assertSame('كلية الأعمال', BusinessFacultyName::text('كلية  الأعمال'));
        $this->assertSame('The Business Faculty\'s final examination schedules.', BusinessFacultyName::text('The Faculty of Business Administration\'s final examination schedules.'));
        $this->assertSame('Dean of the Business Faculty', BusinessFacultyName::text('Dean of the Faculty of Administrative Sciences'));
        $this->assertSame('Dean of the Business Faculty', BusinessFacultyName::text('Dean of Business Administration'));
        $this->assertSame('The Business Faculty is an incubator', BusinessFacultyName::text('The Faculty of Business is an incubator'));
        $this->assertSame('BUSINESS FACULTY', BusinessFacultyName::text('FACULTY OF BUSINESS ADMINISTRATION'));
        $this->assertSame('Browse alumni records for the Business Faculty.', BusinessFacultyName::text('Browse alumni records for Business Administration.'));
        $this->assertSame('Business Faculty Study Plan | SPU', BusinessFacultyName::text('Business Administration Study Plan | SPU'));
    }

    public function test_the_subject_and_degrees_keep_their_name(): void
    {
        foreach ([
            'ماجستير في إدارة الأعمال (MBA)',
            'Master of Business Administration (MBA)',
            'قسم إدارة الأعمال',
            'Department of Business Administration',
            'مجالات العلوم الإدارية المختلفة',
            'we offer programs in Business Administration, Human Resources, Marketing',
            'إدارة الأعمال',
            'Business Administration',
        ] as $text) {
            $this->assertSame($text, BusinessFacultyName::text($text));
        }
    }

    public function test_a_faculty_name_field_takes_the_new_name(): void
    {
        $this->assertSame('كلية الأعمال', BusinessFacultyName::name('إدارة الأعمال'));
        $this->assertSame('كلية الأعمال', BusinessFacultyName::name('كلية إدارة الأعمال'));
        $this->assertSame('Business Faculty', BusinessFacultyName::name('Business Administration'));
        $this->assertSame('Business Faculty', BusinessFacultyName::name('Faculty of Business Administration'));
        $this->assertSame('Business Faculty', BusinessFacultyName::name('Administrative Sciences'));
    }

    public function test_a_payload_renames_faculty_fields_but_not_programs_courses_or_themes(): void
    {
        $payload = [
            'title' => 'Business Administration Study Plan | SPU',
            'faculty' => ['name' => 'Business Administration', 'title' => 'Faculty of Business Administration'],
            'plan' => [
                'facultyEn' => 'Business Administration',
                'facultyAr' => 'إدارة الأعمال',
                'departments' => [[
                    'id' => 'management',
                    'nameAr' => 'إدارة الأعمال',
                    'nameEn' => 'Business Administration',
                    'name' => 'إدارة الأعمال',
                    'courses' => [[
                        'title' => 'Business Administration',
                        'titleAr' => 'إدارة الأعمال الدولية',
                        'description' => 'مقرر موجه لطالب كلية إدارة الأعمال.',
                        'summary' => 'Principles of Business Administration.',
                    ]],
                ]],
            ],
            'filters' => [
                ['value' => 'business-administration', 'label' => 'إدارة الأعمال'],
                ['value' => 'petroleum', 'label' => 'Business Administration'],
            ],
            'items' => [['title' => 'Business Administration', 'url' => '/en/faculties/business-administration']],
            'themes' => [['slug' => 'business-administration', 'name' => 'Business Administration', 'label' => 'Business Administration']],
            'rows' => [['faculty' => 'إدارة الأعمال', 'creditHours' => 134]],
        ];

        $renamed = BusinessFacultyName::payload($payload);

        $this->assertSame('Business Faculty Study Plan | SPU', $renamed['title']);
        $this->assertSame(['name' => 'Business Faculty', 'title' => 'Business Faculty'], $renamed['faculty']);
        $this->assertSame('Business Faculty', $renamed['plan']['facultyEn']);
        $this->assertSame('كلية الأعمال', $renamed['plan']['facultyAr']);
        $this->assertSame('كلية الأعمال', $renamed['filters'][0]['label']);
        $this->assertSame('Business Administration', $renamed['filters'][1]['label'], 'A label of another item keeps its text.');
        $this->assertSame('Business Faculty', $renamed['items'][0]['title']);
        $this->assertSame('كلية الأعمال', $renamed['rows'][0]['faculty']);

        $program = $renamed['plan']['departments'][0];
        $this->assertSame(['إدارة الأعمال', 'Business Administration', 'إدارة الأعمال'], [$program['nameAr'], $program['nameEn'], $program['name']]);
        $this->assertSame('Business Administration', $program['courses'][0]['title']);
        $this->assertSame('إدارة الأعمال الدولية', $program['courses'][0]['titleAr']);
        $this->assertSame('مقرر موجه لطالب كلية الأعمال.', $program['courses'][0]['description']);
        $this->assertSame('Principles of Business Administration.', $program['courses'][0]['summary']);
        $this->assertSame($payload['themes'], $renamed['themes']);
    }
}
