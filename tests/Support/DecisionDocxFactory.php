<?php

declare(strict_types=1);

namespace Tests\Support;

use ZipArchive;

/**
 * Builds .docx files laid out like the faculty council decisions the student
 * list import reads (Docs/STUDENT_LIST_IMPORT_PLAN.md), with invented names.
 * The real decisions name real students and are not stored in the repository.
 */
final class DecisionDocxFactory
{
    /**
     * A graduates decision: a two-row header (the GPA header spans points and
     * percent), then one row per student.
     *
     * @param  list<array{id: string, name: string, specialization: string, points: string, percent: string, grade: string, semester?: string}>  $students
     * @param  list<string>|null  $columnOrder  header keys in order, to test a moved column
     */
    public static function graduates(string $path, array $students, ?array $columnOrder = null): string
    {
        $headers = [
            'line' => '#',
            'id' => 'الرقم الجامعي',
            'name' => 'اسم الطالب',
            'faculty' => 'الكلية',
            'specialization' => 'الاختصاص',
            'gpa' => 'المعدل التراكمي',
            'grade' => 'التقدير',
            'semester' => 'الفصل الدراسي الذي أنهى فيه الطالب مقرراته',
        ];
        $order = $columnOrder ?? array_keys($headers);

        $top = '';
        $sub = '';
        foreach ($order as $key) {
            if ($key === 'gpa') {
                $top .= self::cell($headers[$key], span: 2);
                $sub .= self::cell('المعدل النقطي').self::cell('المعدل المئوي%');
            } else {
                $top .= self::cell($headers[$key], merge: 'restart');
                $sub .= self::cell('', merge: 'continue');
            }
        }

        $rows = self::row($top).self::row($sub);
        foreach ($students as $index => $student) {
            $cells = '';
            foreach ($order as $key) {
                $cells .= match ($key) {
                    'line' => self::cell((string) ($index + 1)),
                    'id' => self::cell($student['id']),
                    'name' => self::cell($student['name']),
                    'faculty' => self::cell('كلية الهندسة'),
                    'specialization' => self::cell($student['specialization']),
                    'gpa' => self::cell($student['points']).self::cell($student['percent']),
                    'grade' => self::cell($student['grade']),
                    'semester' => self::cell($student['semester'] ?? 'الفصل الثاني 2025/2026'),
                };
            }
            $rows .= self::row($cells);
        }

        return self::write($path, [
            'قرار مجلس كلية رقم ( 5/14 )',
            'اطلع مجلس الكلية على أسماء الطلاب الذين أنهوا كافة متطلبات التخرج في الفصل الثاني من العام الدراسي 2025/2026.',
            'المادة 1: الموافقة على تخرج الطلاب التالية أسمائهم وفق الجدول الآتي:',
        ], '<w:tbl>'.$rows.'</w:tbl>', ['المادة 2: يرفع القرار إلى مجلس الجامعة لاستكمال أسبابه.']);
    }

    /**
     * A graduates decision with a second section in the same table, as Dentistry
     * sends it: credit-hour graduates, then a full-width "نظام فصلي" title and
     * its own two-row header whose GPA column holds the percentage only.
     *
     * @param  list<array{id: string, name: string, specialization: string, points: string, percent: string, grade: string}>  $creditHour
     * @param  list<array{id: string, name: string, specialization: string, percent: string, grade: string}>  $semesterSystem
     */
    public static function graduatesWithSemesterSystemSection(string $path, array $creditHour, array $semesterSystem): string
    {
        $header = static fn (string $gpaSubHeaders, int $gpaSpan): string => self::row(
            self::cell('#', merge: 'restart').self::cell('الرقم الجامعي', merge: 'restart').self::cell('اسم الطالب', merge: 'restart')
            .self::cell('الكلية', merge: 'restart').self::cell('الاختصاص', merge: 'restart').self::cell('المعدل التراكمي', span: $gpaSpan)
            .self::cell('التقدير', merge: 'restart').self::cell('الفصل الدراسي الذي أنهى فيه الطالب مقرراته', merge: 'restart')
        ).self::row(str_repeat(self::cell('', merge: 'continue'), 5).$gpaSubHeaders.str_repeat(self::cell('', merge: 'continue'), 2));
        $student = static fn (int $line, array $student, string $gpa): string => self::row(
            self::cell((string) $line).self::cell($student['id']).self::cell($student['name']).self::cell('كلية طب الأسنان')
            .self::cell($student['specialization']).$gpa.self::cell($student['grade']).self::cell('الفصل الصيفي 2025/2026')
        );

        $rows = $header(self::cell('المعدل النقطي').self::cell('المعدل المئوي%'), 2);
        foreach ($creditHour as $index => $row) {
            $rows .= $student($index + 1, $row, self::cell($row['points']).self::cell($row['percent']));
        }
        $rows .= self::row(self::cell('نظام فصلي', span: 9));
        $rows .= $header(self::cell('المعدل المئوي %'), 1);
        foreach ($semesterSystem as $index => $row) {
            $rows .= $student($index + 1, $row, self::cell($row['percent']));
        }

        return self::write($path, [
            'قرار مجلس كلية رقم ( 1/7 )',
            'اطلع مجلس الكلية على أسماء الطلاب الذين أنهوا كافة متطلبات التخرج في الفصل الصيفي من العام الدراسي 2025/2026.',
        ], '<w:tbl>'.$rows.'</w:tbl>');
    }

