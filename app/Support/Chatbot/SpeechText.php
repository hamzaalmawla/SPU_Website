<?php

declare(strict_types=1);

namespace App\Support\Chatbot;

/**
 * An assistant reply as it should be heard, cut into segments.
 *
 * Replies are Markdown written to be read: bold markers, bullets, tables and
 * links would be spoken as noise. The reply becomes plain sentences (a table
 * row becomes "column: value, column: value."), and is then cut at sentence
 * boundaries into short segments, so the first words can play while the rest
 * is still being made. The cut is deterministic: the browser asks for a
 * segment by number and the server cuts the same reply the same way.
 */
final class SpeechText
{
    /**
     * Plain, speakable sentences.
     */
    public static function fromMarkdown(string $markdown): string
    {
        $text = str_replace(["\r\n", "\r"], "\n", $markdown);
        $text = (string) preg_replace('/```.*?```/su', ' ', $text);
        $text = (string) preg_replace('/`([^`]*)`/u', '$1', $text);
        // Images say nothing; links say their label.
        $text = (string) preg_replace('/!\[[^\]]*\]\([^)]*\)/u', '', $text);
        $text = (string) preg_replace('/\[([^\]]+)\]\([^)]*\)/u', '$1', $text);
        // A bare address is said as its host ("spu.edu.sy").
        $text = (string) preg_replace_callback('#\bhttps?://([^\s/<>()]+)[^\s<>()]*#iu', static function (array $match): string {
            return (string) preg_replace('/^www\./i', '', rtrim($match[1], '.,;:!?،؛'));
        }, $text);

        $lines = [];
        $header = null;
        foreach (explode("\n", $text) as $line) {
            $line = trim($line);
            if ($line === '') {
                $header = null;

                continue;
            }

            if (str_starts_with($line, '|')) {
                $cells = self::cells($line);
                if ($cells === [] || self::isSeparatorRow($cells)) {
                    continue;
                }
                if ($header === null) {
                    $header = $cells;

                    continue;
                }
                $lines[] = self::sentence(self::tableRow($header, $cells));

                continue;
            }
            $header = null;

            $line = (string) preg_replace('/^#{1,6}\s+/u', '', $line);
            $line = (string) preg_replace('/^>\s?/u', '', $line);
            $line = (string) preg_replace('/^[-*+•]\s+/u', '', $line);
            $lines[] = self::sentence($line);
        }

        $text = implode(' ', array_filter($lines, static fn (string $line): bool => $line !== ''));
        $text = (string) preg_replace('/(\*\*|__)(.+?)\1/u', '$2', $text);
        $text = (string) preg_replace('/(?<![\p{L}\p{N}*])\*(?!\s)(.+?)(?<!\s)\*(?![\p{L}\p{N}*])/u', '$1', $text);
        $text = str_replace(['**', '__', '~~'], '', $text);
        // Pictographs and stray markup are not read.
        $text = (string) preg_replace('/[\x{1F000}-\x{1FAFF}\x{2600}-\x{27BF}\x{FE0F}\x{200D}]/u', '', $text);
        $text = (string) preg_replace('/[<>#|]/u', ' ', $text);

        return trim((string) preg_replace('/\s+/u', ' ', $text));
    }

