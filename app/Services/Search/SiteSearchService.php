<?php

declare(strict_types=1);

namespace App\Services\Search;

use App\Contracts\Search\SiteSearchServiceInterface;
use App\Contracts\Shared\CacheServiceInterface;
use App\DTOs\Search\SearchResultDTO;
use App\DTOs\Search\SearchResultsDTO;
use App\Models\Search\SearchDocument;
use App\Support\SearchTextNormalizer;
use BadMethodCallException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Public site-wide content search.
 *
 * Matching runs against App\Models\Search\SearchDocument, a flat index whose
 * text was already folded by SearchTextNormalizer at write time. That is what
 * makes the whole feature portable: the only SQL involved is LIKE with an
 * explicit ESCAPE, which behaves identically on sqlite (tests) and MariaDB
 * 10.11 (production). No FULLTEXT, no MATCH...AGAINST, no collation dependency.
 *
 * Cost is bounded and flat regardless of how much matches:
 *
 *  - one indexed scan, hard-limited to MAX_MATCHES rows, on a cache miss
 *  - one keyed fetch of the current page's rows
 *  - nothing else; no model hydration, because the index already stores the
 *    display title, URL, summary and meta line
 *
 * Only the ordered list of matching document ids is cached, under a hashed key,
 * so a flood of distinct queries cannot grow the cache without bound.
 */
final class SiteSearchService implements SiteSearchServiceInterface
{
    /**
     * Hard ceiling on scanned matches. A two-character query can match most of
     * the corpus; ranking a few hundred rows in PHP is cheap, ranking thousands
     * on a five-worker server is not. The SQL ordering puts title matches first
     * so the best results survive the cut.
     */
    private const MAX_MATCHES = 300;

    /** Terms beyond this are ignored; each one costs another LIKE pass. */
    private const MAX_TERMS = 6;

    private const CACHE_TTL_SECONDS = 300;

    /** Characters of context kept before and after the first match. */
    private const SNIPPET_LEAD = 40;

    private const SNIPPET_LENGTH = 240;

    public function __construct(
        private readonly CacheServiceInterface $cacheService,
    ) {}

    public function search(string $locale, string $query, string $type = 'all', int $page = 1, int $perPage = 10): SearchResultsDTO
    {
        $type = in_array($type, self::TYPES, true) ? $type : 'all';
        $page = max(1, $page);
        $perPage = max(1, min($perPage, 50));

        // Cap before normalizing so a megabyte of pasted text never reaches the
        // normalizer, let alone the database.
        $rawQuery = trim($query);
        $rawQuery = mb_substr($rawQuery, 0, self::MAX_QUERY_LENGTH);
        $normalizedQuery = SearchTextNormalizer::normalize($rawQuery);
        $terms = array_slice(SearchTextNormalizer::terms($rawQuery), 0, self::MAX_TERMS);

        $hasQuery = $rawQuery !== '';
        $tooShort = $hasQuery && mb_strlen($normalizedQuery) < self::MIN_QUERY_LENGTH;

        if (! $hasQuery || $tooShort || $terms === []) {
            return $this->emptyResults($locale, $rawQuery, $type, $page, $perPage, $hasQuery, $tooShort);
        }

        $matches = $this->matchingDocuments($locale, $normalizedQuery, $terms);
        $typeCounts = $this->countByType($matches['results']);

        $filtered = $type === 'all'
            ? $matches['results']
            : array_values(array_filter($matches['results'], static fn (array $result): bool => $result['type'] === $type));

        $total = count($filtered);
        $lastPage = max(1, (int) ceil($total / $perPage));
        $page = min($page, $lastPage);
        $pageIds = array_column(array_slice($filtered, ($page - 1) * $perPage, $perPage), 'id');

        return new SearchResultsDTO(
            locale: $locale,
            query: $rawQuery,
            type: $type,
            items: $this->hydrate($pageIds, $terms),
            typeCounts: $typeCounts,
            total: $total,
            currentPage: $page,
            perPage: $perPage,
            lastPage: $lastPage,
            hasQuery: true,
            queryTooShort: false,
            resultsCapped: $matches['capped'],
        );
    }

    // ── Matching and ranking ─────────────────────────────────────────────────

    /**
     * The ranked id list for one (locale, query), cached under a hashed key.
     *
     * Only ids and their type are stored, never rendered rows, so one cache
     * entry stays a few kilobytes however large the corpus grows. Type filtering
     * and pagination then slice this list for free, which is why switching the
     * filter or paging through results costs a single keyed fetch and no scan.
     *
     * @param  list<string>  $terms
     * @return array{results: list<array{id: int, type: string}>, capped: bool}
     */
    private function matchingDocuments(string $locale, string $normalizedQuery, array $terms): array
    {
        $cacheKey = 'search:results:'.sha1($locale.'|'.$normalizedQuery);

        try {
            $cached = $this->cacheService
                ->tags(['search', 'public-pages'])
                ->remember(
                    $cacheKey,
                    fn (): array => $this->rankedMatches($locale, $normalizedQuery, $terms),
                    self::CACHE_TTL_SECONDS,
                );
        } catch (BadMethodCallException) {
            $cached = $this->rankedMatches($locale, $normalizedQuery, $terms);
        }

        if (! is_array($cached) || ! isset($cached['results']) || ! is_array($cached['results'])) {
            return ['results' => [], 'capped' => false];
        }

        return [
            'results' => array_values($cached['results']),
            'capped' => (bool) ($cached['capped'] ?? false),
        ];
    }

