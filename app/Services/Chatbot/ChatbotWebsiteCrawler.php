<?php

declare(strict_types=1);

namespace App\Services\Chatbot;

use App\Contracts\Chatbot\ChatbotWebsiteCrawlerInterface;
use App\DTOs\Chatbot\ChatbotWebPageDTO;
use App\Support\Chatbot\HtmlPageText;
use Illuminate\Http\Client\Pool;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Breadth-first crawl of the public site over HTTP, the way a visitor sees
 * it: only published content, in both languages, after every publish rule
 * the pages already enforce. Four pages are fetched at a time.
 */
final class ChatbotWebsiteCrawler implements ChatbotWebsiteCrawlerInterface
{
    public function pages(): Collection
    {
        $base = $this->baseUrl();
        $allow = (string) config('chatbot.website.allow');
        $deny = (string) config('chatbot.website.deny');
        $maxPages = max(1, (int) config('chatbot.website.max_pages', 400));
        $maxDepth = max(0, (int) config('chatbot.website.depth', 2));
        $maxCharacters = max(1000, (int) config('chatbot.website.max_characters', 30000));

        $queue = [];
        foreach (['ar', 'en'] as $locale) {
            foreach ((array) config('chatbot.website.seeds', []) as $seed) {
                $queue[] = ['/'.$locale.($seed !== '' ? '/'.trim((string) $seed, '/') : ''), 0];
            }
        }

        $seen = array_fill_keys(array_column($queue, 0), true);
        $pages = [];
        $texts = [];

        while ($queue !== [] && count($pages) < $maxPages) {
            $batch = array_splice($queue, 0, 4);
            $responses = $this->fetch($base, array_column($batch, 0));

            foreach ($batch as $index => [$path, $depth]) {
                $response = $responses[$index] ?? null;
                if (! $response instanceof Response || ! $response->successful() || ! str_contains((string) $response->header('Content-Type'), 'text/html')) {
                    continue;
                }

                $parsed = HtmlPageText::parse($response->body());
                $text = mb_substr($parsed['text'], 0, $maxCharacters);
                $hash = sha1($text);

                // The same text under two paths (an alias) is kept once.
                if (mb_strlen($text) >= 40 && $parsed['title'] !== '' && ! isset($texts[$hash])) {
                    $texts[$hash] = true;
                    $pages[] = new ChatbotWebPageDTO($path, substr($path, 1, 2), mb_substr($parsed['title'], 0, 200), $text);
                }

                if ($depth >= $maxDepth) {
                    continue;
                }

                foreach ($parsed['links'] as $link) {
                    $next = $this->normalize($link, $base);
                    if ($next !== null && ! isset($seen[$next]) && preg_match($allow, $next) === 1 && preg_match($deny, $next) !== 1) {
                        $seen[$next] = true;
                        $queue[] = [$next, $depth + 1];
                    }
                }
            }
        }

        return collect($pages);
    }

    /**
     * @param  list<string>  $paths
     * @return array<int, Response|null>
     */
    private function fetch(string $base, array $paths): array
    {
        try {
            $responses = Http::pool(fn (Pool $pool): array => array_map(
                fn (string $path) => $pool->withHeaders(['Accept' => 'text/html', 'User-Agent' => 'SPU-Assistant-Indexer/1.0'])
                    ->connectTimeout(5)
                    ->timeout((int) config('chatbot.website.timeout', 20))
                    ->get($base.$path),
                $paths,
            ));
        } catch (Throwable) {
            return [];
        }

        return array_map(static fn (mixed $response): ?Response => $response instanceof Response ? $response : null, $responses);
    }

    private function normalize(string $href, string $base): ?string
    {
        $href = trim($href);
        if ($href === '' || str_starts_with($href, '#') || preg_match('#^(mailto|tel|javascript):#i', $href) === 1) {
            return null;
        }

        $host = parse_url($href, PHP_URL_HOST);
        if (is_string($host) && strcasecmp($host, (string) parse_url($base, PHP_URL_HOST)) !== 0) {
            return null;
        }

        $path = (string) parse_url($href, PHP_URL_PATH);
        if ($path === '' || ! str_starts_with($path, '/')) {
            return null;
        }

        $path = rtrim($path, '/');

        return $path === '' ? null : strtolower($path);
    }

    private function baseUrl(): string
    {
        $base = config('chatbot.website.base_url') ?: config('edge.canonical_url') ?: config('app.url');

        return rtrim((string) $base, '/');
    }
}
