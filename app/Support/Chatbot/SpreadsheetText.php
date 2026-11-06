<?php

declare(strict_types=1);

namespace App\Support\Chatbot;

use DOMDocument;
use DOMElement;
use DOMXPath;
use InvalidArgumentException;
use ZipArchive;

/**
 * Reads an Excel workbook (.xlsx) as text the assistant can search.
 *
 * Every visible sheet becomes a "## sheet name" section. Title rows above a
 * table become "###" headings, and each data row becomes one line of
 * "Column: value ; Column: value" pairs, so a row keeps its meaning in
 * whichever passage it lands in. Merged cells are repeated into every cell
 * they cover (a merged university name applies to all its rows), formulas
 * give their saved results, dates read as dates and floating-point noise
 * (67.459999999999994) is rounded away.
 *
 * Only ZipArchive and DOM are used; nothing is executed or fetched.
 */
final class SpreadsheetText
{
    private const MAIN_NS = 'http://schemas.openxmlformats.org/spreadsheetml/2006/main';

    private const REL_NS = 'http://schemas.openxmlformats.org/officeDocument/2006/relationships';

    private const MAX_ZIP_ENTRIES = 5000;

    private const MAX_XML_BYTES = 60 * 1024 * 1024;

    private const MAX_ROWS_PER_SHEET = 100_000;

    private const MAX_COLUMNS = 200;

    /** Built-in Excel number formats that are dates or times. */
    private const DATE_FORMAT_IDS = [14, 15, 16, 17, 18, 19, 20, 21, 22, 27, 30, 36, 45, 46, 47, 50, 57];

    /**
     * @throws InvalidArgumentException with an admin_chatbot.errors.* key
     */
    public static function xlsx(string $path): string
    {
        $zip = new ZipArchive;
        if ($zip->open($path, ZipArchive::RDONLY) !== true) {
            throw new InvalidArgumentException('admin_chatbot.errors.xlsx_unreadable');
        }

        try {
            if ($zip->numFiles > self::MAX_ZIP_ENTRIES || $zip->statName('xl/workbook.xml') === false) {
                throw new InvalidArgumentException('admin_chatbot.errors.xlsx_unreadable');
            }

            $workbook = self::xpath(self::entry($zip, 'xl/workbook.xml'));
            $relations = self::relations($zip);
            $strings = self::sharedStrings($zip);
            $dateStyles = self::dateStyles($zip);

            $sections = [];
            foreach ($workbook->query('/m:workbook/m:sheets/m:sheet') ?: [] as $sheet) {
                if (! $sheet instanceof DOMElement || in_array($sheet->getAttribute('state'), ['hidden', 'veryHidden'], true)) {
                    continue;
                }

                $target = $relations[$sheet->getAttributeNS(self::REL_NS, 'id')] ?? null;
                if ($target === null || $zip->statName($target) === false) {
                    continue;
                }

                $grid = self::grid(self::xpath(self::entry($zip, $target)), $strings, $dateStyles);
                $text = self::sheetText($grid);
                if ($text !== '') {
                    $sections[] = '## '.self::oneLine($sheet->getAttribute('name'))."\n\n".$text;
                }
            }
        } finally {
            $zip->close();
        }

        return implode("\n\n", $sections);
    }

    /**
     * @param  list<list<string>>  $grid
     */
    private static function sheetText(array $grid): string
    {
        $rows = [];
        foreach ($grid as $row) {
            $cells = array_map(self::oneLine(...), $row);
            if (implode('', $cells) !== '') {
                $rows[] = $cells;
            }
        }

        $lines = [];
        $header = null;
        // false once the sheet turned out to be a plain list without one.
        $expectHeader = true;

        foreach ($rows as $index => $cells) {
            $filled = array_values(array_filter($cells, static fn (string $cell): bool => $cell !== ''));

            // One distinct value across the row (a title, or a merged
            // section label inside a table): a heading.
            if (count(array_unique($filled)) === 1 && (count($filled) > 1 || $header === null || count(array_filter($header)) > 2)) {
                $lines[] = '';
                $lines[] = '### '.$filled[0];

                continue;
            }

            if ($header === null && $expectHeader) {
                if (self::looksLikeData($cells, $rows[$index + 1] ?? null)) {
                    $expectHeader = false;
                } else {
                    $header = $cells;
                    $lines[] = '| '.implode(' | ', $filled).' |';

                    continue;
                }
            }

            if ($header === null) {
                $lines[] = implode(' | ', $filled);

                continue;
            }

            // A header repeated (every printed page of an export).
            if ($cells === $header) {
                continue;
            }

            $pairs = [];
            foreach ($cells as $column => $cell) {
                if ($cell === '') {
                    continue;
                }
                $label = $header[$column] ?? '';
                $pairs[] = $label !== '' && $label !== $cell ? $label.': '.$cell : $cell;
            }
            $lines[] = implode(' ; ', $pairs);
        }

        return trim(implode("\n", $lines));
    }

