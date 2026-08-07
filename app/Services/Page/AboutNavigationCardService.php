<?php

declare(strict_types=1);

namespace App\Services\Page;

use App\Contracts\Cms\CmsTargetRegistryInterface;
use App\Contracts\Page\AboutNavigationCardServiceInterface;
use App\DTOs\About\AboutNavigationCardDTO;
use App\DTOs\Cms\CmsTargetDTO;
use App\Models\Page\AboutNavigationCard;
use Illuminate\Support\Collection;

final class AboutNavigationCardService implements AboutNavigationCardServiceInterface
{
    public function __construct(
        private readonly CmsTargetRegistryInterface $targetRegistry,
    ) {}

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

        return $this->mapToDto($card);
    }

    public function updateCard(int $id, array $data): bool
    {
        $card = AboutNavigationCard::query()->find($id);

        if (! $card instanceof AboutNavigationCard) {
            return false;
        }

        return $card->update([
            'title_override_ar' => $data['title_override_ar'] ?? $card->title_override_ar,
            'title_override_en' => $data['title_override_en'] ?? $card->title_override_en,
            'sort_order' => $data['sort_order'] ?? $card->sort_order,
            'is_visible' => $data['is_visible'] ?? $card->is_visible,
            'status' => $data['status'] ?? $card->status,
            'publish_at' => $data['publish_at'] ?? $card->publish_at,
        ]);
    }

    public function deleteCard(int $id): bool
    {
        $card = AboutNavigationCard::query()->find($id);

        if (! $card instanceof AboutNavigationCard) {
            return false;
        }

        return $card->delete();
    }

    public function toggleVisibility(int $id): bool
    {
        $card = AboutNavigationCard::query()->find($id);

        if (! $card instanceof AboutNavigationCard) {
            return false;
        }

        return $card->update(['is_visible' => ! $card->is_visible]);
    }

    /** @param array<int, int> $orderedIds */
    public function reorder(array $orderedIds): bool
    {
        foreach ($orderedIds as $index => $id) {
            AboutNavigationCard::query()->whereKey($id)->update(['sort_order' => $index + 1]);
        }

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

        return $card->update([
            'status' => 'draft',
            'published_at' => null,
        ]);
    }

    public function publish(int $id): bool
    {
        $card = AboutNavigationCard::query()->find($id);

        if (! $card instanceof AboutNavigationCard) {
            return false;
        }

        return $card->update([
            'status' => 'published',
            'published_at' => now(),
            'publish_at' => null,
        ]);
    }

    public function schedule(int $id, string $publishAt): bool
    {
        $card = AboutNavigationCard::query()->find($id);

        if (! $card instanceof AboutNavigationCard) {
            return false;
        }

        return $card->update([
            'status' => 'scheduled',
            'publish_at' => new \DateTimeImmutable($publishAt),
            'published_at' => null,
        ]);
    }

    public function unpublish(int $id): bool
    {
        $card = AboutNavigationCard::query()->find($id);

        if (! $card instanceof AboutNavigationCard) {
            return false;
        }

        return $card->update([
            'status' => 'draft',
            'published_at' => null,
            'publish_at' => null,
        ]);
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

        return true;
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
