<?php

declare(strict_types=1);

namespace App\Support\Chatbot;

/**
 * Splits extracted text into overlapping passages for retrieval.
 *
 * Passages break at paragraph, then sentence, then word boundaries (never
 * inside a word, in Arabic or English), stay under the target size, and carry
 * the nearest Markdown heading above them plus their PDF page. Each passage
 * repeats the last few lines of the one before it, so a fact that straddles a
 * boundary is still whole in one of them.
 */
final class TextChunker
{
    /**
     * @param  list<array{page: ?int, text: string}>  $sections
     * @return list<array{page: ?int, heading: ?string, content: string}>
     */
    public static function chunk(array $sections, int $size = 1100, int $overlap = 160): array
    {
        $size = max(200, $size);
        $overlap = max(0, min($overlap, intdiv($size, 3)));

        $chunks = [];
        $heading = null;

        foreach ($sections as $section) {
            $buffer = '';
            $bufferHeading = $heading;

            foreach (self::blocks($section['text'], $size) as $block) {
                if (preg_match('/^#{1,6}\s+(.+)$/u', $block, $matches) === 1) {
                    // A heading starts a new passage when the current one is
                    // already substantial, so a passage rarely spans two topics.
                    if (mb_strlen($buffer) > $size * 0.45) {
                        $chunks[] = ['page' => $section['page'], 'heading' => $bufferHeading, 'content' => trim($buffer)];
                        $buffer = '';
                    }
                    $heading = mb_substr(trim($matches[1]), 0, 250);
                    if ($buffer === '') {
                        $bufferHeading = $heading;
                    }
                    $buffer .= ($buffer === '' ? '' : "\n").$heading;

                    continue;
                }

                if ($buffer !== '' && mb_strlen($buffer) + mb_strlen($block) + 1 > $size) {
                    $chunks[] = ['page' => $section['page'], 'heading' => $bufferHeading, 'content' => trim($buffer)];
                    $buffer = self::tail($buffer, $overlap);
                    $bufferHeading = $heading;
                    // A block that fills a passage by itself gets no overlap.
                    if (mb_strlen($buffer) + mb_strlen($block) + 1 > $size) {
                        $buffer = '';
                    }
                }

                $buffer .= ($buffer === '' ? '' : "\n").$block;
            }

            if (trim($buffer) !== '' && ! self::isOnlyOverlap($buffer, $chunks)) {
                $chunks[] = ['page' => $section['page'], 'heading' => $bufferHeading, 'content' => trim($buffer)];
            }
        }

        return array_values(array_filter(
            $chunks,
            static fn (array $chunk): bool => mb_strlen((string) preg_replace('/\s+/u', '', $chunk['content'])) >= 12,
        ));
    }

    /**
     * Paragraphs, with any paragraph longer than the target cut at sentence
     * and then word boundaries.
     *
     * @return list<string>
     */
    private static function blocks(string $text, int $size): array
    {
        $blocks = [];
        foreach (preg_split('/\n+/u', $text) ?: [] as $paragraph) {
            $paragraph = trim($paragraph);
            if ($paragraph === '') {
                continue;
            }

            if (mb_strlen($paragraph) <= $size) {
                $blocks[] = $paragraph;

                continue;
            }

            $sentences = preg_split('/(?<=[.!?؟。;؛])\s+/u', $paragraph) ?: [$paragraph];
            $current = '';
            foreach ($sentences as $sentence) {
                foreach (self::hardWrap($sentence, $size) as $piece) {
                    if ($current !== '' && mb_strlen($current) + mb_strlen($piece) + 1 > $size) {
                        $blocks[] = $current;
                        $current = '';
                    }
                    $current .= ($current === '' ? '' : ' ').$piece;
                }
            }
            if ($current !== '') {
                $blocks[] = $current;
            }
        }

        return $blocks;
    }

    /** @return list<string> */
    private static function hardWrap(string $sentence, int $size): array
    {
        if (mb_strlen($sentence) <= $size) {
            return [$sentence];
        }

        $pieces = [];
        $current = '';
        foreach (preg_split('/\s+/u', $sentence) ?: [] as $word) {
            // A single "word" longer than a passage (a URL, a table run) is cut.
            while (mb_strlen($word) > $size) {
                $pieces[] = mb_substr($word, 0, $size);
                $word = mb_substr($word, $size);
            }
            if ($current !== '' && mb_strlen($current) + mb_strlen($word) + 1 > $size) {
                $pieces[] = $current;
                $current = '';
            }
            $current .= ($current === '' ? '' : ' ').$word;
        }
        if ($current !== '') {
            $pieces[] = $current;
        }

        return $pieces;
    }

    /**
     * The last whole lines (or words) of a passage, up to $overlap characters.
     */
    private static function tail(string $buffer, int $overlap): string
    {
        if ($overlap === 0) {
            return '';
        }

        $tail = '';
        $lines = preg_split('/\n/u', $buffer) ?: [];
        for ($index = count($lines) - 1; $index >= 0; $index--) {
            $candidate = $lines[$index].($tail === '' ? '' : "\n".$tail);
            if (mb_strlen($candidate) > $overlap) {
                break;
            }
            $tail = $candidate;
        }

        if ($tail !== '') {
            return $tail;
        }

        // The last line alone is longer than the overlap: take its last words.
        $words = preg_split('/\s+/u', (string) end($lines)) ?: [];
        $taken = [];
        while ($words !== [] && mb_strlen(implode(' ', $taken)) < $overlap) {
            array_unshift($taken, (string) array_pop($words));
        }

        return implode(' ', $taken);
    }

    /**
     * @param  list<array{page: ?int, heading: ?string, content: string}>  $chunks
     */
    private static function isOnlyOverlap(string $buffer, array $chunks): bool
    {
        $last = end($chunks);

        return $last !== false && str_ends_with($last['content'], trim($buffer));
    }
}
