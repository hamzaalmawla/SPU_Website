<?php

declare(strict_types=1);

namespace Tests\Unit\Chatbot;

use App\Support\Chatbot\TableRows;
use PHPUnit\Framework\TestCase;

final class TableRowsTest extends TestCase
{
    public function test_markdown_table_rows_keep_their_column_names(): void
    {
        $text = "# الحدود الدنيا\n| الجامعة | الرغبة | الحد الأدنى |\n|---|:---:|---|\n| الجامعة السورية الخاصة | الطب البشري | 80 |\n| جامعة القلمون | | 75 |\nبعد الجدول.";

        $this->assertSame(
            "# الحدود الدنيا\n| الجامعة | الرغبة | الحد الأدنى |\nالجامعة: الجامعة السورية الخاصة ; الرغبة: الطب البشري ; الحد الأدنى: 80\nالجامعة: جامعة القلمون ; الحد الأدنى: 75\nبعد الجدول.",
            TableRows::expandMarkdown($text),
        );
    }

    public function test_a_table_continued_without_titles_keeps_its_values(): void
    {
        $this->assertSame("|  |  |\nجامعة المنارة ; 60", TableRows::expandMarkdown("| | |\n| جامعة المنارة | 60 |"));
    }

    public function test_cell_lines_under_a_header_become_pairs_and_repeated_headers_go(): void
    {
        $lines = [
            'الجدول رقم (1)',
            'الرغبة | الجامعة | الحد الأدنى (مئوياً)',
            'الطب البشري | الجامعة السورية الخاصة | 80',
            'الرغبة | الجامعة | الحد الأدنى (مئوياً)',
            'طب الأسنان | جامعة القلمون الخاصة | 75',
        ];

        $this->assertSame([
            'الجدول رقم (1)',
            'الرغبة | الجامعة | الحد الأدنى (مئوياً)',
            'الرغبة: الطب البشري ; الجامعة: الجامعة السورية الخاصة ; الحد الأدنى (مئوياً): 80',
            'الرغبة: طب الأسنان ; الجامعة: جامعة القلمون الخاصة ; الحد الأدنى (مئوياً): 75',
        ], TableRows::expandCellLines($lines));
    }
}
