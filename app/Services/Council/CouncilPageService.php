<?php

declare(strict_types=1);

namespace App\Services\Council;

use App\Contracts\Council\CouncilCatalogServiceInterface;
use App\Contracts\Council\CouncilPageServiceInterface;
use App\Contracts\Council\CouncilStaffServiceInterface;
use App\Contracts\Shared\CacheServiceInterface;
use App\DTOs\Council\CouncilPageDTO;
use App\DTOs\Council\CouncilPersonDTO;
use App\DTOs\Council\CouncilSummaryDTO;
use App\Enums\PublicationStatus;
use App\Models\Cms\CmsTargetContent;
use App\Models\Content\UniversityCouncil;
use Illuminate\Support\Collection;

final class CouncilPageService implements CouncilPageServiceInterface
{
    public function __construct(
        private readonly CouncilCatalogServiceInterface $catalog,
        private readonly CouncilStaffServiceInterface $staff,
        private readonly CacheServiceInterface $cacheService,
    ) {}

    public function councils(string $locale): Collection
    {
        $locale = $this->locale($locale);
        $published = $this->publishedCouncils();

        $staffIds = [];
        foreach ($published as $council) {
            $staffIds = [...$staffIds, ...$this->staffIds($this->members($council['payload'], $locale))];
        }
        $cards = $this->staff->staffCards($staffIds, $locale);

        return collect($published)
            ->map(function (array $council) use ($locale, $cards): ?CouncilSummaryDTO {
                $content = $council['payload']['translations'][$locale];
                $people = $this->people($this->members($council['payload'], $locale), $cards);
                $title = trim((string) ($content['hero']['title'] ?? ''));

                if ($title === '') {
                    return null;
                }

                return new CouncilSummaryDTO(
                    slug: $council['slug'],
                    url: '/'.$locale.'/about/councils/'.$council['slug'],
                    title: $title,
                    summary: trim((string) ($content['hero']['summary'] ?? '')),
                    image: $this->image($content['hero']['backgroundImage'] ?? null),
                    headNames: array_values(array_map(
                        static fn (CouncilPersonDTO $person): string => $person->name,
                        array_filter($people, static fn (CouncilPersonDTO $person): bool => $person->isHead),
                    )),
                    memberCount: count($people),
                    functionCount: count($this->listOfArrays($content['functions']['items'] ?? [])),
                );
            })
            ->filter()
            ->values();
    }

    public function council(string $slug, string $locale): ?CouncilPageDTO
    {
        $locale = $this->locale($locale);

        foreach ($this->publishedCouncils() as $council) {
            if ($council['slug'] === $slug) {
                return $this->page($council['slug'], $council['payload'], $locale);
            }
        }

        return null;
    }

    public function previewCouncil(string $targetKey, array $payload, string $locale): ?CouncilPageDTO
    {
        $id = $this->catalog->councilIdFromTarget($targetKey);
        $slug = $id !== null ? $this->catalog->slugOf($id) : null;
        $locale = $this->locale($locale);

        if ($slug === null || ! is_array($payload['translations'][$locale] ?? null)) {
            return null;
        }

        return $this->page($slug, $payload, $locale);
    }

    public function urlForFormerPath(string $path, string $locale): ?string
    {
        $path = '/'.trim($path, '/');

        foreach ($this->publishedCouncils() as $council) {
            if ($council['formerPath'] === $path) {
                return '/'.$this->locale($locale).'/about/councils/'.$council['slug'];
            }
        }

        return null;
    }

    /** @param array<string, mixed> $payload */
    private function page(string $slug, array $payload, string $locale): ?CouncilPageDTO
    {
        $content = is_array($payload['translations'][$locale] ?? null) ? $payload['translations'][$locale] : null;
        $title = is_array($content) ? trim((string) ($content['hero']['title'] ?? '')) : '';
        if (! is_array($content) || $title === '') {
            return null;
        }

        $members = $this->members($payload, $locale);
        $people = $this->people($members, $this->staff->staffCards($this->staffIds($members), $locale));
        $council = is_array($content['council'] ?? null) ? $content['council'] : [];
        $functions = is_array($content['functions'] ?? null) ? $content['functions'] : [];

        return new CouncilPageDTO(
            locale: $locale,
            direction: $locale === 'ar' ? 'rtl' : 'ltr',
            slug: $slug,
            url: '/'.$locale.'/about/councils/'.$slug,
            title: $title,
            summary: trim((string) ($content['hero']['summary'] ?? '')),
            heroImage: $this->image($content['hero']['backgroundImage'] ?? null),
            membersTitle: trim((string) ($council['title'] ?? '')),
            membersSubtitle: trim((string) ($council['subtitle'] ?? '')),
            headLabel: trim((string) ($council['headLabel'] ?? '')),
            memberLabel: trim((string) ($council['memberLabel'] ?? '')),
            profileLabel: trim((string) ($council['profileLabel'] ?? '')),
            heads: array_values(array_filter($people, static fn (CouncilPersonDTO $person): bool => $person->isHead)),
            members: array_values(array_filter($people, static fn (CouncilPersonDTO $person): bool => ! $person->isHead)),
            functionsTitle: trim((string) ($functions['title'] ?? '')),
            functionsSubtitle: trim((string) ($functions['subtitle'] ?? '')),
            functions: array_values(array_filter(array_map(static fn (array $item): array => [
                'title' => trim((string) ($item['title'] ?? '')),
                'description' => (string) ($item['description'] ?? ''),
            ], $this->listOfArrays($functions['items'] ?? [])), static fn (array $item): bool => $item['title'] !== '')),
        );
    }

