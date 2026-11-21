<?php

declare(strict_types=1);

namespace Tests\Unit\Chatbot;

use App\Support\Chatbot\DocumentTextExtractor;
use App\Support\Chatbot\SpreadsheetText;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Tests\Support\KnowledgeFixtures;

final class SpreadsheetTextTest extends TestCase
{
    /** @var list<string> */
    private array $files = [];

    protected function tearDown(): void
    {
        foreach ($this->files as $file) {
            @unlink($file);
        }
        parent::tearDown();
    }

    public function test_each_row_reads_as_column_value_pairs_under_its_sheet_and_title(): void
    {
        $path = $this->workbook([
            'الجدول 1' => [
                'rows' => [
                    ['الحدود الدنيا للتحويل المماثل 2026-2027', null, null],
                    ['الجامعة', 'الرغبة', 'الحد الأدنى'],
                    ['الجامعة السورية الخاصة', 'الطب البشري', 80],
                    [null, 'الصيدلة', 67.459999999999994],
                    ['الجامعة', 'الرغبة', 'الحد الأدنى'],
                    ['جامعة القلمون الخاصة', 'طب الأسنان', 75],
                ],
                // The title spans the table; the university spans two rows.
                'merges' => ['A1:C1', 'A3:A4'],
            ],
            'Pivot' => ['rows' => [['secret working sheet', 1]], 'hidden' => true],
        ]);

        $text = DocumentTextExtractor::extract($path, 'xlsx')[0]['text'];

        $this->assertSame(
            "## الجدول 1\n\n### الحدود الدنيا للتحويل المماثل 2026-2027\n| الجامعة | الرغبة | الحد الأدنى |\n"
            ."الجامعة: الجامعة السورية الخاصة ; الرغبة: الطب البشري ; الحد الأدنى: 80\n"
            ."الجامعة: الجامعة السورية الخاصة ; الرغبة: الصيدلة ; الحد الأدنى: 67.46\n"
            .'الجامعة: جامعة القلمون الخاصة ; الرغبة: طب الأسنان ; الحد الأدنى: 75',
            $text,
        );
    }

    public function test_a_sheet_without_a_header_row_is_read_as_a_list(): void
    {
        $path = $this->workbook([
            'Index' => ['rows' => [['Table 1', 'Similar transfer'], ['Table 2', 'Change of enrolment'], ['Table 3', 'Institutes']]],
        ]);

        $this->assertSame(
            "## Index\n\nTable 1 | Similar transfer\nTable 2 | Change of enrolment\nTable 3 | Institutes",
            SpreadsheetText::xlsx($path),
        );
    }

    public function test_a_broken_workbook_fails_with_a_translation_key(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'chatbot').'.xlsx';
        $this->files[] = $path;
        file_put_contents($path, 'not a zip');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('admin_chatbot.errors.xlsx_unreadable');

        DocumentTextExtractor::extract($path, 'xlsx');
    }

    /**
     * @param  array<string, array{rows: list<list<string|int|float|null>>, merges?: list<string>, hidden?: bool}>  $sheets
     */
    private function workbook(array $sheets): string
    {
        $path = tempnam(sys_get_temp_dir(), 'chatbot').'.xlsx';
        $this->files[] = $path;
        KnowledgeFixtures::xlsx($path, $sheets);

        return $path;
    }
}
