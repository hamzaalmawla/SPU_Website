<?php

declare(strict_types=1);

namespace App\Services\Legacy\Concerns;

use App\Contracts\Legacy\LegacyContentCleaningServiceInterface;
use Illuminate\Support\Str;

trait HandlesPrivateReviewPackets
{
    private function plainText(LegacyContentCleaningServiceInterface $cleaner, mixed $value): ?string
    {
        if (! is_scalar($value)) {
            return null;
        }

        $html = $cleaner->cleanHtml((string) $value)->cleanedValue;
        $text = $cleaner->cleanText($html === null ? null : strip_tags(str_replace(['<br>', '<br/>', '<br />', '</p>', '</div>', '</li>'], "\n", $html)))->cleanedValue;

        return $text !== null ? trim((string) preg_replace('/\s+/u', ' ', $text)) : null;
    }

    private function normalizedReviewText(?string $value): ?string
    {
        return $value === null ? null : Str::lower(trim((string) preg_replace('/\s+/u', ' ', $value)));
    }

    private function containsContactPattern(?string $value): bool
    {
        if ($value === null) {
            return false;
        }

        return preg_match('/\b[A-Z0-9._%+\-]+@[A-Z0-9.\-]+\.[A-Z]{2,}\b/iu', $value) === 1
            || preg_match('/(?<!\d)(?:\+?\d[\s().\-]*){7,}(?!\d)/u', $value) === 1;
    }

    /** @param list<string> $headers @param list<array<string, mixed>> $rows */
    private function reviewCsv(array $headers, array $rows): string
    {
        $stream = fopen('php://temp', 'r+');
        if ($stream === false) {
            return '';
        }
        fputcsv($stream, $headers);
        foreach ($rows as $row) {
            fputcsv($stream, array_map(static fn (string $header): mixed => $row[$header] ?? '', $headers));
        }
        rewind($stream);
        $contents = stream_get_contents($stream);
        fclose($stream);

        return is_string($contents) ? $contents : '';
    }

    /** @param array<string, int> $counts @param list<string> $values */
    private function incrementReviewCounts(array &$counts, array $values): void
    {
        foreach ($values as $value) {
            $counts[$value] = ($counts[$value] ?? 0) + 1;
        }
    }
}
