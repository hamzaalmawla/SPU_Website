<?php

declare(strict_types=1);

namespace App\Support\Chatbot;

/**
 * Turns table rows into lines that keep their meaning on their own.
 *
 * A table row "| Medicine | 230 |" says nothing once a passage boundary cuts
 * it from its header. Each row becomes "Faculty: Medicine ; Minimum: 230"
 * instead (the header line stays once, for context), the same shape CSV and
 * Excel rows get. Doing this here rather than asking the vision model to
 * repeat the column names halves what the model writes for a scanned table,
 * which is most of what reading a scan costs.
 */
final class TableRows
{
    /**
     * Markdown tables ("| a | b |" lines, optional "|---|" separator) in
     * `$text`, rows expanded. Other lines are left as they are.
     */
    public static function expandMarkdown(string $text): string
    {
        $lines = explode("\n", $text);
        $out = [];
        $header = null;

        foreach ($lines as $line) {
            $trimmed = trim($line);
            if (! str_starts_with($trimmed, '|')) {
                $header = null;
                $out[] = $line;

                continue;
            }

            $cells = self::cells($trimmed);
            if ($header === null) {
                $header = $cells;
                $out[] = '| '.implode(' | ', $cells).' |';

                continue;
            }

            if (self::isSeparator($cells)) {
                continue;
            }

            $out[] = self::pairs($header, $cells);
        }

        return implode("\n", $out);
    }

    /**
     * Rows already split into cells ("a | b | c", as PdfLayoutText writes
     * them): from the first row of `$minCells` or more cells that holds no
     * number, rows with the same number of cells are expanded under it.
     *
     * @param  list<string>  $lines
     * @return list<string>
     */
    public static function expandCellLines(array $lines, int $minCells = 3): array
    {
        $header = null;
        $out = [];

        foreach ($lines as $line) {
            $cells = array_map('trim', explode(' | ', $line));
            $count = count($cells);

            if ($header !== null && $count === count($header)) {
                if ($cells === $header) {
                    // The header repeated on every page.
                    continue;
                }
                $out[] = self::pairs($header, $cells);

                continue;
            }

            if ($count >= $minCells && ! self::hasNumber($cells)) {
                $header = $cells;
            }
            $out[] = $line;
        }

        return $out;
    }

    /**
     * @param  list<string>  $header
     * @param  list<string>  $cells
     */
    private static function pairs(array $header, array $cells): string
    {
        $pairs = [];
        foreach ($cells as $index => $cell) {
            if ($cell === '' || $cell === '-') {
                continue;
            }
            $label = $header[$index] ?? '';
            $pairs[] = $label !== '' && $label !== $cell ? $label.': '.$cell : $cell;
        }

        return implode(' ; ', $pairs);
    }

    /**
     * @return list<string>
     */
    private static function cells(string $line): array
    {
        $line = trim($line);
        $line = (string) preg_replace('/^\||\|$/u', '', $line);

        return array_map(static fn (string $cell): string => trim((string) preg_replace('/\s+/u', ' ', $cell)), explode('|', $line));
    }

    /**
     * @param  list<string>  $cells
     */
    private static function isSeparator(array $cells): bool
    {
        foreach ($cells as $cell) {
            if ($cell !== '' && preg_match('/^:?-{2,}:?$/', $cell) !== 1) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param  list<string>  $cells
     */
    private static function hasNumber(array $cells): bool
    {
        foreach ($cells as $cell) {
            if (preg_match('/^[\d.,%\s\/-]+$/u', $cell) === 1 && preg_match('/\d/', $cell) === 1) {
                return true;
            }
        }

        return false;
    }
}
