<?php

declare(strict_types=1);

namespace App\Services;

use App\Contracts\AuditServiceInterface;
use App\Contracts\CacheServiceInterface;
use App\Contracts\PageServiceInterface;
use App\DTOs\BreadcrumbTrailDTO;
use App\DTOs\PageDraftDataDTO;
use App\DTOs\PageDraftDTO;
use App\DTOs\PageDTO;
use App\DTOs\PageMetadataDTO;
use App\DTOs\PageSeoInputDTO;
use App\DTOs\PageShellDataDTO;
use App\DTOs\PageTranslationDTO;
use App\DTOs\PreviewDTO;
use App\Models\Page;
use App\Models\PageDraft;
use App\Models\PageSeoMeta;
use App\Models\PageTranslation;
use App\Support\HtmlSanitizer;
use DateTimeInterface;
use Illuminate\Support\Facades\DB;

/**
 * Orchestrates page CRUD operations, delegating public reads to PagePublicReadService,
 * draft lifecycle to PageDraftService, and URL resolution to PageUrlResolver.
 *
 * The PageServiceInterface contract remains unchanged — this class is the sole
 * implementation bound in AppServiceProvider.
 */
final class PageService implements PageServiceInterface
{
    private const EDITABLE_STATUSES = ['draft', 'scheduled'];

    public function __construct(
        private readonly AuditServiceInterface $auditService,
        private readonly CacheServiceInterface $cacheService,
        private readonly PagePublicReadService $publicReadService,
        private readonly PageDraftService $draftService,
        private readonly PageUrlResolver $urlResolver,
        private readonly HtmlSanitizer $htmlSanitizer,
    ) {}

    public function createPageShell(PageShellDataDTO $payload, int $userId): PageDTO
    {
        $page = Page::query()->create([
            'parent_id' => $payload->parentPageId,
            'type' => $payload->isHomepageShell ? 'homepage' : 'landing',
            'template' => $payload->template,
            'slug' => $payload->slug,
            'status' => $payload->status,
            'sort_order' => 0,
            'is_enabled' => true,
            'show_in_breadcrumbs' => true,
            'show_in_nav' => true,
            'is_homepage_shell' => $payload->isHomepageShell,
            'created_by' => $userId,
            'updated_by' => $userId,
        ]);

        $this->auditService->log('page.created', $userId, Page::class, (int) $page->getKey(), [
            'slug' => $page->slug,
            'template' => $page->template,
            'status' => $page->status,
        ]);

        return $this->publicReadService->mapPageToDto($page->fresh(['translations', 'seoMeta']));
    }

    public function updateBaseMetadata(int $pageId, PageMetadataDTO $payload): bool
    {
        $page = Page::query()->find($pageId);

        if (! $page instanceof Page) {
            return false;
        }

        $updated = $page->update([
            'parent_id' => $payload->parentPageId,
            'template' => $payload->template,
            'slug' => $payload->slug,
            'status' => $payload->status,
            'is_enabled' => $payload->isEnabled,
            'show_in_breadcrumbs' => $payload->showInBreadcrumbs,
            'show_in_nav' => $payload->showInNav,
            'is_homepage_shell' => $payload->isHomepageShell,
            'publish_at' => $payload->publishAt,
            'content_json' => $payload->contentJson,
        ]);

        if ($updated) {
            $this->touchPageCaches((int) $page->getKey());
            $this->auditService->log('page.updated', null, Page::class, (int) $page->getKey(), [
                'field' => 'metadata',
            ]);
        }

        return $updated;
    }

    public function updateArabicTranslation(int $pageId, PageTranslationDTO $payload): bool
    {
        return $this->updateTranslation($pageId, 'ar', $payload);
    }

    public function updateEnglishTranslation(int $pageId, PageTranslationDTO $payload): bool
    {
        return $this->updateTranslation($pageId, 'en', $payload);
    }

    public function updateArabicSeo(int $pageId, PageSeoInputDTO $payload): bool
    {
        return $this->updateSeo($pageId, 'ar', $payload);
    }

    public function updateEnglishSeo(int $pageId, PageSeoInputDTO $payload): bool
    {
        return $this->updateSeo($pageId, 'en', $payload);
    }

