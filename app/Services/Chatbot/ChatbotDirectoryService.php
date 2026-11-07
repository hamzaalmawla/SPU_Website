<?php

declare(strict_types=1);

namespace App\Services\Chatbot;

use App\Contracts\Center\CenterPageServiceInterface;
use App\Contracts\Chatbot\ChatbotDirectoryServiceInterface;
use App\Contracts\Page\FacultyPageServiceInterface;
use App\Contracts\Shared\CacheServiceInterface;
use App\DTOs\Faculty\FacultyHubCardDTO;
use Throwable;

/**
 * Builds the directory from what the website publishes: the faculties hub
 * and the centers landing, the same data their pages show. It is read again
 * at most every ten minutes, so a new or renamed faculty reaches the
 * assistant soon after it reaches the site.
 */
final class ChatbotDirectoryService implements ChatbotDirectoryServiceInterface
{
    private const CACHE_SECONDS = 600;

    private const SUMMARY_CHARACTERS = 180;

    public function __construct(
        private readonly FacultyPageServiceInterface $faculties,
        private readonly CenterPageServiceInterface $centers,
        private readonly CacheServiceInterface $cache,
    ) {}

    public function forPrompt(string $locale): string
    {
        $locale = $locale === 'en' ? 'en' : 'ar';

        try {
            /** @var string $text */
            $text = $this->cache->remember('chatbot.directory.v1.'.$locale, fn (): string => $this->build($locale), self::CACHE_SECONDS);

            return is_string($text) ? $text : '';
        } catch (Throwable $exception) {
            report($exception);

            return '';
        }
    }

    private function build(string $locale): string
    {
        $other = $locale === 'ar' ? 'en' : 'ar';
        $lines = [];

        $faculties = $this->faculties->getHub($locale)->faculties->values();
        if ($faculties->isNotEmpty()) {
            $otherNames = [];
            foreach ($this->faculties->getHub($other)->faculties as $card) {
                $otherNames[$card->slug] = $card->title;
            }

            $lines[] = '## Faculties ('.$faculties->count().', the complete list)';
            foreach ($faculties as $card) {
                /** @var FacultyHubCardDTO $card */
                $lines[] = $this->line($card->title, $otherNames[$card->slug] ?? null, $card->url, $card->summary);
            }
        }

        $centers = $this->centers->getLanding($locale)->cards;
        if ($centers !== []) {
            $otherNames = [];
            foreach ($this->centers->getLanding($other)->cards as $card) {
                $otherNames[$card['slug']] = $card['title'];
            }

            $lines[] = '';
            $lines[] = '## Centers ('.count($centers).', the complete list)';
            foreach ($centers as $card) {
                $lines[] = $this->line($card['title'], $otherNames[$card['slug']] ?? null, $card['url'], $card['summary']);
            }
        }

        return implode("\n", $lines);
    }

    private function line(string $title, ?string $otherTitle, string $url, string $summary): string
    {
        $name = self::oneLine($title);
        $otherTitle = $otherTitle !== null ? self::oneLine($otherTitle) : '';
        if ($otherTitle !== '' && $otherTitle !== $name) {
            $name .= ' ('.$otherTitle.')';
        }

        $path = parse_url($url, PHP_URL_PATH);
        $line = '- '.$name.': '.(is_string($path) && $path !== '' ? $path : $url);

        $summary = self::oneLine(strip_tags($summary));
        if ($summary !== '') {
            $line .= ' — '.(mb_strlen($summary) > self::SUMMARY_CHARACTERS ? rtrim(mb_substr($summary, 0, self::SUMMARY_CHARACTERS)).'…' : $summary);
        }

        return $line;
    }

    private static function oneLine(string $text): string
    {
        return trim((string) preg_replace('/\s+/u', ' ', $text));
    }
}
