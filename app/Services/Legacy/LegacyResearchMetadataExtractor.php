<?php

declare(strict_types=1);

namespace App\Services\Legacy;

use App\Contracts\Legacy\LegacyResearchMetadataExtractorInterface;
use App\DTOs\Legacy\LegacyResearchMetadataDTO;

final class LegacyResearchMetadataExtractor implements LegacyResearchMetadataExtractorInterface
{
    /** @var array<string, list<string>> */
    private const LABELS = [
        'authors' => ['author', 'authors', 'المؤلف', 'المؤلفون', 'الباحث', 'الباحثون'],
        'citation' => ['published in', 'publication', 'journal', 'منشور في', 'نشرت في', 'المجلة'],
        'abstract' => ['abstract', 'summary', 'الملخص', 'ملخص'],
        'keywords' => ['keywords', 'key words', 'الكلمات المفتاحية', 'كلمات مفتاحية'],
        'stop' => ['to read the article', 'read the article', 'download', 'copyright', 'لقراءة البحث', 'تحميل البحث'],
    ];

    public function extract(?string $html, ?string $explicitKeywords, ?string $title): LegacyResearchMetadataDTO
    {
        $sections = $this->sections($html);
        $authors = $this->cleanSection($sections['authors'] ?? null, 2000);
        $citation = $this->cleanSection($sections['citation'] ?? null, 3000);
        $abstract = $this->cleanSection($sections['abstract'] ?? null, 30000);
        $keywords = $this->keywords($explicitKeywords, $sections['keywords'] ?? null);
        $doi = $this->doi(implode("\n", array_filter([$title, $html, $citation])));
        $publicationYear = $this->publicationYear($citation);
        $journalRank = $this->journalRank(implode(' ', array_filter([$citation, $html])));
        $publisher = $this->publisher($citation);
        $evidence = [];

        foreach ([
            'authors_section' => $authors,
            'citation_section' => $citation,
            'abstract_section' => $abstract,
            'explicit_or_section_keywords' => $keywords !== [] ? 'present' : null,
            'validated_doi' => $doi,
            'citation_year' => $publicationYear,
            'explicit_journal_rank' => $journalRank,
            'clean_citation_publisher' => $publisher,
        ] as $label => $value) {
            if ($value !== null && $value !== '') {
                $evidence[] = $label;
            }
        }

        return new LegacyResearchMetadataDTO(
            authors: $authors,
            citation: $citation,
            abstract: $abstract,
            publisher: $publisher,
            doi: $doi,
            publicationYear: $publicationYear,
            journalRank: $journalRank,
            keywords: $keywords,
            evidence: $evidence,
        );
    }

    /** @return array<string, string> */
    private function sections(?string $html): array
    {
        if ($html === null || trim($html) === '') {
            return [];
        }

        $withLines = preg_replace('/<\s*(br|\/p|\/div|\/li|\/h[1-6]|\/tr)\b[^>]*>/iu', "\n", $html) ?? $html;
        $text = html_entity_decode(strip_tags($withLines), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $lines = array_values(array_filter(array_map(
            static fn (string $line): string => trim((string) preg_replace('/\s+/u', ' ', $line)),
            preg_split('/\R/u', $text) ?: [],
        ), static fn (string $line): bool => $line !== ''));
        $sections = [];
        $active = null;

        foreach ($lines as $line) {
            [$section, $inline] = $this->sectionLabel($line);
            if ($section !== null) {
                $active = $section === 'stop' ? null : $section;
                if ($active !== null && $inline !== null) {
                    $sections[$active][] = $inline;
                }

                continue;
            }

            if ($active !== null) {
                $sections[$active][] = $line;
            }
        }

        return array_map(static fn (array $values): string => implode("\n", $values), $sections);
    }

    /** @return array{0: ?string, 1: ?string} */
    private function sectionLabel(string $line): array
    {
        $normalized = mb_strtolower(trim($line, " \t\n\r\0\x0B:：-–—"));

        foreach (self::LABELS as $section => $labels) {
            foreach ($labels as $label) {
                if ($normalized === $label) {
                    return [$section, null];
                }
                if (preg_match('/^'.preg_quote($label, '/').'\s*[:：-]\s*(.+)$/iu', $line, $matches) === 1) {
                    return [$section, trim($matches[1])];
                }
            }
        }

        return [null, null];
    }

    private function cleanSection(?string $value, int $maxLength): ?string
    {
        $value = trim((string) preg_replace('/^["\'>\s]+/u', '', (string) $value));
        if ($value === '' || mb_strlen($value) > $maxLength) {
            return null;
        }

        return $value;
    }

    /** @return list<string> */
    private function keywords(?string $explicit, ?string $section): array
    {
        $source = trim((string) $explicit) !== '' ? $explicit : $section;
        if ($source === null) {
            return [];
        }

        return array_values(array_unique(array_filter(array_map(
            static fn (string $keyword): string => trim((string) preg_replace('/\s+/u', ' ', $keyword)),
            preg_split('/(?:[,،;؛|]+|\R+)/u', html_entity_decode(strip_tags($source), ENT_QUOTES | ENT_HTML5, 'UTF-8')) ?: [],
        ), static fn (string $keyword): bool => $keyword !== '' && mb_strlen($keyword) <= 160)));
    }

    private function doi(string $value): ?string
    {
        if (preg_match('/\b10\.\d{4,9}\/[-._;()\/:a-z0-9]+/iu', $value, $matches) !== 1) {
            return null;
        }

        return rtrim($matches[0], '.,;:/)]}');
    }

    private function publicationYear(?string $citation): ?int
    {
        if ($citation === null) {
            return null;
        }

        preg_match_all('/\b(?:19|20)\d{2}\b/', $citation, $matches);
        $years = array_values(array_filter(array_map('intval', $matches[0] ?? []), static fn (int $year): bool => $year <= ((int) date('Y') + 1)));

        return $years === [] ? null : max($years);
    }

    private function journalRank(string $value): ?string
    {
        return preg_match('/\bQ([1-4])\b/i', $value, $matches) === 1 ? 'Q'.$matches[1] : null;
    }

    private function publisher(?string $citation): ?string
    {
        if ($citation === null || preg_match('/copyright|license|received|revised|accepted|©/iu', $citation) === 1) {
            return null;
        }

        $firstLine = trim(explode("\n", $citation)[0]);
        $candidate = rtrim(
            trim((string) preg_replace('/\s*[,،-]?\s*\b(?:19|20)\d{2}\b.*$/u', '', $firstLine)),
            " \t\n\r\0\x0B,;:-–—(",
        );

        return mb_strlen($candidate) >= 3 && mb_strlen($candidate) <= 255 ? $candidate : null;
    }
}