    /**
     * @param  list<string>  $terms
     * @return array{results: list<array{id: int, type: string}>, capped: bool}
     */
    private function rankedMatches(string $locale, string $normalizedQuery, array $terms): array
    {
        $rows = SearchDocument::query()
            ->where('locale', $locale)
            ->where(function (Builder $query) use ($terms): void {
                // Every term must appear somewhere in the document. body_normalized
                // already contains the title, so one column covers both.
                foreach ($terms as $term) {
                    $query->whereRaw(
                        "body_normalized LIKE ? ESCAPE '!'",
                        ['%'.SearchTextNormalizer::escapeLike($term).'%'],
                    );
                }
            })
            ->orderByRaw(
                "CASE WHEN title_normalized LIKE ? ESCAPE '!' THEN 0 ELSE 1 END",
                ['%'.SearchTextNormalizer::escapeLike($normalizedQuery).'%'],
            )
            ->orderByDesc('weight')
            ->orderByDesc('published_at')
            ->orderByDesc('id')
            ->limit(self::MAX_MATCHES + 1)
            ->get(['id', 'type', 'title_normalized', 'published_at', 'weight']);

        $capped = $rows->count() > self::MAX_MATCHES;
        $rows = $rows->take(self::MAX_MATCHES);

        $scored = $rows
            ->map(function (SearchDocument $document) use ($normalizedQuery, $terms): array {
                return [
                    'id' => (int) $document->getKey(),
                    'type' => (string) $document->type,
                    'score' => $this->score((string) $document->title_normalized, (int) $document->weight, $normalizedQuery, $terms),
                    'published_at' => $document->published_at?->getTimestamp() ?? 0,
                ];
            })
            ->all();

        usort($scored, static function (array $a, array $b): int {
            return [$b['score'], $b['published_at'], $a['id']] <=> [$a['score'], $a['published_at'], $b['id']];
        });

        return [
            'results' => array_map(
                static fn (array $result): array => ['id' => $result['id'], 'type' => $result['type']],
                $scored,
            ),
            'capped' => $capped,
        ];
    }

    /**
     * Relevance, strongest signal first: an exact title, then a title that
     * starts with the query, then a title containing the whole phrase, then the
     * individual terms — worth much more in the title than in the body.
     *
     * `weight` only ever breaks ties between otherwise equal documents.
     *
     * @param  list<string>  $terms
     */
    private function score(string $title, int $weight, string $normalizedQuery, array $terms): int
    {
        $score = 0;

        if ($title === $normalizedQuery) {
            $score += 1000;
        } elseif (str_starts_with($title, $normalizedQuery)) {
            $score += 600;
        } elseif (str_contains($title, $normalizedQuery)) {
            $score += 400;
        }

        foreach ($terms as $term) {
            if (str_starts_with($title, $term)) {
                $score += 90;
            } elseif (str_contains($title, $term)) {
                $score += 60;
            } else {
                // The row matched, so an unmatched title means a body match.
                $score += 10;
            }
        }

        return $score + $weight;
    }

    /**
     * @param  list<array{id: int, type: string}>  $results
     * @return array<string, int>
     */
    private function countByType(array $results): array
    {
        $counts = ['all' => count($results)];

        foreach (self::TYPES as $type) {
            if ($type !== 'all') {
                $counts[$type] = 0;
            }
        }

        foreach ($results as $result) {
            if (array_key_exists($result['type'], $counts)) {
                $counts[$result['type']]++;
            }
        }

        return $counts;
    }

    // ── Result hydration ─────────────────────────────────────────────────────

    /**
     * @param  list<int>  $ids
     * @param  list<string>  $terms
     * @return Collection<int, SearchResultDTO>
     */
    private function hydrate(array $ids, array $terms): Collection
    {
        if ($ids === []) {
            return new Collection;
        }

        $documents = SearchDocument::query()
            ->whereIn('id', $ids)
            ->get(['id', 'type', 'title', 'summary', 'url', 'meta', 'published_at'])
            ->keyBy(fn (SearchDocument $document): int => (int) $document->getKey());

        $results = new Collection;

        // Preserve the ranked order; whereIn returns rows in storage order.
        foreach ($ids as $id) {
            $document = $documents->get($id);

            if (! $document instanceof SearchDocument) {
                continue;
            }

            $results->push(new SearchResultDTO(
                type: (string) $document->type,
                id: (string) $document->getKey(),
                title: (string) $document->title,
                url: (string) $document->url,
                snippet: $this->snippet((string) ($document->summary ?? ''), $terms),
                meta: $document->meta === null ? null : (string) $document->meta,
                publishedAt: $document->published_at?->toDateString(),
            ));
        }

        return $results;
    }

