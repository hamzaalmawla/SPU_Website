<?php

declare(strict_types=1);

namespace App\Services\Page;

use App\Contracts\Cms\CmsTargetRegistryInterface;
use App\Contracts\Page\AboutNavigationCardServiceInterface;
use App\Contracts\Shared\CacheServiceInterface;
use App\DTOs\About\AboutNavigationCardDTO;
use App\DTOs\Cms\CmsTargetDTO;
use App\Models\Page\AboutNavigationCard;
use Illuminate\Support\Collection;

final class AboutNavigationCardService implements AboutNavigationCardServiceInterface
{
    public function __construct(
        private readonly CmsTargetRegistryInterface $targetRegistry,
        private readonly CacheServiceInterface $cacheService,
    ) {}

    /** @return array<string, string> */
    public function availableTargetOptions(): array
    {
        $existingKeys = AboutNavigationCard::query()->pluck('target_key')->all();

        return $this->targetRegistry->forArea('about')
            ->filter(fn (CmsTargetDTO $target): bool => $target->key !== 'about.landing'
                && $target->publicPath !== null
                && ! in_array($target->key, $existingKeys, true))
            ->mapWithKeys(fn (CmsTargetDTO $target): array => [
                $target->key => __($target->labelKey).' ('.$target->key.')',
            ])
            ->all();
    }

    /** @return array<int, array<string, string>> */
    public function getVisibleCards(string $locale): array
    {
        $hasAnyCards = AboutNavigationCard::query()->exists();

        if (! $hasAnyCards) {
            return $this->fallbackFromRegistry($locale);
        }

        $cards = AboutNavigationCard::query()
            ->where('is_visible', true)
            ->where('status', 'published')
            ->whereNotNull('published_at')
            ->where(function ($query): void {
                $query->whereNull('publish_at')->orWhere('publish_at', '<=', now());
            })
            ->orderBy('sort_order')
            ->get();

        return $cards
            ->map(function (AboutNavigationCard $card) use ($locale): ?array {
                $target = $this->targetRegistry->find($card->target_key);

                if (! $target instanceof CmsTargetDTO || $target->publicPath === null) {
                    return null;
                }

                $title = $locale === 'ar'
                    ? ($card->title_override_ar ?? __($target->labelKey, [], 'ar'))
                    : ($card->title_override_en ?? __($target->labelKey, [], 'en'));

                return [
                    'target_key' => $card->target_key,
                    'title' => $title,
                    'link' => $target->publicPath,
                ];
            })
            ->filter()
            ->values()
            ->all();
    }

    /** @return Collection<int, AboutNavigationCardDTO> */
    public function getAllCards(): Collection
    {
        return AboutNavigationCard::query()
            ->orderBy('sort_order')
            ->get()
            ->map(function (AboutNavigationCard $card): AboutNavigationCardDTO {
                $target = $this->targetRegistry->find($card->target_key);

                return new AboutNavigationCardDTO(
                    id: (int) $card->getKey(),
                    targetKey: $card->target_key,
                    titleOverrideAr: $card->title_override_ar,
                    titleOverrideEn: $card->title_override_en,
                    sortOrder: (int) $card->sort_order,
                    isVisible: (bool) $card->is_visible,
                    status: $card->status,
                    publishAt: $card->publish_at?->toDateTimeString(),
                    publishedAt: $card->published_at?->toDateTimeString(),
                    resolvedTitleAr: $card->title_override_ar ?? ($target instanceof CmsTargetDTO ? __($target->labelKey, [], 'ar') : $card->target_key),
                    resolvedTitleEn: $card->title_override_en ?? ($target instanceof CmsTargetDTO ? __($target->labelKey, [], 'en') : $card->target_key),
                    publicPath: $target instanceof CmsTargetDTO ? $target->publicPath : null,
                );
            });
    }

