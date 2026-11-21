<?php

declare(strict_types=1);

namespace App\Support\Chatbot;

use Smalot\PdfParser\Page;
use Throwable;

/**
 * Rebuilds a PDF page's text from where each piece is drawn, for Arabic PDFs
 * whose text stream is in visual order.
 *
 * Many exporters (Excel and Word "Save as PDF", report tools) write Arabic
 * glyph by glyph from left to right. Read in stream order every word comes
 * out backwards ("لودجلا" for "الجدول") and table cells run into each other,
 * so neither embeddings nor keywords can match them. Here the pieces are
 * grouped into lines by their baseline, ordered right to left on Arabic lines
 * (left to right otherwise, with numbers and Latin words kept left to right
 * inside Arabic lines), and a wide gap between pieces becomes " | ", the way
 * table cells are written elsewhere in the knowledge base.
 *
 * `bestText()` keeps the parser's own text unless the rebuilt one reads
 * clearly better, judged by `arabicOrderScore()`.
 */
final class PdfLayoutText
{
    /** Pieces whose baselines differ by less than this (PDF units) share a line. */
    private const LINE_TOLERANCE = 2.5;

    private const MAX_PIECES = 60_000;

    /**
     * The page's text: the parser's when it reads in logical order, the
     * position-based rebuild when that scores better.
     */
    public static function bestText(Page $page, string $streamText): string
    {
        if (preg_match('/\p{Arabic}/u', $streamText) !== 1) {
            return $streamText;
        }

        $best = $streamText;
        $clean = DocumentTextExtractor::clean($streamText);
        $bestScore = self::arabicOrderScore($clean);

        // Already in reading order: rebuilding from positions costs several
        // times the parse and gains nothing.
        $words = (int) preg_match_all('/\p{Arabic}{3,}/u', $clean);
        if ($bestScore > 0 && $bestScore * 5 >= $words) {
            return $streamText;
        }

        try {
            $pieces = self::pieces($page);
        } catch (Throwable) {
            return $streamText;
        } finally {
            self::forgetPositions($page);
        }

        if ($pieces === []) {
            return $streamText;
        }

        // Pieces of more than one glyph may themselves be stored right to
        // left (some exporters) or left to right (others); try both.
        foreach ([false, true] as $reverseRuns) {
            $text = self::rebuild($pieces, $reverseRuns);
            $score = self::arabicOrderScore(DocumentTextExtractor::clean($text));
            if ($score > $bestScore) {
                $best = $text;
                $bestScore = $score;
            }
        }

        return $best;
    }

    /**
     * How strongly Arabic text reads in logical order: words that start with
     * the article or end with taa marbuta or alef maqsura count for it, the
     * same letters at the wrong end of a word count against it. Positive for
     * ordinary Arabic, negative when words are reversed, near zero without
     * Arabic.
     */
    public static function arabicOrderScore(string $text): int
    {
        if (preg_match_all('/\p{Arabic}{3,}/u', $text, $matches) === false) {
            return 0;
        }

        $score = 0;
        foreach ($matches[0] as $word) {
            $first = mb_substr($word, 0, 1);
            $last = mb_substr($word, -1);

            if (str_starts_with($word, 'ال')) {
                $score++;
            }
            if ($last === 'ة' || $last === 'ى') {
                $score++;
            }
            if (str_ends_with($word, 'لا')) {
                $score--;
            }
            if ($first === 'ة' || $first === 'ى') {
                $score -= 2;
            }
        }

        return $score;
    }

    /**
     * The parser keeps every page's positioned text on the page object, so a
     * 200-page table would hold all of it at once (well over PHP's 128 MB on
     * the host). Drop it once the page is read.
     */
    private static function forgetPositions(Page $page): void
    {
        try {
            (function (): void {
                $this->dataTm = [];
            })->call($page);
        } catch (Throwable) {
            // A parser version without the cache: nothing to free.
        }
    }

    /**
     * @return list<array{x: float, y: float, text: string}>
     */
    private static function pieces(Page $page): array
    {
        $pieces = [];
        foreach ($page->getDataTm() as $item) {
            if (! is_array($item) || ! isset($item[0], $item[1]) || ! is_array($item[0]) || ! is_string($item[1])) {
                continue;
            }

            $text = str_replace(["\r", "\n", "\t"], ' ', $item[1]);
            if ($text === '') {
                continue;
            }

            $pieces[] = ['x' => (float) ($item[0][4] ?? 0), 'y' => (float) ($item[0][5] ?? 0), 'text' => $text];
            if (count($pieces) >= self::MAX_PIECES) {
                break;
            }
        }

        return $pieces;
    }

    /**
     * @param  list<array{x: float, y: float, text: string}>  $pieces
     */
    private static function rebuild(array $pieces, bool $reverseRuns): string
    {
        usort($pieces, static fn (array $a, array $b): int => $b['y'] <=> $a['y'] ?: $a['x'] <=> $b['x']);

        $lines = [];
        $current = [];
        $lineY = null;
        foreach ($pieces as $piece) {
            if ($lineY !== null && abs($piece['y'] - $lineY) > self::LINE_TOLERANCE) {
                $lines[] = $current;
                $current = [];
            }
            if ($current === []) {
                $lineY = $piece['y'];
            }
            $current[] = $piece;
        }
        if ($current !== []) {
            $lines[] = $current;
        }

        $out = [];
        foreach ($lines as $line) {
            $text = self::line($line, $reverseRuns);
            if ($text !== '') {
                $out[] = $text;
            }
        }

        // Rows under a header line read as "column: value" pairs.
        return implode("\n", TableRows::expandCellLines($out));
    }