    /**
     * Whether a sheet's first row is data rather than column names: it has
     * numbers, or it reads like the row below it once digits are ignored
     * ("Table 1 | ..." above "Table 2 | ...").
     *
     * @param  list<string>  $first
     * @param  list<string>|null  $next
     */
    private static function looksLikeData(array $first, ?array $next): bool
    {
        foreach ($first as $cell) {
            if ($cell !== '' && is_numeric(str_replace([',', '%'], '', $cell))) {
                return true;
            }
        }

        if ($next === null) {
            return false;
        }

        $shape = static fn (string $cell): string => trim((string) preg_replace('/\p{N}+/u', '#', $cell));
        $same = 0;
        $compared = 0;
        foreach ($first as $column => $cell) {
            if ($cell === '' || ($next[$column] ?? '') === '') {
                continue;
            }
            $compared++;
            if ($shape($cell) === $shape($next[$column])) {
                $same++;
            }
        }

        return $compared > 0 && $same * 2 >= $compared;
    }

    /**
     * The sheet as rows of cell text, merged ranges filled in.
     *
     * @param  list<string>  $strings
     * @param  array<int, true>  $dateStyles
     * @return list<list<string>>
     */
    private static function grid(DOMXPath $sheet, array $strings, array $dateStyles): array
    {
        $cells = [];
        $maxRow = -1;
        $maxColumn = -1;

        foreach ($sheet->query('/m:worksheet/m:sheetData/m:row/m:c') ?: [] as $cell) {
            if (! $cell instanceof DOMElement) {
                continue;
            }

            $position = self::position($cell->getAttribute('r'));
            if ($position === null || $position[0] >= self::MAX_ROWS_PER_SHEET || $position[1] >= self::MAX_COLUMNS) {
                continue;
            }

            $value = self::cellValue($sheet, $cell, $strings, $dateStyles);
            if ($value === '') {
                continue;
            }

            [$row, $column] = $position;
            $cells[$row][$column] = $value;
            $maxRow = max($maxRow, $row);
            $maxColumn = max($maxColumn, $column);
        }

        foreach ($sheet->query('/m:worksheet/m:mergeCells/m:mergeCell') ?: [] as $merge) {
            if (! $merge instanceof DOMElement) {
                continue;
            }

            $range = explode(':', $merge->getAttribute('ref'));
            $from = self::position($range[0]);
            $to = self::position($range[1] ?? $range[0]);
            if ($from === null || $to === null) {
                continue;
            }

            $value = $cells[$from[0]][$from[1]] ?? '';
            if ($value === '') {
                continue;
            }

            $lastRow = min($to[0], self::MAX_ROWS_PER_SHEET - 1);
            $lastColumn = min($to[1], self::MAX_COLUMNS - 1);
            for ($row = $from[0]; $row <= $lastRow; $row++) {
                for ($column = $from[1]; $column <= $lastColumn; $column++) {
                    $cells[$row][$column] = $value;
                }
            }
            $maxRow = max($maxRow, $lastRow);
            $maxColumn = max($maxColumn, $lastColumn);
        }

        $grid = [];
        ksort($cells);
        foreach ($cells as $row) {
            $line = [];
            for ($column = 0; $column <= $maxColumn; $column++) {
                $line[] = $row[$column] ?? '';
            }
            $grid[] = $line;
        }

        return $grid;
    }

    /**
     * @param  list<string>  $strings
     * @param  array<int, true>  $dateStyles
     */
    private static function cellValue(DOMXPath $sheet, DOMElement $cell, array $strings, array $dateStyles): string
    {
        $type = $cell->getAttribute('t');

        if ($type === 'inlineStr') {
            return trim(self::textOf($sheet, $cell, './m:is'));
        }

        $raw = $sheet->query('./m:v', $cell)?->item(0)?->textContent;
        if ($raw === null || $raw === '') {
            return '';
        }

        return match ($type) {
            's' => trim($strings[(int) $raw] ?? ''),
            'b' => $raw === '1' ? 'TRUE' : 'FALSE',
            'str', 'e' => trim($raw),
            default => self::number($raw, isset($dateStyles[(int) $cell->getAttribute('s')])),
        };
    }

    private static function number(string $raw, bool $isDate): string
    {
        if (! is_numeric($raw)) {
            return trim($raw);
        }

        $value = (float) $raw;
        if ($isDate && $value > 0 && $value < 2_958_466) {
            // Excel's day 0 is 1899-12-30 (its 1900 leap-year bug included).
            $seconds = (int) round(($value - 25569) * 86400);
            $format = abs($value - floor($value)) > 1e-9 ? 'Y-m-d H:i' : 'Y-m-d';

            return gmdate($format, $seconds);
        }

        if (abs($value - round($value)) < 1e-9 && abs($value) < 1e15) {
            return (string) (int) round($value);
        }

        return rtrim(rtrim(number_format($value, 4, '.', ''), '0'), '.');
    }

