<?php

declare(strict_types=1);

namespace App\Services\Council;

use App\Contracts\Council\CouncilCatalogServiceInterface;
use App\Contracts\Shared\CacheServiceInterface;
use App\DTOs\Cms\CmsTargetDTO;
use App\Enums\PublicationStatus;
use App\Models\Cms\CmsDraft;
use App\Models\Cms\CmsTargetContent;
use App\Models\Content\UniversityCouncil;
use App\Models\Person\FacultyMember;

/**
 * No dependency on the CMS workflow or target registry (both call into this),
 * so it reads cms_target_contents and cms_drafts itself, and only the columns
 * it needs.
 */
final class CouncilCatalogService implements CouncilCatalogServiceInterface
{
    public function __construct(
        private readonly CacheServiceInterface $cacheService,
    ) {}

    public function targetKey(int $councilId): string
    {
        return self::TARGET_PREFIX.$councilId;
    }

    public function councilIdFromTarget(string $targetKey): ?int
    {
        if (preg_match('/^entity\.council\.([1-9][0-9]*)$/', $targetKey, $matches) !== 1) {
            return null;
        }

        return (int) $matches[1];
    }

    public function resolveTarget(string $targetKey): ?CmsTargetDTO
    {
        $id = $this->councilIdFromTarget($targetKey);
        $slug = $id !== null ? $this->slugOf($id) : null;
        if ($slug === null) {
            return null;
        }

        return new CmsTargetDTO(
            key: $targetKey,
            area: 'about',
            labelKey: 'admin.cms.targets.entity.council',
            publicPath: '/about/councils/'.$slug,
            routeName: 'public.about.councils.show',
            parentKey: 'about.councils',
            supportsDraftWorkflow: true,
        );
    }

    public function createCouncil(string $slug, int $sortOrder, bool $isEnabled): int
    {
        $council = UniversityCouncil::query()->create([
            'slug' => $slug,
            'sort_order' => max(0, $sortOrder),
            'is_enabled' => $isEnabled,
        ]);

        return (int) $council->getKey();
    }

    public function updateSettings(int $councilId, int $sortOrder, bool $isEnabled): bool
    {
        $updated = UniversityCouncil::query()->whereKey($councilId)->update([
            'sort_order' => max(0, $sortOrder),
            'is_enabled' => $isEnabled,
            'updated_at' => now(),
        ]) > 0;

        if ($updated) {
            $this->flushPublicCache();
        }

        return $updated;
    }

    public function deleteCouncil(int $councilId): bool
    {
        $deleted = UniversityCouncil::query()->whereKey($councilId)->delete() > 0;

        if ($deleted) {
            // The page is gone: unpublish its content so nothing can show it again
            // if the id were ever reused. The versions stay in the CMS history.
            CmsTargetContent::query()
                ->where('target_key', $this->targetKey($councilId))
                ->update(['status' => PublicationStatus::Draft->value, 'updated_at' => now()]);
            $this->flushPublicCache();
        }

        return $deleted;
    }

    public function slugOf(int $councilId): ?string
    {
        $slug = UniversityCouncil::query()->whereKey($councilId)->value('slug');

        return is_string($slug) && $slug !== '' ? $slug : null;
    }

    public function newCouncilPayload(string $titleAr, string $titleEn): array
    {
        $payload = $this->emptyPayload();
        $payload['translations']['ar']['hero']['title'] = trim($titleAr);
        $payload['translations']['en']['hero']['title'] = trim($titleEn);

        return $payload;
    }

    public function editorPayload(int $councilId): array
    {
        $published = $this->publishedPayload($councilId);

        if (is_array($published['translations']['ar'] ?? null) && is_array($published['translations']['en'] ?? null)) {
            return ['translations' => [
                'ar' => $published['translations']['ar'],
                'en' => $published['translations']['en'],
            ]];
        }

        return $this->emptyPayload();
    }

    public function adminTitle(int $councilId, string $locale): string
    {
        $locale = $locale === 'ar' ? 'ar' : 'en';
        $targetKey = $this->targetKey($councilId);

        $draftId = CmsDraft::query()
            ->where('target_key', $targetKey)
            ->whereIn('status', PublicationStatus::editableValues())
            ->orderByDesc('id')
            ->value('id');
        $payload = $draftId !== null
            ? CmsDraft::query()->whereKey($draftId)->value('payload_json')
            : CmsTargetContent::query()->where('target_key', $targetKey)->value('payload_json');
        $payload = is_string($payload) ? json_decode($payload, true) : $payload;

        foreach ([$locale, $locale === 'ar' ? 'en' : 'ar'] as $candidate) {
            $title = is_array($payload) ? trim((string) ($payload['translations'][$candidate]['hero']['title'] ?? '')) : '';
            if ($title !== '') {
                return $title;
            }
        }

        return (string) ($this->slugOf($councilId) ?? '');
    }

