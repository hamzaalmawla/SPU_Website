<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Support\StudentLists\DecisionListParser;
use App\Support\StudentLists\DepartmentMatcher;
use App\Support\StudentLists\DocxTableReader;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Tests\Support\DecisionDocxFactory;

final class StudentListParserTest extends TestCase
{
    private string $path;

    protected function setUp(): void
    {
        parent::setUp();
        $this->path = tempnam(sys_get_temp_dir(), 'decision').'.docx';
    }

    protected function tearDown(): void
    {
        @unlink($this->path);
        parent::tearDown();
    }

    public function test_it_reads_a_graduates_decision_with_a_two_row_header(): void
    {
        DecisionDocxFactory::graduates($this->path, $this->graduates());

        $parsed = DecisionListParser::graduates(DocxTableReader::read($this->path));

        $this->assertSame(['decisionNumber' => '5/14', 'academicYear' => '2025/2026', 'semester' => 'second'], $parsed['meta']);
        $this->assertCount(3, $parsed['rows']);
        $this->assertSame([
            'line' => 1,
            'studentIdentifier' => '4200001',
            'name' => 'سامر خالد الحلبي',
            'specialization' => 'هندسة أمن النظم والشبكات الحاسوبية',
            'gpaPoints' => 3.55,
            'gpaPercent' => 91.0,
            'grade' => 'excellent',
            'semester' => 'second',
            'academicYear' => '2025/2026',
            'issues' => [],
        ], $parsed['rows'][0]);
        $this->assertSame(['very_good', 'pass'], [$parsed['rows'][1]['grade'], $parsed['rows'][2]['grade']]);
        // No program prefix: the text is kept as it is.
        $this->assertSame('هندسة الاتصالات و الشبكات', $parsed['rows'][2]['specialization']);
    }

    public function test_columns_are_found_by_header_not_position(): void
    {
        DecisionDocxFactory::graduates($this->path, $this->graduates(), ['line', 'name', 'id', 'grade', 'gpa', 'specialization', 'faculty', 'semester']);

        $row = DecisionListParser::graduates(DocxTableReader::read($this->path))['rows'][0];

        $this->assertSame('4200001', $row['studentIdentifier']);
        $this->assertSame('سامر خالد الحلبي', $row['name']);
        $this->assertSame(91.0, $row['gpaPercent']);
        $this->assertSame('excellent', $row['grade']);
    }

    public function test_unreadable_rows_carry_issues_instead_of_disappearing(): void
    {
        $students = $this->graduates();
        $students[1]['id'] = 'غير متوفر';
        $students[2]['percent'] = '120';
        $students[2]['grade'] = 'ممتاز جداً';
        DecisionDocxFactory::graduates($this->path, $students);

        $rows = DecisionListParser::graduates(DocxTableReader::read($this->path))['rows'];

        $this->assertSame([], $rows[0]['issues']);
        $this->assertSame(['missing_id'], $rows[1]['issues']);
        $this->assertSame(['bad_gpa', 'bad_grade'], $rows[2]['issues']);
    }

    public function test_it_reads_top_student_blocks_and_skips_spacers(): void
    {
        DecisionDocxFactory::topStudents($this->path, [
            'السنة الأولى' => [
                ['id' => '4250001', 'name' => 'ليلى سامي العلي', 'gpa' => '95.8'],
                ['id' => '4250002', 'name' => 'مازن فادي الخطيب', 'gpa' => '95.2'],
            ],
            'السنة الخامسة / هندسة البرمجيات ونظم المعلومات' => [
                ['id' => '٤٢١٠٠٠٣', 'name' => 'رنا عمر حداد', 'gpa' => '٩٠٫٨'],
            ],
        ]);

        $parsed = DecisionListParser::topStudents(DocxTableReader::read($this->path));

        $this->assertSame(['decisionNumber' => '12/14', 'academicYear' => '2025/2026', 'semester' => 'second'], $parsed['meta']);
        $this->assertCount(3, $parsed['rows']);
        $this->assertSame([1, null, 1, 95.8], [$parsed['rows'][0]['studyYear'], $parsed['rows'][0]['specialization'], $parsed['rows'][0]['rank'], $parsed['rows'][0]['gpa']]);
        $this->assertSame(2, $parsed['rows'][1]['rank']);
        // Arabic-Indic digits and decimal separator.
        $this->assertSame(['4210003', 5, 'هندسة البرمجيات ونظم المعلومات', 90.8], [
            $parsed['rows'][2]['studentIdentifier'], $parsed['rows'][2]['studyYear'], $parsed['rows'][2]['specialization'], $parsed['rows'][2]['gpa'],
        ]);
    }

