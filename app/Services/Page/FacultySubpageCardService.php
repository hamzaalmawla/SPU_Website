<?php

declare(strict_types=1);

namespace App\Services\Page;

use App\Contracts\Page\FacultySubpageCardServiceInterface;
use App\DTOs\Faculty\FacultySubpageCardDTO;
use App\Models\Faculty\Faculty;
use App\Models\Faculty\FacultyPage;
use App\Models\Faculty\FacultySubpageCard;
use Illuminate\Support\Collection;

final class FacultySubpageCardService implements FacultySubpageCardServiceInterface
{
    public function hasAnyCards(string $facultySlug): bool
    {
        return FacultySubpageCard::query()
            ->where('faculty_slug', $facultySlug)
            ->exists();
    }

    /** @return array<string, string> */
    public function availableSubpageOptions(string $facultySlug): array
    {
        $faculty = Faculty::query()
            ->where('faculty_scope_slug', $facultySlug)
            ->orWhere('public_slug', $facultySlug)
            ->orWhere('slug', $facultySlug)
            ->first();

        $slugs = ['overview', 'departments', 'study-plan', 'labs', 'projects', 'alumni', 'valedictorians', 'research'];

        if ($faculty instanceof Faculty) {
            if ($faculty->public_slug === 'pharmacy') {
                $slugs[] = 'training';
            }

            $custom = FacultyPage::query()
                ->where('faculty_id', $faculty->getKey())
                ->where('is_enabled', true)
                ->pluck('slug')
                ->all();

            foreach ($custom as $slug) {
                if (! in_array($slug, $slugs, true)) {
                    $slugs[] = $slug;
                }
            }
        } elseif ($facultySlug === 'pharmacy') {
            $slugs[] = 'training';
        }

        $options = [];

        foreach ($slugs as $slug) {
            if (! is_string($slug) || $slug === '' || $slug === 'study-plan-course') {
                continue;
            }

            $options[$slug] = __('admin.faculty_workspace.subpages.'.str_replace('-', '_', $slug));
        }

        return $options;
    }

    /** @return Collection<int, FacultySubpageCardDTO> */
    public function getAllCards(string $facultySlug): Collection
    {
        return FacultySubpageCard::query()
            ->where('faculty_slug', $facultySlug)
            ->orderBy('sort_order')
            ->get()
            ->map(fn (FacultySubpageCard $card): FacultySubpageCardDTO => $this->mapToDto($card));
    }

    /** @return array<int, string> */
    public function getVisibleSubpageSlugs(string $facultySlug): array
    {
        return FacultySubpageCard::query()
            ->where('faculty_slug', $facultySlug)
            ->where('is_visible', true)
            ->where('status', 'published')
            ->whereNotNull('published_at')
            ->where(function ($query): void {
                $query->whereNull('publish_at')->orWhere('publish_at', '<=', now());
            })
            ->orderBy('sort_order')
            ->pluck('subpage_slug')
            ->all();
    }

    public function createCard(
        string $facultySlug,
        string $subpageSlug,
        ?string $titleOverrideAr = null,
        ?string $titleOverrideEn = null,
        ?int $sortOrder = null,
    ): FacultySubpageCardDTO {
        $maxOrder = FacultySubpageCard::query()
            ->where('faculty_slug', $facultySlug)
            ->max('sort_order') ?? 0;

        $card = FacultySubpageCard::query()->create([
            'faculty_slug' => $facultySlug,
            'subpage_slug' => $subpageSlug,
            'title_override_ar' => $titleOverrideAr,
            'title_override_en' => $titleOverrideEn,
            'sort_order' => $sortOrder ?? ($maxOrder + 1),
            'is_visible' => true,
            'status' => 'published',
            'published_at' => now(),
        ]);

        return $this->mapToDto($card);
    }

    public function updateCard(int $id, array $data): bool
    {
        $card = FacultySubpageCard::query()->find($id);

        if (! $card instanceof FacultySubpageCard) {
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
        $card = FacultySubpageCard::query()->find($id);

        if (! $card instanceof FacultySubpageCard) {
            return false;
        }

        return $card->delete();
    }

    public function toggleVisibility(int $id): bool
    {
        $card = FacultySubpageCard::query()->find($id);

        if (! $card instanceof FacultySubpageCard) {
            return false;
        }

        return $card->update(['is_visible' => ! $card->is_visible]);
    }

    /** @param array<int, int> $orderedIds */
    public function reorder(array $orderedIds): bool
    {
        foreach ($orderedIds as $index => $id) {
            FacultySubpageCard::query()->whereKey($id)->update(['sort_order' => $index + 1]);
        }

        return true;
    }

    public function publish(int $id): bool
    {
        $card = FacultySubpageCard::query()->find($id);

        if (! $card instanceof FacultySubpageCard) {
            return false;
        }

        return $card->update([
            'status' => 'published',
            'published_at' => now(),
            'publish_at' => null,
        ]);
    }

    public function unpublish(int $id): bool
    {
        $card = FacultySubpageCard::query()->find($id);

        if (! $card instanceof FacultySubpageCard) {
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
        $card = FacultySubpageCard::query()->find($id);

        if (! $card instanceof FacultySubpageCard) {
            return false;
        }

        $previous = FacultySubpageCard::query()
            ->where('faculty_slug', $card->faculty_slug)
            ->where('sort_order', '<', $card->sort_order)
            ->orderByDesc('sort_order')
            ->first();

        if (! $previous instanceof FacultySubpageCard) {
            return false;
        }

        $temp = (int) $previous->sort_order;
        $previous->update(['sort_order' => $card->sort_order]);
        $card->update(['sort_order' => $temp]);

        return true;
    }

    public function moveDown(int $id): bool
    {
        $card = FacultySubpageCard::query()->find($id);

        if (! $card instanceof FacultySubpageCard) {
            return false;
        }

        $next = FacultySubpageCard::query()
            ->where('faculty_slug', $card->faculty_slug)
            ->where('sort_order', '>', $card->sort_order)
            ->orderBy('sort_order')
            ->first();

        if (! $next instanceof FacultySubpageCard) {
            return false;
        }

        $temp = (int) $next->sort_order;
        $next->update(['sort_order' => $card->sort_order]);
        $card->update(['sort_order' => $temp]);

        return true;
    }

    private function mapToDto(FacultySubpageCard $card): FacultySubpageCardDTO
    {
        return new FacultySubpageCardDTO(
            id: (int) $card->getKey(),
            facultySlug: $card->faculty_slug,
            subpageSlug: $card->subpage_slug,
            titleOverrideAr: $card->title_override_ar,
            titleOverrideEn: $card->title_override_en,
            sortOrder: (int) $card->sort_order,
            isVisible: (bool) $card->is_visible,
            status: $card->status,
            publishAt: $card->publish_at?->toDateTimeString(),
            publishedAt: $card->published_at?->toDateTimeString(),
        );
    }
}