    public function isPublished(int $councilId): bool
    {
        return CmsTargetContent::query()
            ->where('target_key', $this->targetKey($councilId))
            ->where('status', PublicationStatus::Published->value)
            ->exists();
    }

    public function validationErrors(array $payload): array
    {
        $errors = [];
        $translations = is_array($payload['translations'] ?? null) ? $payload['translations'] : [];
        $memberIds = [];

        foreach (['ar', 'en'] as $locale) {
            $content = is_array($translations[$locale] ?? null) ? $translations[$locale] : [];
            $label = $locale === 'ar' ? 'Arabic' : 'English';

            if (trim((string) ($content['hero']['title'] ?? '')) === '') {
                $errors['council'][] = "The {$label} council name is required.";
            }

            $members = $this->listOfArrays($content['council']['members'] ?? []);
            $memberIds[$locale] = array_map(static fn (array $member): string => trim((string) ($member['id'] ?? '')), $members);

            foreach ($members as $index => $member) {
                $number = $index + 1;
                if (filter_var($member['isStaff'] ?? false, FILTER_VALIDATE_BOOLEAN)) {
                    if (! is_numeric($member['staffId'] ?? null) || (int) $member['staffId'] <= 0) {
                        $errors['members'][] = "Member {$number} ({$label}) is marked as teaching staff but no staff member is picked.";
                    }

                    continue;
                }

                if ($locale === 'ar' && trim((string) ($member['name'] ?? '')) === '') {
                    $errors['members'][] = "Member {$number} needs a name.";
                }
            }

            foreach ($this->listOfArrays($content['functions']['items'] ?? []) as $index => $function) {
                if (trim((string) ($function['title'] ?? '')) === '') {
                    $errors['functions'][] = 'Function '.($index + 1)." ({$label}) needs a title.";
                }
            }
        }

        if (($memberIds['ar'] ?? []) !== ($memberIds['en'] ?? [])) {
            $errors['members'][] = 'The Arabic and English pages must list the same members in the same order.';
        }

        $staffIds = [];
        foreach ($this->listOfArrays($translations['ar']['council']['members'] ?? []) as $member) {
            if (filter_var($member['isStaff'] ?? false, FILTER_VALIDATE_BOOLEAN) && is_numeric($member['staffId'] ?? null)) {
                $staffIds[] = (int) $member['staffId'];
            }
        }
        $staffIds = array_values(array_unique(array_filter($staffIds, static fn (int $id): bool => $id > 0)));
        if ($staffIds !== []) {
            $found = FacultyMember::query()->whereKey($staffIds)->pluck('id')->map(static fn (mixed $id): int => (int) $id)->all();
            foreach (array_diff($staffIds, $found) as $missing) {
                $errors['members'][] = "Staff profile {$missing} no longer exists; pick the member again.";
            }
        }

        return array_map(static fn (array $messages): array => array_values(array_unique($messages)), $errors);
    }

    /** @return array<string, mixed>|null */
    private function publishedPayload(int $councilId): ?array
    {
        $payload = CmsTargetContent::query()
            ->where('target_key', $this->targetKey($councilId))
            ->where('status', PublicationStatus::Published->value)
            ->value('payload_json');
        $payload = is_string($payload) ? json_decode($payload, true) : $payload;

        return is_array($payload) ? $payload : null;
    }

    /** @return array{translations: array{ar: array<string, mixed>, en: array<string, mixed>}} */
    private function emptyPayload(): array
    {
        $content = fn (string $locale): array => [
            'hero' => [
                'title' => '',
                'summary' => '',
                'backgroundImage' => null,
            ],
            'council' => [
                'title' => __('councils.defaults.members_title', [], $locale),
                'subtitle' => __('councils.defaults.members_subtitle', [], $locale),
                'headLabel' => __('councils.defaults.head_label', [], $locale),
                'memberLabel' => __('councils.defaults.member_label', [], $locale),
                'profileLabel' => __('councils.defaults.profile_label', [], $locale),
                'members' => [],
            ],
            'functions' => [
                'title' => __('councils.defaults.functions_title', [], $locale),
                'subtitle' => __('councils.defaults.functions_subtitle', [], $locale),
                'items' => [],
            ],
        ];

        return ['translations' => ['ar' => $content('ar'), 'en' => $content('en')]];
    }

    /** @return list<array<string, mixed>> */
    private function listOfArrays(mixed $items): array
    {
        return is_array($items) ? array_values(array_filter($items, 'is_array')) : [];
    }

    private function flushPublicCache(): void
    {
        $this->cacheService->flushTags(['public-pages', 'public-shell', 'seo', 'sitemap', 'councils', 'about']);
    }
}
