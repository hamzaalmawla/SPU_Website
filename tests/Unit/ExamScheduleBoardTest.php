<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Support\ExamScheduleBoard;
use PHPUnit\Framework\TestCase;

final class ExamScheduleBoardTest extends TestCase
{
    public function test_exams_group_by_day_in_date_order_and_link_each_year_forward(): void
    {
        $board = ExamScheduleBoard::build([
            ['day' => 'Tuesday', 'date' => '12/01/2027', 'time' => '10:00', 'course' => 'Anatomy II', 'year' => 'Second year', 'room' => 'A1'],
            ['day' => 'Sunday', 'date' => '2027-01-10', 'time' => '10:00', 'course' => 'Anatomy I', 'year' => 'First year', 'room' => 'A1'],
            ['day' => 'Sunday', 'date' => '2027-01-10', 'time' => '12:00', 'course' => 'Physiology I', 'year' => 'Second year', 'room' => 'B2'],
            ['day' => 'Tuesday', 'date' => '١٢/٠١/٢٠٢٧', 'time' => '12:00', 'course' => 'Chemistry', 'year' => 'First year'],
            ['course' => 'Make-up exam'],
            ['day' => 'Monday', 'course' => ''],
        ], 'exams-1');

        $this->assertSame(5, $board['total']);
        $this->assertSame(['2027-01-10', '2027-01-12', ''], array_column($board['days'], 'iso'));
        $this->assertSame(['Anatomy I', 'Physiology I'], array_column($board['days'][0]['exams'], 'course'));
        $this->assertSame(['Anatomy II', 'Chemistry'], array_column($board['days'][1]['exams'], 'course'));
        $this->assertSame(['Make-up exam'], array_column($board['days'][2]['exams'], 'course'));

        $this->assertSame(['Second year', 'First year'], array_column($board['years'], 'label'));
        $this->assertSame([2, 2], array_column($board['years'], 'count'));

        // Anatomy II (second year) follows Physiology I; Chemistry (first year) follows Anatomy I.
        $this->assertSame('exams-1-3', $board['days'][1]['exams'][0]['previous']);
        $this->assertSame('exams-1-2', $board['days'][1]['exams'][1]['previous']);
        $this->assertSame('', $board['days'][0]['exams'][0]['previous']);
    }

    public function test_unreadable_dates_keep_the_editor_order(): void
    {
        $board = ExamScheduleBoard::build([
            ['day' => 'الأحد', 'date' => 'الأسبوع الأول', 'course' => 'A'],
            ['day' => 'الاثنين', 'date' => 'الأسبوع الأول', 'course' => 'B'],
        ]);

        $this->assertSame(['الأحد', 'الاثنين'], array_column($board['days'], 'day'));
        $this->assertSame('', ExamScheduleBoard::isoDate('31/02/2027'));
        $this->assertSame('2027-02-03', ExamScheduleBoard::isoDate('3.2.2027'));
    }
}