    /**
     * The speakable text cut into segments: the first at most
     * `$firstCharacters` long (so playback starts quickly), the others at
     * most `$characters`, all within `$maxCharacters`.
     *
     * @return list<string>
     */
    public static function segments(string $text, int $firstCharacters = 160, int $characters = 420, int $maxCharacters = 3000): array
    {
        $text = trim($text);
        if ($text === '') {
            return [];
        }

        $pieces = [];
        foreach (self::sentences($text) as $sentence) {
            foreach (self::split($sentence, max(40, $characters)) as $piece) {
                $pieces[] = $piece;
            }
        }

        $segments = [];
        $current = '';
        $total = 0;
        foreach ($pieces as $piece) {
            if ($total + mb_strlen($piece) > $maxCharacters) {
                break;
            }
            $total += mb_strlen($piece) + 1;

            $limit = $segments === [] ? max(40, $firstCharacters) : max(40, $characters);
            if ($current !== '' && mb_strlen($current) + 1 + mb_strlen($piece) > $limit) {
                $segments[] = $current;
                $current = $piece;

                continue;
            }
            $current = $current === '' ? $piece : $current.' '.$piece;
        }
        if ($current !== '') {
            $segments[] = $current;
        }

        // A first sentence longer than the short limit is cut at a pause;
        // the rest of it opens the next segment when it fits there.
        if ($segments !== [] && mb_strlen($segments[0]) > max(40, $firstCharacters)) {
            $head = self::split($segments[0], max(40, $firstCharacters));
            if (count($head) > 1) {
                $rest = trim(implode(' ', array_slice($head, 1)));
                if (isset($segments[1]) && mb_strlen($rest) + 1 + mb_strlen($segments[1]) <= max(40, $characters)) {
                    array_splice($segments, 0, 2, [$head[0], $rest.' '.$segments[1]]);
                } else {
                    array_splice($segments, 0, 1, [$head[0], $rest]);
                }
            }
        }

        return array_values(array_filter($segments, static fn (string $segment): bool => $segment !== ''));
    }

    /**
     * Whether the text is mostly Arabic (it decides how the voice speaks).
     */
    public static function isArabic(string $text): bool
    {
        $arabic = preg_match_all('/[\x{0600}-\x{06FF}\x{0750}-\x{077F}\x{FB50}-\x{FDFF}\x{FE70}-\x{FEFF}]/u', $text);
        $latin = preg_match_all('/[A-Za-z]/u', $text);

        return $arabic > 0 && $arabic >= $latin;
    }

    /**
     * @return list<string>
     */
    private static function sentences(string $text): array
    {
        $parts = preg_split('/(?<=[.!?؟…])\s+/u', $text) ?: [$text];

        return array_values(array_filter(array_map('trim', $parts), static fn (string $part): bool => $part !== ''));
    }

    /**
     * Cut a long sentence at commas, then at spaces, into pieces of at most
     * `$limit` characters.
     *
     * @return list<string>
     */
    private static function split(string $sentence, int $limit): array
    {
        if (mb_strlen($sentence) <= $limit) {
            return [$sentence];
        }

        $pieces = [];
        $current = '';
        foreach (preg_split('/(?<=[,،;؛:])\s+|\s+/u', $sentence) ?: [] as $word) {
            if ($current !== '' && mb_strlen($current) + 1 + mb_strlen($word) > $limit) {
                $pieces[] = $current;
                $current = $word;

                continue;
            }
            $current = $current === '' ? $word : $current.' '.$word;
        }
        if ($current !== '') {
            $pieces[] = $current;
        }

        return $pieces;
    }

    /**
     * @return list<string>
     */
    private static function cells(string $line): array
    {
        $cells = array_map('trim', explode('|', trim($line, " \t|")));

        return array_values(array_filter($cells, static fn (string $cell): bool => $cell !== ''));
    }

    /**
     * @param  list<string>  $cells
     */
    private static function isSeparatorRow(array $cells): bool
    {
        foreach ($cells as $cell) {
            if (preg_match('/^:?-{2,}:?$/u', $cell) !== 1) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param  list<string>  $header
     * @param  list<string>  $cells
     */
    private static function tableRow(array $header, array $cells): string
    {
        $arabic = self::isArabic(implode(' ', $cells).' '.implode(' ', $header));
        if (count($header) !== count($cells)) {
            return implode($arabic ? '، ' : ', ', $cells);
        }

        $pairs = [];
        foreach ($cells as $index => $cell) {
            $pairs[] = $header[$index].': '.$cell;
        }

        return implode($arabic ? '، ' : ', ', $pairs);
    }

    /**
     * A line ends as a sentence, so lines do not run into each other.
     */
    private static function sentence(string $line): string
    {
        $line = trim($line);
        if ($line === '' || preg_match('/[.!?؟…:،,;؛]$/u', $line) === 1) {
            return $line;
        }

        return $line.'.';
    }
}
