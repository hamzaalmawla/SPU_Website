<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Support\ExamScheduleBoard;
use App\Support\ExamScheduleRows;
use PHPUnit\Framework\TestCase;

final class ExamScheduleRowsTest extends TestCase
{
    public function test_a_header_line_sets_the_column_order(): void
    {
        $rows = ExamScheduleRows::fromPastedText("التاريخ\tالمقرر\tالسنة\tالقاعة\tالساعة\n14/6/2026\tرياضيات 1\tالسنة الأولى\tA1\t09:00 - 11:00\n\t\t\t\t\n١٥/٦/٢٠٢٦\tفيزياء\tالسنة الثانية\tB2\t");

        $this->assertSame([
            ['day' => '', 'date' => '2026-06-14', 'time' => '09:00 - 11:00', 'course' => 'رياضيات 1', 'year' => 'السنة الأولى', 'room' => 'A1'],
            ['day' => '', 'date' => '2026-06-15', 'time' => '', 'course' => 'فيزياء', 'year' => 'السنة الثانية', 'room' => 'B2'],
        ], $rows);
    }

    public function test_without_a_header_the_order_is_day_date_time_course_year_hall(): void
    {
        $rows = ExamScheduleRows::fromPastedText("Sunday, 2026-06-14, 10:00, Anatomy, First Year, A1\nMonday,15 June,,Pathology,,\n,,,,,");

        $this->assertCount(2, $rows);
        $this->assertSame('Anatomy', $rows[0]['course']);
        $this->assertSame('2026-06-14', $rows[0]['date']);
        // A date that can't be read is kept as written.
        $this->assertSame('15 June', $rows[1]['date']);
    }

    public function test_a_picked_date_shows_its_weekday_and_reads_day_month_year(): void
    {
        $board = ExamScheduleBoard::build([['date' => '2026-06-14', 'course' => 'Anatomy']], 'exam', 'ar');

        $this->assertSame('الأحد', $board['days'][0]['day']);
        $this->assertSame('14/06/2026', $board['days'][0]['date']);
        $this->assertSame('2026-06-14', $board['days'][0]['iso']);
        $this->assertSame('Sunday', ExamScheduleBoard::weekday('2026-06-14', 'en'));
    }
}
