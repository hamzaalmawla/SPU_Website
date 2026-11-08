<?php

declare(strict_types=1);

namespace App\Services\Chatbot;

use App\Contracts\Center\CenterPageServiceInterface;
use App\Contracts\Chatbot\ChatbotDirectoryServiceInterface;
use App\Contracts\Page\AboutPageServiceInterface;
use App\Contracts\Page\FacultyPageServiceInterface;
use App\Contracts\Shared\CacheServiceInterface;
use App\DTOs\Content\PersonDTO;
use App\DTOs\Faculty\FacultyHubCardDTO;
use Throwable;

/**
 * Builds the directory from what the website publishes: the faculties hub,
 * the centers landing and the leadership page, the same data their pages
 * show. It is read again at most every ten minutes, so a new or renamed
 * faculty, or a new president or dean, reaches the assistant soon after it
 * reaches the site.
 */
final class ChatbotDirectoryService implements ChatbotDirectoryServiceInterface
{
    private const CACHE_SECONDS = 600;

    private const SUMMARY_CHARACTERS = 180;

    /** The posts the leadership page shows, in its order. */
    private const LEADERSHIP_CATEGORIES = ['rector', 'vice_president', 'dean'];

    public function __construct(
        private readonly FacultyPageServiceInterface $faculties,
        private readonly CenterPageServiceInterface $centers,
        private readonly AboutPageServiceInterface $about,
        private readonly CacheServiceInterface $cache,
    ) {}

    public function forPrompt(string $locale): string
    {
        $locale = $locale === 'en' ? 'en' : 'ar';

        try {
            /** @var string $text */
            $text = $this->cache->remember('chatbot.directory.v2.'.$locale, fn (): string => $this->build($locale), self::CACHE_SECONDS);

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

        // First, so the prompt's length cap never cuts it.
        $leadership = $this->leadership($locale, $other);
        if ($leadership !== []) {
            $lines[] = '## Current leadership (who holds each post now, from the leadership page /'.$locale.'/about/leadership)';
            array_push($lines, ...$leadership);
            $lines[] = '';
        }

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

        return trim(implode("\n", $lines));
    }

    /**
     * Who holds each post now, as the leadership page shows it. Older pages
     * and files still carry the names of former presidents and deans (as the
     * signature of a circular or a decision), and retrieval finds those for
     * "who is the president?"; this list is what the answer must name.
     *
     * @return list<string>
     */
    private function leadership(string $locale, string $other): array
    {
        $directory = $this->about->getLeadershipDirectory($locale);
        $people = $directory->people
            ->filter(fn (PersonDTO $person): bool => in_array($person->category, self::LEADERSHIP_CATEGORIES, true) && self::oneLine($person->name) !== '')
            ->sortBy(fn (PersonDTO $person): int => (int) array_search($person->category, self::LEADERSHIP_CATEGORIES, true), SORT_REGULAR)
            ->values();
        if ($people->isEmpty()) {
            return [];
        }

        $otherNames = $this->about->getLeadershipProfiles($other)
            ->mapWithKeys(fn (PersonDTO $person): array => [$person->id => self::oneLine($person->name)])
            ->all();
        $facultyNames = collect($directory->facultyFilters)
            ->mapWithKeys(fn (array $filter): array => [$filter['slug'] => self::oneLine($filter['label'])])
            ->all();

        $lines = [];
        foreach ($people as $person) {
            /** @var PersonDTO $person */
            $name = self::oneLine($person->name);
            $otherName = $otherNames[$person->id] ?? '';
            if ($otherName !== '' && $otherName !== $name) {
                $name .= ' ('.$otherName.')';
            }

            $role = self::oneLine(strip_tags($person->role));
            if ($role === '') {
                $role = $this->postName((string) $person->category, $locale);
            }
            // A dean's role on the page can be just "Dean of the faculty".
            $faculty = $person->category === 'dean' && $person->facultySlug !== null ? ($facultyNames[$person->facultySlug] ?? '') : '';
            if ($faculty !== '' && ! str_contains($role, $faculty)) {
                $role .= ' — '.$faculty;
            }

            $lines[] = '- '.$role.': '.$name;
        }

        return $lines;
    }

    private function postName(string $category, string $locale): string
    {
        return match ($category) {
            'rector' => $locale === 'ar' ? 'رئيس الجامعة' : 'University President',
            'vice_president' => $locale === 'ar' ? 'نائب رئيس الجامعة' : 'Vice President',
            default => $locale === 'ar' ? 'عميد كلية' : 'Faculty Dean',
        };
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