    public function test_it_reads_the_first_semester_and_academic_years(): void
    {
        $meta = DecisionListParser::meta(['قائمة الأوائل للفصل الأول من العام الدراسي ٢٠٢٦/٢٠٢٧']);

        $this->assertSame('first', $meta['semester']);
        $this->assertSame('2026/2027', $meta['academicYear']);
        $this->assertNull(DecisionListParser::academicYear('قرار رقم 194 تاريخ 22/6/2025'));
    }

    /** @return iterable<string, array{string, ?int, bool}> */
    public static function specializations(): iterable
    {
        yield 'exact words' => ['هندسة البرمجيات ونظم المعلومات', 547, true];
        yield 'صنعي is اصطناعي' => ['هندسة الذكاء الصنعي وعلوم البيانات', 548, true];
        yield 'short name' => ['هندسة الذكاء الصنعي', 548, true];
        yield 'extra word' => ['هندسة الاتصالات و الشبكات', 551, true];
        yield 'joined و' => ['هندسة التحكم والروبوت', 550, true];
        yield 'weak match is suggested, not trusted' => ['هندسة أمن النظم والشبكات الحاسوبية', 549, false];
        yield 'no shared word' => ['هندسة الحاسوب و التحكم', null, false];
    }

    #[DataProvider('specializations')]
    public function test_specializations_match_departments(string $specialization, ?int $department, bool $confident): void
    {
        $departments = [
            547 => 'قسم هندسة البرمجيات ونظم المعلومات الذكية',
            548 => 'قسم هندسة الذكاء الاصطناعي وعلوم البيانات',
            549 => 'قسم هندسة نظم أمن المعلومات الذكية',
            550 => 'قسم هندسة الروبوت والنظم الذكية',
            551 => 'قسم هندسة الاتصالات الذكية',
        ];

        $match = DepartmentMatcher::match($specialization, $departments);

        $this->assertSame($department, $match['departmentId']);
        $this->assertSame($confident, $match['confident']);
    }

    public function test_the_reader_refuses_files_that_are_not_word_documents(): void
    {
        file_put_contents($this->path, 'not a zip');
        $this->assertReaderRefuses();

        DecisionDocxFactory::zip($this->path, '<?xml version="1.0"?><!DOCTYPE d [<!ENTITY x SYSTEM "file:///etc/passwd">]><d>&x;</d>');
        $this->assertReaderRefuses();
    }

    private function assertReaderRefuses(): void
    {
        try {
            DocxTableReader::read($this->path);
            $this->fail('The reader accepted an invalid file.');
        } catch (InvalidArgumentException $exception) {
            $this->assertSame('student_lists.errors.not_docx', $exception->getMessage());
        }
    }

    /** @return list<array{id: string, name: string, specialization: string, points: string, percent: string, grade: string}> */
    private function graduates(): array
    {
        return [
            ['id' => '4200001', 'name' => 'سامر خالد الحلبي', 'specialization' => 'الهندسة المعلوماتية/هندسة أمن النظم والشبكات الحاسوبية', 'points' => '3.55', 'percent' => '91', 'grade' => 'امتياز'],
            ['id' => '4200002', 'name' => 'هبة ماهر يوسف', 'specialization' => 'الهندسة المعلوماتية/هندسة البرمجيات ونظم المعلومات', 'points' => '3.21', 'percent' => '84.2', 'grade' => 'جيد جداً'],
            ['id' => '4200003', 'name' => 'طارق نزار سليم', 'specialization' => 'هندسة الاتصالات و الشبكات', 'points' => '2.07', 'percent' => '61.4', 'grade' => 'مقبول'],
        ];
    }
}
