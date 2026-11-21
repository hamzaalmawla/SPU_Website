<?php

declare(strict_types=1);

namespace App\Services\Shared;

use App\Contracts\Shared\PlainTextFormatterInterface;

/**
 * Turns editor plain text into paragraphs and lists for the public views.
 *
 * Every CMS body, summary and description field is a plain textarea, and the
 * views used to print it inside one <p>, which collapses every line break: a
 * numbered list pasted from Word read as one run-on paragraph. This reads the
 * structure the editor typed instead, so no field needs HTML or a rich editor.
 */
final class PlainTextFormatter implements PlainTextFormatterInterface
{
    /** "1. text", "1) text", "1- text", "١. text"; up to three digits so "2024 - x" stays text. */
    private const ORDERED_LINE = '/^([0-9٠-٩۰-۹]{1,3})\s*([.)\-–])\s+(\S.*)$/u';

    private const UNORDERED_LINE = '/^([•●▪◦·*\-–])\s+(\S.*)$/u';

    /** A numbered marker inside a line: "… السموم. 3. قسم …". */
    private const INLINE_MARKER = '/(?<=^|\s)([0-9٠-٩۰-۹]{1,3})[.)]\s+(?=\S)/u';

    public function blocks(string $text): array
    {
        $text = trim(str_replace(["\r\n", "\r"], "\n", $text));

        if ($text === '') {
            return [];
        }

        if (! str_contains($text, "\n")) {
            $text = $this->splitInlineList($text);
        }

        $blocks = [];
        $paragraph = null;
        $list = null;
        $afterBlank = false;

        foreach (explode("\n", $text) as $line) {
            $line = trim($line);

            if ($line === '') {
                $this->flushParagraph($blocks, $paragraph);
                $afterBlank = true;

                continue;
            }

            $item = $this->listItem($line);

            if ($item !== null) {
                $this->flushParagraph($blocks, $paragraph);

                if ($list === null || $list['type'] !== $item['type']) {
                    $this->flushList($blocks, $list);
                    $list = ['type' => $item['type'], 'items' => []];
                }

                $list['items'][] = ['marker' => $item['marker'], 'lines' => [$item['text']]];
                $afterBlank = false;

                continue;
            }

            // A line straight after a list item, with no blank line between,
            // is that item wrapping onto a second line.
            if ($list !== null && ! $afterBlank) {
                $list['items'][array_key_last($list['items'])]['lines'][] = $line;

                continue;
            }

            $this->flushList($blocks, $list);

            if ($paragraph !== null && ! $afterBlank) {
                $paragraph[] = $line;
            } else {
                $this->flushParagraph($blocks, $paragraph);
                $paragraph = [$line];
            }

            $afterBlank = false;
        }

        $this->flushParagraph($blocks, $paragraph);
        $this->flushList($blocks, $list);

        return $blocks;
    }

    /**
     * Put each item of a one-line numbered list on its own line, when the
     * markers count 1, 2, 3 … in order, so a stray "1." in a sentence is left
     * alone. Bullets ("• a • b") are split the same way.
     */
    private function splitInlineList(string $text): string
    {
        if (preg_match_all(self::INLINE_MARKER, $text, $matches, PREG_OFFSET_CAPTURE) >= 2) {
            $expected = 1;

            foreach ($matches[1] as [$number]) {
                if ($this->toInt($number) !== $expected++) {
                    return $text;
                }
            }

            return trim((string) preg_replace('/\s+(?=(?:[0-9٠-٩۰-۹]{1,3})[.)]\s+\S)/u', "\n", $text));
        }

        if (mb_substr_count($text, '•') >= 2) {
            return trim((string) preg_replace('/\s*•\s*/u', "\n• ", $text));
        }

        return $text;
    }

    /** @return array{type: 'ordered'|'unordered', marker: string, text: string}|null */
    private function listItem(string $line): ?array
    {
        if (preg_match(self::ORDERED_LINE, $line, $match) === 1) {
            return ['type' => 'ordered', 'marker' => $match[1].($match[2] === '–' ? '-' : $match[2]), 'text' => $match[3]];
        }

        if (preg_match(self::UNORDERED_LINE, $line, $match) === 1) {
            return ['type' => 'unordered', 'marker' => '•', 'text' => $match[2]];
        }

        return null;
    }

    /**
     * @param  array<int, array<string, mixed>>  $blocks
     * @param  array<int, string>|null  $paragraph
     *
     * @param-out null $paragraph
     */
    private function flushParagraph(array &$blocks, ?array &$paragraph): void
    {
        if ($paragraph !== null && $paragraph !== []) {
            $blocks[] = ['type' => 'paragraph', 'lines' => $paragraph];
        }

        $paragraph = null;
    }

    /**
     * A lone "1. …" line is a sentence, not a list: it stays a paragraph.
     *
     * @param  array<int, array<string, mixed>>  $blocks
     * @param  array{type: 'ordered'|'unordered', items: array<int, array{marker: string, lines: array<int, string>}>}|null  $list
     *
     * @param-out null $list
     */
    private function flushList(array &$blocks, ?array &$list): void
    {
        if ($list === null) {
            return;
        }

        if (count($list['items']) >= 2) {
            $blocks[] = $list;
        } else {
            $item = $list['items'][0];
            $lines = $item['lines'];
            $lines[0] = $item['marker'].' '.$lines[0];
            $blocks[] = ['type' => 'paragraph', 'lines' => $lines];
        }

        $list = null;
    }

    private function toInt(string $digits): int
    {
        return (int) strtr($digits, [
            '٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4', '٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9',
            '۰' => '0', '۱' => '1', '۲' => '2', '۳' => '3', '۴' => '4', '۵' => '5', '۶' => '6', '۷' => '7', '۸' => '8', '۹' => '9',
        ]);
    }
}