    public function createCard(
        string $targetKey,
        ?string $titleOverrideAr = null,
        ?string $titleOverrideEn = null,
        ?int $sortOrder = null,
    ): AboutNavigationCardDTO {
        $maxOrder = AboutNavigationCard::query()->max('sort_order') ?? 0;

        $card = AboutNavigationCard::query()->create([
            'target_key' => $targetKey,
            'title_override_ar' => $titleOverrideAr,
            'title_override_en' => $titleOverrideEn,
            'sort_order' => $sortOrder ?? ($maxOrder + 1),
            'is_visible' => true,
            'status' => 'draft',
        ]);

        $this->invalidatePublicCache();

        return $this->mapToDto($card);
    }

    public function updateCard(int $id, array $data): bool
    {
        $card = AboutNavigationCard::query()->find($id);

        if (! $card instanceof AboutNavigationCard) {
            return false;
        }

        $updated = $card->update([
            'title_override_ar' => $data['title_override_ar'] ?? $card->title_override_ar,
            'title_override_en' => $data['title_override_en'] ?? $card->title_override_en,
            'sort_order' => $data['sort_order'] ?? $card->sort_order,
            'is_visible' => $data['is_visible'] ?? $card->is_visible,
            'status' => $data['status'] ?? $card->status,
            'publish_at' => $data['publish_at'] ?? $card->publish_at,
        ]);

        if ($updated) {
            $this->invalidatePublicCache();
        }

        return $updated;
    }

    public function deleteCard(int $id): bool
    {
        $card = AboutNavigationCard::query()->find($id);

        if (! $card instanceof AboutNavigationCard) {
            return false;
        }

        $deleted = (bool) $card->delete();

        if ($deleted) {
            $this->invalidatePublicCache();
        }

        return $deleted;
    }

    public function toggleVisibility(int $id): bool
    {
        $card = AboutNavigationCard::query()->find($id);

        if (! $card instanceof AboutNavigationCard) {
            return false;
        }

        $updated = $card->update(['is_visible' => ! $card->is_visible]);

        if ($updated) {
            $this->invalidatePublicCache();
        }

        return $updated;
    }

    /** @param array<int, int> $orderedIds */
    public function reorder(array $orderedIds): bool
    {
        foreach ($orderedIds as $index => $id) {
            AboutNavigationCard::query()->whereKey($id)->update(['sort_order' => $index + 1]);
        }

        $this->invalidatePublicCache();

        return true;
    }

    public function autoCreateForTarget(string $targetKey): void
    {
        $exists = AboutNavigationCard::query()->where('target_key', $targetKey)->exists();

        if ($exists) {
            return;
        }

        $target = $this->targetRegistry->find($targetKey);

        if (! $target instanceof CmsTargetDTO || $target->publicPath === null) {
            return;
        }

        $this->createCard(targetKey: $targetKey);
    }

    public function saveDraft(int $id): bool
    {
        $card = AboutNavigationCard::query()->find($id);

        if (! $card instanceof AboutNavigationCard) {
            return false;
        }

        $updated = $card->update([
            'status' => 'draft',
            'published_at' => null,
        ]);

        if ($updated) {
            $this->invalidatePublicCache();
        }

        return $updated;
    }

    public function publish(int $id): bool
    {
        $card = AboutNavigationCard::query()->find($id);

        if (! $card instanceof AboutNavigationCard) {
            return false;
        }

        $updated = $card->update([
            'status' => 'published',
            'published_at' => now(),
            'publish_at' => null,
        ]);

        if ($updated) {
            $this->invalidatePublicCache();
        }

        return $updated;
    }

    public function schedule(int $id, string $publishAt): bool
    {
        $card = AboutNavigationCard::query()->find($id);

        if (! $card instanceof AboutNavigationCard) {
            return false;
        }

        $updated = $card->update([
            'status' => 'scheduled',
            'publish_at' => new \DateTimeImmutable($publishAt),
            'published_at' => null,
        ]);

        if ($updated) {
            $this->invalidatePublicCache();
        }

        return $updated;
    }