    /**
     * @param  list<array{x: float, y: float, text: string}>  $line
     */
    private static function line(array $line, bool $reverseRuns): string
    {
        $joined = implode('', array_column($line, 'text'));
        $arabic = (int) preg_match_all('/\p{Arabic}/u', $joined);
        $latin = (int) preg_match_all('/[A-Za-z]/', $joined);
        $rtl = $arabic > 0 && $arabic >= $latin;

        usort($line, static fn (array $a, array $b): int => $rtl ? $b['x'] <=> $a['x'] : $a['x'] <=> $b['x']);

        if ($rtl) {
            if ($reverseRuns) {
                foreach ($line as $index => $piece) {
                    if (mb_strlen($piece['text']) > 1 && preg_match('/\p{Arabic}/u', $piece['text']) === 1) {
                        $line[$index]['text'] = implode('', array_reverse(mb_str_split($piece['text'])));
                    }
                }
            }
        }

        $charWidth = self::charWidth($line, $rtl);
        if ($rtl) {
            $line = self::keepLeftToRightRuns($line, $charWidth);
        }
        // When the exporter draws its own spaces, a gap is only a cell
        // boundary; guessing word spaces from glyph widths would split words
        // after wide letters.
        $drawsSpaces = str_contains($joined, ' ');
        $text = '';
        $previous = null;
        foreach ($line as $piece) {
            if ($previous !== null && $charWidth > 0) {
                // Space between the two pieces beyond the width the earlier
                // (left-to-right) or later (right-to-left) piece takes up.
                $distance = $rtl ? $previous['x'] - $piece['x'] : $piece['x'] - $previous['x'];
                $width = mb_strlen($rtl ? $piece['text'] : $previous['text']) * $charWidth;
                $gap = $distance - $width;

                if ($gap > 4 * $charWidth) {
                    $text = rtrim($text).' | ';
                } elseif (! $drawsSpaces && $gap > 1.5 * $charWidth && ! str_ends_with($text, ' ') && ! str_starts_with($piece['text'], ' ')) {
                    $text .= ' ';
                }
            }
            $text .= $piece['text'];
            $previous = $piece;
        }

        $text = (string) preg_replace('/ {2,}/', ' ', $text);
        $text = (string) preg_replace('/(?:\s*\|\s*)+/', ' | ', $text);

        return trim($text, " |\t");
    }

    /**
     * On a right-to-left line, digits and Latin words read left to right:
     * put each run of such pieces (with the spaces and symbols between them)
     * back in left-to-right order.
     *
     * A run never crosses a cell gap: two numbers in neighbouring columns
     * stay two numbers.
     *
     * @param  list<array{x: float, y: float, text: string}>  $line
     * @return list<array{x: float, y: float, text: string}>
     */
    private static function keepLeftToRightRuns(array $line, float $charWidth): array
    {
        $isCellGap = static function (array $right, array $left) use ($charWidth): bool {
            return $charWidth > 0 && ($right['x'] - $left['x']) - mb_strlen($left['text']) * $charWidth > 4 * $charWidth;
        };

        $isStrong = static fn (string $text): bool => preg_match('/^[\p{N}A-Za-z]/u', $text) === 1 && preg_match('/\p{Arabic}/u', $text) !== 1;
        $isJoiner = static fn (string $text): bool => preg_match('/^[\s.,:\/%\-+]+$/u', $text) === 1;

        $result = [];
        $count = count($line);
        $index = 0;
        while ($index < $count) {
            if (! $isStrong($line[$index]['text'])) {
                $result[] = $line[$index];
                $index++;

                continue;
            }

            // Extend the run over strong pieces and joiners followed by more
            // strong pieces.
            $end = $index;
            $probe = $index + 1;
            while ($probe < $count) {
                if ($isCellGap($line[$probe - 1], $line[$probe])) {
                    break;
                }
                if ($isStrong($line[$probe]['text'])) {
                    $end = $probe;
                    $probe++;
                } elseif ($isJoiner($line[$probe]['text'])) {
                    $probe++;
                } else {
                    break;
                }
            }

            $run = array_slice($line, $index, $end - $index + 1);
            $texts = array_reverse(array_column($run, 'text'));
            // Keep the positions in right-to-left order so gaps still measure
            // correctly; only the reading order of the texts changes.
            foreach ($run as $offset => $piece) {
                $piece['text'] = $texts[$offset];
                $result[] = $piece;
            }
            $index = $end + 1;
        }

        return $result;
    }

    /**
     * Typical width of one character on the line, from the distances between
     * neighbouring pieces divided by the characters they cover.
     *
     * @param  list<array{x: float, y: float, text: string}>  $line
     */
    private static function charWidth(array $line, bool $rtl): float
    {
        $widths = [];
        for ($index = 1, $count = count($line); $index < $count; $index++) {
            $distance = $rtl ? $line[$index - 1]['x'] - $line[$index]['x'] : $line[$index]['x'] - $line[$index - 1]['x'];
            $length = mb_strlen($rtl ? $line[$index]['text'] : $line[$index - 1]['text']);
            if ($distance > 0 && $length > 0) {
                $widths[] = $distance / $length;
            }
        }

        if ($widths === []) {
            return 0.0;
        }

        sort($widths);

        return (float) $widths[intdiv(count($widths), 2)];
    }
}