    /**
     * A top-students decision: blocks of a full-width title row, a header row
     * and ranked students, with an empty full-width spacer row between two blocks.
     *
     * @param  array<string, list<array{id: string, name: string, gpa: string}>>  $blocks  block title => students
     */
    public static function topStudents(string $path, array $blocks, string $semesterText = 'للفصل الثاني من العام الدراسي 2025/2026'): string
    {
        $rows = '';
        $first = true;
        foreach ($blocks as $title => $students) {
            if (! $first) {
                $rows .= self::row(self::cell('', span: 4));
            }
            $first = false;
            $rows .= self::row(self::cell($title, span: 4));
            $rows .= self::row(self::cell('ت').self::cell('الرقم الجامعي').self::cell('اسم الطالب').self::cell('المعدل التنافسي'));
            foreach ($students as $index => $student) {
                $rows .= self::row(self::cell((string) ($index + 1)).self::cell($student['id']).self::cell($student['name']).self::cell($student['gpa']));
            }
        }

        return self::write($path, [
            'قرار مجلس كلية رقم ( 12 / 14 )',
            'ناقش مجلس الكلية قائمة الطلاب الأوائل '.$semesterText.' والتي تعتمد المعدلات التنافسية لترتيب الأوائل.',
        ], '<w:tbl>'.$rows.'</w:tbl>', ['المادة 2- يرفع القرار إلى مجلس شؤون الطلاب لاستكمال اسبابه.']);
    }

    /**
     * A top-students list with two semesters side by side, as Dentistry sends
     * it: each half has its own year titles ("السنة الأولى (الفصل الأول)"),
     * headers and ranked students, no GPA, and the halves drift out of step
     * when one has more students.
     *
     * @param  array<string, list<array{id: string, name: string}>>  $first  block title => students
     * @param  array<string, list<array{id: string, name: string}>>  $second
     */
    public static function topStudentsSideBySide(string $path, array $first, array $second): string
    {
        $half = static function (array $blocks): array {
            $rows = [];
            foreach ($blocks as $title => $students) {
                $rows[] = self::cell('').self::cell($title, span: 2);
                $rows[] = self::cell('ت').self::cell('الرقم الجامعي').self::cell('اسم الطالب');
                foreach ($students as $index => $student) {
                    $rows[] = self::cell((string) ($index + 1)).self::cell($student['id']).self::cell($student['name']);
                }
            }

            return $rows;
        };

        $left = $half($first);
        $right = $half($second);
        $empty = self::cell('').self::cell('').self::cell('');
        $rows = '';
        for ($i = 0; $i < max(count($left), count($right)); $i++) {
            $rows .= self::row(($left[$i] ?? $empty).($right[$i] ?? $empty));
        }

        return self::write($path, ['نرفق لكم جدول الخريجين الأوائل للفصل الأول والثاني :'], '<w:tbl>'.$rows.'</w:tbl>', ['عميد الكلية']);
    }

    /**
     * @param  list<string>  $before
     * @param  list<string>  $after
     */
    public static function write(string $path, array $before, string $tables, array $after = []): string
    {
        $paragraphs = static fn (array $texts): string => implode('', array_map(
            static fn (string $text): string => '<w:p><w:r><w:t xml:space="preserve">'.htmlspecialchars($text, ENT_XML1).'</w:t></w:r></w:p>',
            $texts,
        ));

        $xml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"><w:body>'
            .$paragraphs($before).$tables.$paragraphs($after)
            .'</w:body></w:document>';

        return self::zip($path, $xml);
    }

    public static function zip(string $path, string $documentXml): string
    {
        $zip = new ZipArchive;
        $zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        $zip->addFromString('[Content_Types].xml', '<?xml version="1.0" encoding="UTF-8"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="xml" ContentType="application/xml"/><Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/></Types>');
        $zip->addFromString('word/document.xml', $documentXml);
        $zip->close();

        return $path;
    }

    private static function row(string $cells): string
    {
        return '<w:tr>'.$cells.'</w:tr>';
    }

    private static function cell(string $text, int $span = 1, ?string $merge = null): string
    {
        $properties = '';
        if ($span > 1) {
            $properties .= '<w:gridSpan w:val="'.$span.'"/>';
        }
        if ($merge === 'restart') {
            $properties .= '<w:vMerge w:val="restart"/>';
        } elseif ($merge === 'continue') {
            $properties .= '<w:vMerge/>';
        }

        return '<w:tc>'.($properties !== '' ? '<w:tcPr>'.$properties.'</w:tcPr>' : '')
            .'<w:p><w:r><w:t xml:space="preserve">'.htmlspecialchars($text, ENT_XML1).'</w:t></w:r></w:p></w:tc>';
    }
}