    /**
     * @return list<string>
     */
    private static function sharedStrings(ZipArchive $zip): array
    {
        if ($zip->statName('xl/sharedStrings.xml') === false) {
            return [];
        }

        $xpath = self::xpath(self::entry($zip, 'xl/sharedStrings.xml'));
        $strings = [];
        foreach ($xpath->query('/m:sst/m:si') ?: [] as $item) {
            $strings[] = $item instanceof DOMElement ? self::textOf($xpath, $item, '.') : '';
        }

        return $strings;
    }

    /**
     * Style indexes whose number format is a date.
     *
     * @return array<int, true>
     */
    private static function dateStyles(ZipArchive $zip): array
    {
        if ($zip->statName('xl/styles.xml') === false) {
            return [];
        }

        $xpath = self::xpath(self::entry($zip, 'xl/styles.xml'));
        $customDates = [];
        foreach ($xpath->query('/m:styleSheet/m:numFmts/m:numFmt') ?: [] as $format) {
            if ($format instanceof DOMElement) {
                // Strip quoted text and [colour] blocks, then look for date parts.
                $code = (string) preg_replace('/"[^"]*"|\[[^\]]*\]/', '', $format->getAttribute('formatCode'));
                if (preg_match('/[dmyhs]/i', $code) === 1 && preg_match('/[0#]/', $code) !== 1) {
                    $customDates[(int) $format->getAttribute('numFmtId')] = true;
                }
            }
        }

        $styles = [];
        $index = 0;
        foreach ($xpath->query('/m:styleSheet/m:cellXfs/m:xf') ?: [] as $xf) {
            if ($xf instanceof DOMElement) {
                $id = (int) $xf->getAttribute('numFmtId');
                if (in_array($id, self::DATE_FORMAT_IDS, true) || isset($customDates[$id])) {
                    $styles[$index] = true;
                }
            }
            $index++;
        }

        return $styles;
    }

    /**
     * Sheet relationship id => zip entry name.
     *
     * @return array<string, string>
     */
    private static function relations(ZipArchive $zip): array
    {
        if ($zip->statName('xl/_rels/workbook.xml.rels') === false) {
            return [];
        }

        $dom = self::dom(self::entry($zip, 'xl/_rels/workbook.xml.rels'));
        $relations = [];
        foreach ($dom->getElementsByTagName('Relationship') as $relation) {
            $target = ltrim($relation->getAttribute('Target'), '/');
            $target = str_starts_with($target, 'xl/') ? $target : 'xl/'.$target;
            // No "../" tricks: a sheet lives inside xl/.
            if (! str_contains($target, '..')) {
                $relations[$relation->getAttribute('Id')] = $target;
            }
        }

        return $relations;
    }

    /**
     * Text of the <t> runs under a node, skipping phonetic hints (<rPh>).
     */
    private static function textOf(DOMXPath $xpath, DOMElement $node, string $base): string
    {
        $text = '';
        foreach ($xpath->query($base.'//m:t[not(ancestor::m:rPh)]', $node) ?: [] as $run) {
            $text .= $run->textContent;
        }

        return $text;
    }

    /**
     * "B12" => [11, 1] (zero-based row, column).
     *
     * @return array{0: int, 1: int}|null
     */
    private static function position(string $reference): ?array
    {
        if (preg_match('/^\$?([A-Z]{1,3})\$?(\d{1,7})$/', strtoupper(trim($reference)), $matches) !== 1) {
            return null;
        }

        $column = 0;
        foreach (str_split($matches[1]) as $letter) {
            $column = $column * 26 + (ord($letter) - 64);
        }

        return [(int) $matches[2] - 1, $column - 1];
    }

    private static function oneLine(string $text): string
    {
        return trim((string) preg_replace('/\s+/u', ' ', str_replace("\u{00A0}", ' ', $text)));
    }

    private static function entry(ZipArchive $zip, string $name): string
    {
        $stat = $zip->statName($name);
        if ($stat === false || $stat['size'] > self::MAX_XML_BYTES) {
            throw new InvalidArgumentException('admin_chatbot.errors.xlsx_unreadable');
        }

        $xml = $zip->getFromName($name);
        if (! is_string($xml) || $xml === '') {
            throw new InvalidArgumentException('admin_chatbot.errors.xlsx_unreadable');
        }

        return $xml;
    }

    private static function xpath(string $xml): DOMXPath
    {
        $xpath = new DOMXPath(self::dom($xml));
        $xpath->registerNamespace('m', self::MAIN_NS);

        return $xpath;
    }

    private static function dom(string $xml): DOMDocument
    {
        $previous = libxml_use_internal_errors(true);
        try {
            $document = new DOMDocument;
            // No network, no DTDs: a DOCTYPE is refused outright.
            if (! $document->loadXML($xml, LIBXML_NONET | LIBXML_COMPACT | LIBXML_PARSEHUGE) || $document->doctype !== null) {
                throw new InvalidArgumentException('admin_chatbot.errors.xlsx_unreadable');
            }
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }

        return $document;
    }
}