    public function saveDraft(int $pageId, PageDraftDataDTO $payload, int $userId): PageDraftDTO
    {
        $page = Page::query()->findOrFail($pageId);

        $draft = PageDraft::query()->create([
            'page_id' => $pageId,
            'payload_json' => $this->draftService->pageDraftPayloadToArray($payload),
            'status' => $payload->metadata->status,
            'created_by' => $userId,
            'updated_by' => $userId,
            'scheduled_at' => $payload->metadata->publishAt,
        ]);

        $this->auditService->log('page.draft_saved', $userId, Page::class, (int) $page->getKey(), [
            'draft_id' => (int) $draft->getKey(),
            'status' => $draft->status,
        ]);

        return $this->draftService->mapDraftToDto($draft);
    }

    public function publish(int $pageId, int $userId): bool
    {
        $page = Page::query()->with(['translations', 'seoMeta'])->find($pageId);

        if (! $page instanceof Page) {
            return false;
        }

        if (! $this->isPublishable($page)) {
            return false;
        }

        $draft = PageDraft::query()
            ->where('page_id', $pageId)
            ->whereIn('status', self::EDITABLE_STATUSES)
            ->latest('updated_at')
            ->first();

        DB::transaction(function () use ($page, $draft, $userId): void {
            if ($draft instanceof PageDraft && is_array($draft->payload_json)) {
                $this->draftService->applyDraftPayloadToPage(
                    $page,
                    $draft->payload_json,
                    $userId,
                    fn (int $id, string $locale, PageTranslationDTO $dto): bool => $this->updateTranslation($id, $locale, $dto),
                    fn (int $id, string $locale, PageSeoInputDTO $dto): bool => $this->updateSeo($id, $locale, $dto),
                );
                $draft->forceFill([
                    'status' => 'published',
                    'approved_by' => $userId,
                    'published_at' => now(),
                ])->save();
            }

            $page->forceFill([
                'status' => 'published',
                'published_at' => now(),
                'publish_at' => $page->publish_at?->isFuture() ? $page->publish_at : now(),
                'approved_by' => $userId,
                'updated_by' => $userId,
            ])->save();
        });

        $this->touchPageCaches((int) $page->getKey());
        $this->auditService->log('page.publish', $userId, Page::class, (int) $page->getKey());

        return true;
    }

    public function unpublish(int $pageId, int $userId): bool
    {
        $page = Page::query()->find($pageId);

        if (! $page instanceof Page) {
            return false;
        }

        $updated = $page->update([
            'status' => 'draft',
            'published_at' => null,
            'updated_by' => $userId,
        ]);

        if ($updated) {
            $this->touchPageCaches((int) $page->getKey());
            $this->auditService->log('page.unpublish', $userId, Page::class, (int) $page->getKey());
        }

        return $updated;
    }

    public function schedulePublish(int $pageId, DateTimeInterface $publishAt, int $userId): bool
    {
        $page = Page::query()->find($pageId);

        if (! $page instanceof Page) {
            return false;
        }

        $draft = PageDraft::query()
            ->where('page_id', $pageId)
            ->latest('updated_at')
            ->first();

        if ($draft instanceof PageDraft) {
            $draft->forceFill([
                'status' => 'scheduled',
                'updated_by' => $userId,
                'scheduled_at' => $publishAt,
            ])->save();
        }

        $updated = $page->update([
            'status' => 'scheduled',
            'publish_at' => $publishAt,
            'updated_by' => $userId,
        ]);

        if ($updated) {
            $this->touchPageCaches((int) $page->getKey());
            $this->auditService->log('page.schedule', $userId, Page::class, (int) $page->getKey(), [
                'publish_at' => $publishAt->format(DATE_ATOM),
            ]);
        }

        return $updated;
    }

    // ── Delegated public reads ──

    public function getPublicPageBySlug(string $slug, string $locale): ?PageDTO
    {
        return $this->publicReadService->getPublicPageBySlug($slug, $locale);
    }

    public function getAdminEditorPayload(int $pageId): PageDTO
    {
        return $this->publicReadService->getAdminEditorPayload($pageId);
    }

    // ── Delegated URL resolution ──

    public function buildBreadcrumbPayload(int $pageId, string $locale): BreadcrumbTrailDTO
    {
        return $this->urlResolver->buildBreadcrumbPayload($pageId, $locale);
    }