    /**
     * Each member as the page shows them. A teaching staff member whose profile
     * is not published is left out; a typed-in member with no name too.
     *
     * @param  list<array<string, mixed>>  $members
     * @param  array<int, array{name: string, rank: string, image: ?string, email: string, url: string, isPublic: bool}>  $cards
     * @return list<CouncilPersonDTO>
     */
    private function people(array $members, array $cards): array
    {
        $people = [];

        foreach ($members as $member) {
            $isHead = ($member['role'] ?? 'member') === 'head';
            $position = trim((string) ($member['position'] ?? ''));

            if (filter_var($member['isStaff'] ?? false, FILTER_VALIDATE_BOOLEAN)) {
                $card = $cards[(int) ($member['staffId'] ?? 0)] ?? null;
                if ($card === null) {
                    continue;
                }

                $people[] = new CouncilPersonDTO($card['name'], $card['rank'], $position, $card['image'], $card['email'], $card['url'], $isHead);

                continue;
            }

            $name = trim((string) ($member['name'] ?? ''));
            if ($name === '') {
                continue;
            }

            $people[] = new CouncilPersonDTO($name, '', $position, $this->image($member['image'] ?? null), '', null, $isHead);
        }

        return $people;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return list<array<string, mixed>>
     */
    private function members(array $payload, string $locale): array
    {
        return $this->listOfArrays($payload['translations'][$locale]['council']['members'] ?? []);
    }

    /**
     * @param  list<array<string, mixed>>  $members
     * @return list<int>
     */
    private function staffIds(array $members): array
    {
        $ids = [];
        foreach ($members as $member) {
            if (filter_var($member['isStaff'] ?? false, FILTER_VALIDATE_BOOLEAN) && is_numeric($member['staffId'] ?? null)) {
                $ids[] = (int) $member['staffId'];
            }
        }

        return $ids;
    }

    /**
     * Every shown council with content published in both languages, in list
     * order. Cached under the `cms` tag, which every publish flushes, and the
     * `councils` tag, which a change of order or visibility flushes.
     *
     * @return list<array{id: int, slug: string, formerPath: ?string, payload: array<string, mixed>}>
     */
    private function publishedCouncils(): array
    {
        $cached = $this->cacheService
            ->tags(['cms', 'councils'])
            ->remember('councils.published', function (): array {
                $councils = UniversityCouncil::query()
                    ->enabled()
                    ->orderBy('sort_order')
                    ->orderBy('id')
                    ->get(['id', 'slug', 'former_path']);
                if ($councils->isEmpty()) {
                    return [];
                }

                $payloads = CmsTargetContent::query()
                    ->whereIn('target_key', $councils->map(fn (UniversityCouncil $council): string => $this->catalog->targetKey((int) $council->getKey()))->all())
                    ->where('status', PublicationStatus::Published->value)
                    ->get(['target_key', 'payload_json'])
                    ->mapWithKeys(static fn (CmsTargetContent $content): array => [(string) $content->target_key => $content->payload_json]);

                $published = [];
                foreach ($councils as $council) {
                    $payload = $payloads[$this->catalog->targetKey((int) $council->getKey())] ?? null;
                    $payload = is_string($payload) ? json_decode($payload, true) : $payload;
                    if (! is_array($payload['translations']['ar'] ?? null) || ! is_array($payload['translations']['en'] ?? null)) {
                        continue;
                    }

                    $published[] = [
                        'id' => (int) $council->getKey(),
                        'slug' => (string) $council->slug,
                        'formerPath' => is_string($council->former_path) && $council->former_path !== '' ? $council->former_path : null,
                        'payload' => $payload,
                    ];
                }

                return $published;
            }, (int) config('cache.public_page_ttl', 3600));

        return is_array($cached) ? array_values($cached) : [];
    }

    private function image(mixed $value): ?string
    {
        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        $value = trim($value);
        if (preg_match('#^https?://#i', $value) === 1) {
            return $value;
        }

        // A media path; never a protocol-relative or other-scheme address.
        return str_starts_with($value, '//') || str_contains($value, ':') ? null : '/'.ltrim($value, '/');
    }

    private function locale(string $locale): string
    {
        return $locale === 'ar' ? 'ar' : 'en';
    }

    /** @return list<array<string, mixed>> */
    private function listOfArrays(mixed $items): array
    {
        return is_array($items) ? array_values(array_filter($items, 'is_array')) : [];
    }
}
