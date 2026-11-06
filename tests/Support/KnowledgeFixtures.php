<?php

declare(strict_types=1);

namespace Tests\Support;

use ZipArchive;

/**
 * Small, generated stand-ins for the files editors upload to the AI
 * assistant: an Arabic PDF written glyph by glyph in visual order (as Excel
 * exports it), a PDF of scanned page images, a photo, and an Excel workbook.
 * Nothing here comes from a real document.
 */
final class KnowledgeFixtures
{
    /**
     * One-byte codes the fixture font maps to Arabic letters (ToUnicode).
     *
     * @var array<string, string>
     */
    private const ARABIC = [
        'ا' => 'A', 'ل' => 'L', 'ج' => 'J', 'د' => 'D', 'و' => 'W', 'ط' => 'T', 'ب' => 'B',
        'ة' => 'H', 'ع' => 'E', 'م' => 'M', 'ص' => 'S', 'خ' => 'K', 'ي' => 'Y', 'ر' => 'R', 'ش' => 'X',
    ];

    /**
     * A PDF whose Arabic is drawn one glyph at a time from left to right
     * (visual order), with real space glyphs and a number, in two table
     * columns far apart. Each row is [right cell, left cell].
     *
     * @param  list<array{0: string, 1: string}>  $rows
     */
    public static function visualOrderArabicPdf(array $rows): string
    {
        $map = self::ARABIC + [' ' => ' '];
        foreach (range(0, 9) as $digit) {
            $map[(string) $digit] = (string) $digit;
        }

        $stream = "BT /F1 12 Tf\n";
        $y = 700;
        foreach ($rows as [$right, $left]) {
            // Right cell ends at x=500, left cell ends at x=200; glyphs 7pt apart.
            foreach ([[$right, 500.0], [$left, 200.0]] as [$cell, $end]) {
                $glyphs = self::visualGlyphs($cell);
                $x = $end - 7 * count($glyphs);
                foreach ($glyphs as $glyph) {
                    $code = $map[$glyph] ?? '?';
                    $stream .= sprintf("1 0 0 1 %.1f %d Tm (%s) Tj\n", $x, $y, addcslashes($code, '()\\'));
                    $x += 7;
                }
            }
            $y -= 30;
        }
        $stream .= 'ET';

        $bfchar = '';
        foreach ($map as $char => $code) {
            $bfchar .= sprintf("<%02X> <%04X>\n", ord((string) $code), mb_ord((string) $char));
        }
        $cmap = "/CIDInit /ProcSet findresource begin\n12 dict begin\nbegincmap\n/CMapName /Fixture def\n1 begincodespacerange\n<00> <FF>\nendcodespacerange\n"
            .count($map)." beginbfchar\n".$bfchar."endbfchar\nendcmap\nCMapName currentdict /CMap defineresource pop\nend\nend";

        return self::pdf([
            1 => '<< /Type /Catalog /Pages 2 0 R >>',
            2 => '<< /Type /Pages /Kids [3 0 R] /Count 1 >>',
            3 => '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] /Contents 4 0 R /Resources << /Font << /F1 5 0 R >> >> >>',
            4 => self::stream($stream),
            5 => '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /ToUnicode 6 0 R >>',
            6 => self::stream($cmap),
        ]);
    }

    /**
     * A PDF of `$pages` scanned pages: each page is one JPEG drawn over the
     * whole page, plus a scanner app's small watermark text.
     */
    public static function scannedPdf(int $pages): string
    {
        $objects = [
            1 => '<< /Type /Catalog /Pages 2 0 R >>',
            3 => '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>',
        ];
        $kids = [];
        $next = 4;
        for ($page = 1; $page <= $pages; $page++) {
            $jpeg = self::jpeg(600, 800, 'Page '.$page);
            $content = "q 612 0 0 792 0 0 cm /Im1 Do Q\nBT /F1 8 Tf 540 20 Td (CamScanner) Tj ET";
            $objects[$next] = '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] /Contents '.($next + 1).' 0 R /Resources << /Font << /F1 3 0 R >> /XObject << /Im1 '.($next + 2).' 0 R >> >> >>';
            $objects[$next + 1] = self::stream($content);
            $objects[$next + 2] = '<< /Type /XObject /Subtype /Image /Width 600 /Height 800 /ColorSpace /DeviceRGB /BitsPerComponent 8 /Filter /DCTDecode /Length '.strlen($jpeg)." >>\nstream\n".$jpeg."\nendstream";
            $kids[] = $next.' 0 R';
            $next += 3;
        }
        $objects[2] = '<< /Type /Pages /Kids ['.implode(' ', $kids).'] /Count '.$pages.' >>';

        return self::pdf($objects);
    }