    public function unpublish(int $id): bool
    {
        $card = AboutNavigationCard::query()->find($id);

        if (! $card instanceof AboutNavigationCard) {
            return false;
        }

        $updated = $card->update([
            'status' => 'draft',
            'published_at' => null,
            'publish_at' => null,
        ]);

        if ($updated) {
            $this->invalidatePublicCache();
        }

        return $updated;
    }

    public function moveUp(int $id): bool
    {
        $card = AboutNavigationCard::query()->find($id);

        if (! $card instanceof AboutNavigationCard) {
            return false;
        }

        $previous = AboutNavigationCard::query()
            ->where('sort_order', '<', $card->sort_order)
            ->orderByDesc('sort_order')
            ->first();

        if (! $previous instanceof AboutNavigationCard) {
            return false;
        }

        $temp = (int) $previous->sort_order;
        $previous->update(['sort_order' => $card->sort_order]);
        $card->update(['sort_order' => $temp]);

        $this->invalidatePublicCache();

        return true;
    }

    public function moveDown(int $id): bool
    {
        $card = AboutNavigationCard::query()->find($id);

        if (! $card instanceof AboutNavigationCard) {
            return false;
        }

        $next = AboutNavigationCard::query()
            ->where('sort_order', '>', $card->sort_order)
            ->orderBy('sort_order')
            ->first();

        if (! $next instanceof AboutNavigationCard) {
            return false;
        }

        $temp = (int) $next->sort_order;
        $next->update(['sort_order' => $card->sort_order]);
        $card->update(['sort_order' => $temp]);

        $this->invalidatePublicCache();

        return true;
    }

    public function publishDueScheduled(): int
    {
        $published = 0;

        AboutNavigationCard::query()
            ->where('status', 'scheduled')
            ->whereNotNull('publish_at')
            ->where('publish_at', '<=', now())
            ->orderBy('publish_at')
            ->orderBy('id')
            ->get()
            ->each(function (AboutNavigationCard $card) use (&$published): void {
                $updated = $card->update([
                    'status' => 'published',
                    'published_at' => now(),
                    'publish_at' => null,
                ]);

                if ($updated) {
                    $published++;
                }
            });

        if ($published > 0) {
            $this->invalidatePublicCache();
        }

        return $published;
    }

    private function mapToDto(AboutNavigationCard $card): AboutNavigationCardDTO
    {
        $target = $this->targetRegistry->find($card->target_key);

        return new AboutNavigationCardDTO(
            id: (int) $card->getKey(),
            targetKey: $card->target_key,
            titleOverrideAr: $card->title_override_ar,
            titleOverrideEn: $card->title_override_en,
            sortOrder: (int) $card->sort_order,
            isVisible: (bool) $card->is_visible,
            status: $card->status,
            publishAt: $card->publish_at?->toDateTimeString(),
            publishedAt: $card->published_at?->toDateTimeString(),
            resolvedTitleAr: $card->title_override_ar ?? ($target instanceof CmsTargetDTO ? __($target->labelKey, [], 'ar') : $card->target_key),
            resolvedTitleEn: $card->title_override_en ?? ($target instanceof CmsTargetDTO ? __($target->labelKey, [], 'en') : $card->target_key),
            publicPath: $target instanceof CmsTargetDTO ? $target->publicPath : null,
        );
    }

    private function invalidatePublicCache(): void
    {
        if (! $this->cacheService->flushTags(['public-pages', 'public-shell', 'about', 'navigation', 'seo', 'sitemap'])) {
            $this->cacheService->flushAll();
        }
    }

    /** @return array<int, array<string, string>> */
    private function fallbackFromRegistry(string $locale): array
    {
        return $this->targetRegistry->forArea('about')
            ->filter(fn (CmsTargetDTO $target): bool => $target->key !== 'about.landing' && $target->publicPath !== null)
            ->values()
            ->map(fn (CmsTargetDTO $target): array => [
                'target_key' => $target->key,
                'title' => __($target->labelKey, [], $locale),
                'link' => $target->publicPath,
            ])
            ->all();
    }
}
