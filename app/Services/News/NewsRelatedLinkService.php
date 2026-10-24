<?php

declare(strict_types=1);

namespace App\Services\News;

use App\Contracts\News\NewsArticleEventServiceInterface;
use App\Contracts\News\NewsRelatedLinkServiceInterface;
use App\Contracts\Research\ResearchPageServiceInterface;
use App\DTOs\News\NewsRelatedLinkDTO;
use App\Models\News\NewsArticle;
use Illuminate\Database\Eloquent\Builder;

final class NewsRelatedLinkService implements NewsRelatedLinkServiceInterface
{
    public function __construct(
        private readonly NewsArticleEventServiceInterface $articleEvents,
        private readonly ResearchPageServiceInterface $researchPages,
    ) {}

    public function relatedLink(?string $kind, ?int $articleId, ?string $conferenceId, ?string $url, ?string $label, string $locale): ?NewsRelatedLinkDTO
    {
        $label = $this->filled($label);

        if ($kind === 'article' && $articleId !== null) {
            $event = $this->articleEvents->findPublicEvent((string) $articleId, $locale);
            if ($event === null) {
                return null;
            }

            return new NewsRelatedLinkDTO(
                url: $event->registrationUrl ?? '/'.$locale.'/news/'.$event->articleId,
                label: $label ?? __($event->registrationUrl !== null ? 'public.article_event.register' : 'public.related_link.event_details', [], $locale),
                isExternal: false,
            );
        }

        $conferenceId = $this->filled($conferenceId);
        if ($kind === 'conference' && $conferenceId !== null) {
            if (! array_key_exists($conferenceId, $this->conferenceTitles($locale))) {
                return null;
            }
            $registerable = $this->researchPages->findRegisterableConference($conferenceId, $locale) !== null;

            return new NewsRelatedLinkDTO(
                url: $registerable
                    ? '/'.$locale.'/research/conferences/register?event='.rawurlencode($conferenceId)
                    : '/'.$locale.'/research/conferences',
                label: $label ?? __($registerable ? 'public.article_event.register' : 'public.related_link.conference_details', [], $locale),
                isExternal: false,
            );
        }

        $url = $this->filled($url);
        if ($kind === 'url' && $url !== null && $this->isLinkTarget($url)) {
            return new NewsRelatedLinkDTO(
                url: $url,
                label: $label ?? __('public.related_link.details', [], $locale),
                isExternal: ! str_starts_with($url, '/'),
            );
        }

        return null;
    }

    public function forPayload(array $payload, string $locale): ?NewsRelatedLinkDTO
    {
        return $this->relatedLink(
            $this->filled($payload['related_kind'] ?? null),
            is_numeric($payload['related_article_id'] ?? null) ? (int) $payload['related_article_id'] : null,
            $this->filled($payload['related_conference_id'] ?? null),
            $this->filled($payload['related_url'] ?? null),
            $this->filled($payload['cta_label_'.$locale] ?? null),
            $locale,
        );
    }

    public function relatedLinkOptions(string $kind, string $locale, int|string|null $current = null): array
    {
        if ($kind === 'conference') {
            $options = $this->conferenceTitles($locale);
            $current = is_string($current) ? trim($current) : null;
            if ($current !== null && $current !== '' && ! array_key_exists($current, $options)) {
                $options[$current] = $current;
            }

            return $options;
        }

        $articles = NewsArticle::query()
            ->public()
            ->whereHas('category', fn (Builder $query): Builder => $query->whereIn('slug', ['events', 'society-events']))
            ->with('translations')
            ->latest('published_at')
            ->get();
        if (is_numeric($current) && ! $articles->contains(fn (NewsArticle $article): bool => (int) $article->getKey() === (int) $current)) {
            $chosen = NewsArticle::query()->with('translations')->find((int) $current);
            if ($chosen instanceof NewsArticle) {
                $articles->push($chosen);
            }
        }

        $options = [];
        foreach ($articles as $article) {
            $title = $article->translations->firstWhere('locale', $locale)?->title
                ?? $article->translations->firstWhere('locale', 'ar')?->title;
            $options[(int) $article->getKey()] = trim(strip_tags((string) ($title ?? $article->slug)));
        }

        return $options;
    }

    /** @return array<string, string> Published conference IDs with their title, upcoming first. */
    private function conferenceTitles(string $locale): array
    {
        $conferences = $this->researchPages->conferences($locale);
        if (! $conferences->isAvailable) {
            return [];
        }

        $titles = [];
        foreach (['upcoming', 'past'] as $list) {
            foreach (is_array($conferences->data[$list] ?? null) ? $conferences->data[$list] : [] as $conference) {
                $id = is_array($conference) ? trim((string) ($conference['id'] ?? '')) : '';
                $title = is_array($conference) ? trim((string) ($conference['title'] ?? '')) : '';
                if ($id !== '' && $title !== '' && ! isset($titles[$id])) {
                    $titles[$id] = $title;
                }
            }
        }

        return $titles;
    }

    /** A site address ("/en/...") or a full http(s) address. */
    private function isLinkTarget(string $url): bool
    {
        if (str_starts_with($url, '/')) {
            return ! str_starts_with($url, '//');
        }

        return filter_var($url, FILTER_VALIDATE_URL) !== false
            && in_array(strtolower((string) parse_url($url, PHP_URL_SCHEME)), ['http', 'https'], true);
    }

    private function filled(mixed $value): ?string
    {
        return is_string($value) && trim($value) !== '' ? trim($value) : null;
    }
}