    public function resolveLanguageSwitchTargetUrl(int $pageId, string $targetLocale): ?string
    {
        return $this->urlResolver->resolveLanguageSwitchTargetUrl($pageId, $targetLocale);
    }

    // ── Delegated preview / draft operations ──

    public function buildPreviewPayload(int $pageId, string $locale): PreviewDTO
    {
        return $this->draftService->buildPreviewPayload($pageId, $locale);
    }

    public function buildPreviewPayloadFromSnapshot(int $pageId, array $snapshot, string $locale): PreviewDTO
    {
        return $this->draftService->buildPreviewPayloadFromSnapshot($pageId, $snapshot, $locale);
    }

    // ── Private helpers (CRUD + sanitization) ──

    private function updateTranslation(int $pageId, string $locale, PageTranslationDTO $payload): bool
    {
        $page = Page::query()->find($pageId);

        if (! $page instanceof Page) {
            return false;
        }

        $sanitizedBody = $this->htmlSanitizer->sanitize($payload->body);
        $sanitizedBodyPayload = $this->sanitizeBodyPayload($payload->bodyPayload);

        $translation = PageTranslation::query()->updateOrCreate(
            ['page_id' => $pageId, 'locale' => $locale],
            [
                'title' => $payload->title,
                'navigation_label' => $payload->navigationLabel,
                'headline' => $payload->headline,
                'subheadline' => $payload->subheadline,
                'hero_payload' => $payload->heroPayload,
                'overview_cards_payload' => $payload->overviewCardsPayload,
                'stats_payload' => $payload->statsPayload,
                'body_payload' => $sanitizedBodyPayload,
                'cta_payload' => $payload->ctaPayload,
                'sidebar_payload' => $payload->sidebarPayload,
                'excerpt' => $payload->excerpt,
                'body' => $sanitizedBody,
                'raw_excerpt' => $payload->rawExcerpt,
                'meta_title_fallback' => $payload->metaTitleFallback,
            ],
        );

        $this->touchPageCaches((int) $page->getKey());
        $this->auditService->log('page.updated', null, Page::class, (int) $page->getKey(), [
            'field' => 'translation',
            'locale' => $locale,
        ]);

        return $translation->exists;
    }

    private function updateSeo(int $pageId, string $locale, PageSeoInputDTO $payload): bool
    {
        $page = Page::query()->find($pageId);

        if (! $page instanceof Page) {
            return false;
        }

        $seo = PageSeoMeta::query()->updateOrCreate(
            ['page_id' => $pageId, 'locale' => $locale],
            [
                'meta_title' => $payload->title,
                'meta_description' => $payload->metaDescription,
                'og_title' => $payload->ogTitle,
                'og_description' => $payload->ogDescription,
                'og_image_url' => $payload->ogImage,
                'canonical_url' => $payload->canonicalUrl,
                'robots' => $payload->robots,
            ],
        );

        $this->touchPageCaches((int) $page->getKey());
        $this->auditService->log('page.updated', null, Page::class, (int) $page->getKey(), [
            'field' => 'seo',
            'locale' => $locale,
        ]);

        return $seo->exists;
    }

    /** @param  array<string, mixed>|null  $bodyPayload */
    private function sanitizeBodyPayload(?array $bodyPayload): ?array
    {
        if ($bodyPayload === null || ! is_array($bodyPayload['blocks'] ?? null)) {
            return $bodyPayload;
        }

        $bodyPayload['blocks'] = array_map(function (mixed $block): mixed {
            if (is_array($block) && ($block['type'] ?? null) === 'legacy_html' && ! empty($block['content'])) {
                $block['content'] = $this->htmlSanitizer->sanitize($block['content']);
            }

            return $block;
        }, $bodyPayload['blocks']);

        return $bodyPayload;
    }

    private function touchPageCaches(int $pageId): void
    {
        $this->cacheService->flushTags(['pages', 'seo', 'sitemap', 'navigation', 'settings']);
    }

    private function isPublishable(Page $page): bool
    {
        if (empty($page->slug) || empty($page->template) || ! (bool) $page->is_enabled) {
            return false;
        }

        $page->loadMissing('translations');

        return $page->translations->contains(fn ($t) => ! empty($t->title));
    }
}