    // ── Snippets ─────────────────────────────────────────────────────────────

    /**
     * Build a match snippet as pre-split segments.
     *
     * The stored summary is original text, but the terms are folded, so a match
     * cannot be located by a plain string search: "احمد" has to light up the
     * "أَحْمَد" that is actually on the page. SearchTextNormalizer records, for
     * every folded character, which original character produced it, which is
     * what lets a match found in folded space be painted back onto the original.
     *
     * Segments are returned rather than HTML so the view escapes every piece.
     *
     * @param  list<string>  $terms
     * @return list<array{text: string, highlighted: bool}>
     */
    private function snippet(string $summary, array $terms): array
    {
        $summary = trim($summary);

        if ($summary === '') {
            return [];
        }

        $folded = SearchTextNormalizer::normalizeWithOffsets($summary);
        $characters = $folded['characters'];
        $ranges = $this->matchRanges($folded['normalized'], $folded['offsets'], $terms);

        $total = count($characters);
        $start = 0;

        if ($ranges !== []) {
            $start = max(0, $ranges[0][0] - self::SNIPPET_LEAD);
        }

        $end = min($total, $start + self::SNIPPET_LENGTH);

        // Avoid a snippet that stops mid-word when the tail would have fit.
        if ($total - $end < 24) {
            $end = $total;
        }

        $segments = [];

        if ($start > 0) {
            $segments[] = ['text' => '… ', 'highlighted' => false];
        }

        $cursor = $start;

        foreach ($ranges as [$rangeStart, $rangeEnd]) {
            if ($rangeEnd < $cursor || $rangeStart >= $end) {
                continue;
            }

            $rangeStart = max($rangeStart, $cursor);
            $rangeEnd = min($rangeEnd, $end - 1);

            if ($rangeStart > $cursor) {
                $segments[] = [
                    'text' => implode('', array_slice($characters, $cursor, $rangeStart - $cursor)),
                    'highlighted' => false,
                ];
            }

            $segments[] = [
                'text' => implode('', array_slice($characters, $rangeStart, $rangeEnd - $rangeStart + 1)),
                'highlighted' => true,
            ];

            $cursor = $rangeEnd + 1;
        }

        if ($cursor < $end) {
            $segments[] = [
                'text' => implode('', array_slice($characters, $cursor, $end - $cursor)),
                'highlighted' => false,
            ];
        }

        if ($end < $total) {
            $segments[] = ['text' => ' …', 'highlighted' => false];
        }

        return array_values(array_filter($segments, static fn (array $segment): bool => $segment['text'] !== ''));
    }

    /**
     * Locate every term in the folded text and translate each hit back into
     * original-character coordinates, merged so overlapping hits paint once.
     *
     * @param  list<int>  $offsets
     * @param  list<string>  $terms
     * @return list<array{0: int, 1: int}>
     */
    private function matchRanges(string $normalized, array $offsets, array $terms): array
    {
        $ranges = [];
        $normalizedLength = mb_strlen($normalized);

        foreach ($terms as $term) {
            $termLength = mb_strlen($term);

            if ($termLength === 0) {
                continue;
            }

            $position = 0;
            $found = 0;

            while ($found < 12) {
                $index = mb_strpos($normalized, $term, $position);

                if ($index === false || $index >= $normalizedLength) {
                    break;
                }

                $lastIndex = $index + $termLength - 1;

                if (isset($offsets[$index], $offsets[$lastIndex])) {
                    $ranges[] = [$offsets[$index], $offsets[$lastIndex]];
                }

                $position = $index + $termLength;
                $found++;
            }
        }

        if ($ranges === []) {
            return [];
        }

        usort($ranges, static fn (array $a, array $b): int => $a[0] <=> $b[0]);

        $merged = [];

        foreach ($ranges as $range) {
            $last = count($merged) - 1;

            if ($last >= 0 && $range[0] <= $merged[$last][1] + 1) {
                $merged[$last][1] = max($merged[$last][1], $range[1]);

                continue;
            }

            $merged[] = $range;
        }

        return $merged;
    }

    // ── Empty result ─────────────────────────────────────────────────────────

    private function emptyResults(
        string $locale,
        string $query,
        string $type,
        int $page,
        int $perPage,
        bool $hasQuery,
        bool $tooShort,
    ): SearchResultsDTO {
        $counts = ['all' => 0];

        foreach (self::TYPES as $candidate) {
            if ($candidate !== 'all') {
                $counts[$candidate] = 0;
            }
        }

        return new SearchResultsDTO(
            locale: $locale,
            query: $query,
            type: $type,
            items: new Collection,
            typeCounts: $counts,
            total: 0,
            currentPage: max(1, $page),
            perPage: $perPage,
            lastPage: 1,
            hasQuery: $hasQuery,
            queryTooShort: $tooShort,
            resultsCapped: false,
        );
    }
}