    /**
     * A JPEG with a line of text on it (a "photo" of a document).
     */
    public static function jpeg(int $width = 600, int $height = 800, string $label = 'Scan'): string
    {
        $image = imagecreatetruecolor($width, $height);
        imagefill($image, 0, 0, (int) imagecolorallocate($image, 255, 255, 255));
        imagestring($image, 5, 40, 40, $label, (int) imagecolorallocate($image, 0, 0, 0));
        ob_start();
        imagejpeg($image, null, 80);
        $bytes = (string) ob_get_clean();

        return $bytes;
    }

    /**
     * An .xlsx workbook. Each sheet is rows of cells (strings or numbers);
     * `merges` lists ranges such as "A1:C1"; `hidden` hides the sheet.
     *
     * @param  array<string, array{rows: list<list<string|int|float|null>>, merges?: list<string>, hidden?: bool}>  $sheets
     */
    public static function xlsx(string $path, array $sheets): void
    {
        $strings = [];
        $stringIndex = static function (string $value) use (&$strings): int {
            $index = array_search($value, $strings, true);
            if ($index === false) {
                $strings[] = $value;
                $index = count($strings) - 1;
            }

            return (int) $index;
        };

        $zip = new ZipArchive;
        $zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE);

        $sheetEntries = '';
        $relations = '';
        $number = 0;
        foreach ($sheets as $name => $sheet) {
            $number++;
            $xmlRows = '';
            foreach ($sheet['rows'] as $rowIndex => $row) {
                $cells = '';
                foreach (array_values($row) as $columnIndex => $value) {
                    if ($value === null || $value === '') {
                        continue;
                    }
                    $reference = chr(65 + $columnIndex).($rowIndex + 1);
                    $cells .= is_string($value)
                        ? '<c r="'.$reference.'" t="s"><v>'.$stringIndex($value).'</v></c>'
                        : '<c r="'.$reference.'"><v>'.$value.'</v></c>';
                }
                $xmlRows .= '<row r="'.($rowIndex + 1).'">'.$cells.'</row>';
            }
            $merges = '';
            if (($sheet['merges'] ?? []) !== []) {
                $merges = '<mergeCells count="'.count($sheet['merges']).'">'.implode('', array_map(static fn (string $range): string => '<mergeCell ref="'.$range.'"/>', $sheet['merges'])).'</mergeCells>';
            }
            $zip->addFromString('xl/worksheets/sheet'.$number.'.xml', '<?xml version="1.0" encoding="UTF-8"?><worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetData>'.$xmlRows.'</sheetData>'.$merges.'</worksheet>');
            $sheetEntries .= '<sheet name="'.htmlspecialchars($name, ENT_XML1).'" sheetId="'.$number.'"'.(($sheet['hidden'] ?? false) ? ' state="hidden"' : '').' r:id="rId'.$number.'"/>';
            $relations .= '<Relationship Id="rId'.$number.'" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet'.$number.'.xml"/>';
        }

        $zip->addFromString('[Content_Types].xml', '<?xml version="1.0"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"/>');
        $zip->addFromString('xl/workbook.xml', '<?xml version="1.0" encoding="UTF-8"?><workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets>'.$sheetEntries.'</sheets></workbook>');
        $zip->addFromString('xl/_rels/workbook.xml.rels', '<?xml version="1.0" encoding="UTF-8"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'.$relations.'</Relationships>');
        $zip->addFromString('xl/sharedStrings.xml', '<?xml version="1.0" encoding="UTF-8"?><sst xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'.implode('', array_map(static fn (string $value): string => '<si><t>'.htmlspecialchars($value, ENT_XML1).'</t></si>', $strings)).'</sst>');
        $zip->close();
    }

    /**
     * The glyphs of a right-to-left cell in the order they are drawn from
     * left to right: Arabic runs reversed, digit runs kept as they are.
     *
     * @return list<string>
     */
    private static function visualGlyphs(string $cell): array
    {
        preg_match_all('/\d+|[^\d]/u', $cell, $matches);
        $logical = $matches[0];

        $visual = [];
        foreach (array_reverse($logical) as $token) {
            foreach (preg_match('/^\d+$/', $token) === 1 ? str_split($token) : [$token] as $glyph) {
                $visual[] = $glyph;
            }
        }

        return $visual;
    }

    private static function stream(string $content): string
    {
        return '<< /Length '.strlen($content)." >>\nstream\n".$content."\nendstream";
    }

    /**
     * @param  array<int, string>  $objects
     */
    private static function pdf(array $objects): string
    {
        ksort($objects);
        $pdf = "%PDF-1.4\n";
        $offsets = [];
        foreach ($objects as $id => $object) {
            $offsets[$id] = strlen($pdf);
            $pdf .= $id." 0 obj\n".$object."\nendobj\n";
        }
        $xref = strlen($pdf);
        $pdf .= "xref\n0 ".(count($objects) + 1)."\n0000000000 65535 f \n";
        foreach ($offsets as $offset) {
            $pdf .= sprintf("%010d 00000 n \n", $offset);
        }

        return $pdf.'trailer << /Size '.(count($objects) + 1)." /Root 1 0 R >>\nstartxref\n".$xref."\n%%EOF";
    }
}
