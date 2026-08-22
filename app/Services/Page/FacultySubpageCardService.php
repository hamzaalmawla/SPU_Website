<?php

declare(strict_types=1);

namespace App\Services\Page;

use App\Contracts\Page\FacultySubpageCardServiceInterface;
use App\Contracts\Shared\AuditServiceInterface;
use App\Contracts\Shared\CacheServiceInterface;
use App\DTOs\Faculty\FacultySubpageCardDTO;
use App\Models\Faculty\Faculty;
use App\Models\Faculty\FacultyPage;
use App\Models\Faculty\FacultySubpageCard;
use App\Models\User\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

final class FacultySubpageCardService implements FacultySubpageCardServiceInterface
{
    public function __construct(
        private readonly AuditServiceInterface $auditService,
        private readonly CacheServiceInterface $cacheService,
    ) {}

    public function scopedFacultySlug(int $userId): ?string
    {
        $user = User::query()->find($userId);

        if (! $user instanceof User || $user->role_slug !== 'faculty_editor') {
            return null;
        }

        $scope = trim((string) ($user->faculty_scope_slug ?? ''));

        return $scope === '' ? null : $this->canonicalFacultyScope($scope);
    }

    /** @return array<string, string> */
    public function facultyOptions(int $userId): array
    {
        $user = $this->authorizedUser($userId);
        $query = Faculty::query()->where('is_enabled', true);

        if ($user->role_slug === 'faculty_editor') {
            $scope = $this->canonicalFacultyScope((string) ($user->faculty_scope_slug ?? ''));
            $query->where('public_slug', $scope);
        }

        return $query->pluck('public_slug', 'public_slug')->all();
    }

    public function cardExists(string $facultySlug, string $subpageSlug): bool
    {
        return FacultySubpageCard::query()
            ->where('faculty_slug', $facultySlug)
            ->where('subpage_slug', $subpageSlug)
            ->exists();
    }

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

