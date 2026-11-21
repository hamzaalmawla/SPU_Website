<?php

declare(strict_types=1);

namespace App\Contracts\Shared;

/**
 * Reads the structure editors type or paste into plain-text fields.
 */
interface PlainTextFormatterInterface
{
    /**
     * Split plain text into paragraphs and lists.
     *
     * Blank lines separate paragraphs, single line breaks stay line breaks,
     * and runs of "1." / "1-" / "•" lines become lists. A numbered list pasted
     * as one line ("1. … 2. … 3. …") is split back into its items. The text
     * is returned as written, unescaped; the view escapes it.
     *
     * @return array<int, array{type: 'paragraph', lines: array<int, string>}|array{type: 'ordered'|'unordered', items: array<int, array{marker: string, lines: array<int, string>}>}>
     */
    public function blocks(string $text): array;
}