        $slugs = ['overview', 'departments', 'study-plan', 'labs', 'projects', 'alumni', 'valedictorians', 'research', 'members'];

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
        int $userId,
        ?string $titleOverrideAr = null,
        ?string $titleOverrideEn = null,
        ?int $sortOrder = null,
    ): FacultySubpageCardDTO {
        $this->authorizeCreate($facultySlug, $userId);

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
            'status' => 'draft',
            'published_at' => null,
        ]);

        $this->afterMutation('faculty_subpage_card.created', $card, $userId);

        return $this->mapToDto($card);
    }

    public function updateCard(int $id, array $data, int $userId): bool
    {
        $card = FacultySubpageCard::query()->find($id);

        if (! $card instanceof FacultySubpageCard) {
            return false;
        }

        $this->authorizeMutation($card, $userId);

        $updated = $card->update([
            'title_override_ar' => array_key_exists('title_override_ar', $data) ? $data['title_override_ar'] : $card->title_override_ar,
            'title_override_en' => array_key_exists('title_override_en', $data) ? $data['title_override_en'] : $card->title_override_en,
            'sort_order' => $data['sort_order'] ?? $card->sort_order,
            'is_visible' => $data['is_visible'] ?? $card->is_visible,
        ]);

        if ($updated) {
            $this->afterMutation('faculty_subpage_card.updated', $card, $userId);
        }

        return $updated;
    }

    public function deleteCard(int $id, int $userId): bool
    {
        $card = FacultySubpageCard::query()->find($id);

        if (! $card instanceof FacultySubpageCard) {
            return false;
        }

        $this->authorizeMutation($card, $userId, 'delete');
        $metadata = $this->auditMetadata($card);
        $deleted = (bool) $card->delete();

        if ($deleted) {
            $this->invalidatePublicCache();
            $this->auditService->log('faculty_subpage_card.deleted', $userId, FacultySubpageCard::class, $id, $metadata);
        }

        return $deleted;
    }

    public function toggleVisibility(int $id, int $userId): bool
    {
        $card = FacultySubpageCard::query()->find($id);

        if (! $card instanceof FacultySubpageCard) {
            return false;
        }

        $this->authorizeMutation($card, $userId);
        $updated = $card->update(['is_visible' => ! $card->is_visible]);

        if ($updated) {
            $this->afterMutation('faculty_subpage_card.visibility_updated', $card, $userId);
        }

        return $updated;
    }

    /** @param array<int, int> $orderedIds */
    public function reorder(array $orderedIds, int $userId): bool
    {
        $cards = FacultySubpageCard::query()->whereKey($orderedIds)->get()->keyBy('id');

        if ($cards->count() !== count(array_unique($orderedIds))) {
            return false;
        }

        foreach ($cards as $card) {
            $this->authorizeMutation($card, $userId);
        }

        DB::transaction(function () use ($orderedIds, $cards): void {
            foreach ($orderedIds as $index => $id) {
                $cards->get($id)?->update(['sort_order' => $index + 1]);
            }
        });

        $this->invalidatePublicCache();
        $this->auditService->log('faculty_subpage_card.reordered', $userId, FacultySubpageCard::class, metadata: [
            'ordered_ids' => array_values($orderedIds),
        ]);

        return true;
    }

    public function publish(int $id, int $userId): bool
    {
        $card = FacultySubpageCard::query()->find($id);

        if (! $card instanceof FacultySubpageCard) {
            return false;
        }

        $this->authorizePublication($card, $userId);
        $updated = $card->update([
            'status' => 'published',
            'published_at' => now(),
            'publish_at' => null,
        ]);

        if ($updated) {
            $this->afterMutation('faculty_subpage_card.published', $card, $userId);
        }

        return $updated;
    }

    public function unpublish(int $id, int $userId): bool
    {
        $card = FacultySubpageCard::query()->find($id);

        if (! $card instanceof FacultySubpageCard) {
            return false;
        }

        $this->authorizePublication($card, $userId);
        $updated = $card->update([
            'status' => 'draft',
            'published_at' => null,
            'publish_at' => null,
        ]);

        if ($updated) {
            $this->afterMutation('faculty_subpage_card.unpublished', $card, $userId);
        }

        return $updated;
    }

    public function moveUp(int $id, int $userId): bool
    {
        $card = FacultySubpageCard::query()->find($id);

        if (! $card instanceof FacultySubpageCard) {
            return false;
        }

        $this->authorizeMutation($card, $userId);

        $previous = FacultySubpageCard::query()
            ->where('faculty_slug', $card->faculty_slug)
            ->where('sort_order', '<', $card->sort_order)
            ->orderByDesc('sort_order')
            ->first();

        if (! $previous instanceof FacultySubpageCard) {
            return false;
        }

        $this->authorizeMutation($previous, $userId);

        $temp = (int) $previous->sort_order;
        DB::transaction(function () use ($card, $previous, $temp): void {
            $previous->update(['sort_order' => $card->sort_order]);
            $card->update(['sort_order' => $temp]);
        });

        $this->afterMutation('faculty_subpage_card.moved', $card, $userId, ['direction' => 'up']);

        return true;
    }

    public function moveDown(int $id, int $userId): bool
    {
        $card = FacultySubpageCard::query()->find($id);

        if (! $card instanceof FacultySubpageCard) {
            return false;
        }

        $this->authorizeMutation($card, $userId);

        $next = FacultySubpageCard::query()
            ->where('faculty_slug', $card->faculty_slug)
            ->where('sort_order', '>', $card->sort_order)
            ->orderBy('sort_order')
            ->first();

        if (! $next instanceof FacultySubpageCard) {
            return false;
        }

        $this->authorizeMutation($next, $userId);

        $temp = (int) $next->sort_order;
        DB::transaction(function () use ($card, $next, $temp): void {
            $next->update(['sort_order' => $card->sort_order]);
            $card->update(['sort_order' => $temp]);
        });

        $this->afterMutation('faculty_subpage_card.moved', $card, $userId, ['direction' => 'down']);

        return true;
    }

    private function authorizeCreate(string $facultySlug, int $userId): void
    {
        $user = $this->authorizedUser($userId);

        if (Gate::forUser($user)->denies('create', FacultySubpageCard::class)) {
            throw new AuthorizationException('This user is not authorized to create faculty subpage cards.');
        }

        $this->assertFacultyScope($user, $facultySlug);
    }

    private function authorizeMutation(FacultySubpageCard $card, int $userId, string $ability = 'update'): void
    {
        $user = $this->authorizedUser($userId);

        if (Gate::forUser($user)->denies($ability, $card)) {
            throw new AuthorizationException('This user is not authorized to modify this faculty subpage card.');
        }

        $this->assertFacultyScope($user, (string) $card->faculty_slug);
    }

    private function authorizePublication(FacultySubpageCard $card, int $userId): void
    {
        $user = $this->authorizedUser($userId);

        if (Gate::forUser($user)->denies('publish-content') || Gate::forUser($user)->denies('update', $card)) {
            throw new AuthorizationException('This user is not authorized to publish faculty subpage cards.');
        }

        $this->assertFacultyScope($user, (string) $card->faculty_slug);
    }

    private function authorizedUser(int $userId): User
    {
        $user = User::query()->find($userId);

        if (! $user instanceof User || (bool) $user->is_locked || Gate::forUser($user)->denies('manage-faculties')) {
            throw new AuthorizationException('This user is not authorized to manage faculty subpage cards.');
        }

        return $user;
    }

    private function assertFacultyScope(User $user, string $facultySlug): void
    {
        if ($user->role_slug !== 'faculty_editor') {
            return;
        }

        $userScope = $this->canonicalFacultyScope((string) ($user->faculty_scope_slug ?? ''));

        if ($userScope === '' || $userScope !== $this->canonicalFacultyScope($facultySlug)) {
            throw new AuthorizationException('This faculty editor is not authorized to manage this faculty.');
        }
    }

    private function canonicalFacultyScope(string $scope): string
    {
        if (trim($scope) === '') {
            return '';
        }

        return Faculty::query()
            ->where('faculty_scope_slug', $scope)
            ->orWhere('public_slug', $scope)
            ->orWhere('slug', $scope)
            ->value('public_slug') ?: $scope;
    }

    /** @param array<string, mixed> $metadata */
    private function afterMutation(string $action, FacultySubpageCard $card, int $userId, array $metadata = []): void
    {
        $this->invalidatePublicCache();
        $this->auditService->log($action, $userId, FacultySubpageCard::class, (int) $card->getKey(), [
            ...$this->auditMetadata($card),
            ...$metadata,
        ]);
    }

    /** @return array<string, mixed> */
    private function auditMetadata(FacultySubpageCard $card): array
    {
        return [
            'faculty_slug' => $card->faculty_slug,
            'subpage_slug' => $card->subpage_slug,
            'status' => $card->status,
            'is_visible' => (bool) $card->is_visible,
            'sort_order' => (int) $card->sort_order,
        ];
    }

    private function invalidatePublicCache(): void
    {
        if (! $this->cacheService->flushTags(['faculties', 'public-pages', 'public-shell', 'navigation', 'seo', 'sitemap'])) {
            $this->cacheService->flushAll();
        }
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
