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
use App\Events\DraftConflictDetected;
use App\Exceptions\ConflictException;
use App\Models\Page;
use App\Models\PageDraft;
use App\Models\PageSeoMeta;
use App\Models\PageTranslation;
use App\Support\HtmlSanitizer;
use App\Support\UrlSanitizer;
use DateTimeInterface;
use Illuminate\Support\Facades\DB;

/**
 * Orchestrates page CRUD operations, delegating public reads to PagePublicReadService,
 * draft lifecycle to PageDraftService, and URL resolution to PageUrlResolver.
 *
 * The PageServiceInterface contract remains unchanged — this class is the sole
 * implementation bound in AppServiceProvider.
 *
 * Index coverage for hot-path queries (verified 2026-04-30):
 * ──────────────────────────────────────────────────────────
 * Public reads: delegated to PagePublicReadService (see that class for index docs)
 *
 * saveDraft():
 *   → PageDraft: where('page_id', $id) + whereIn('status', [...]) + latest()
 *   → Covered by idx_page_draft_lookup: (page_id, status, updated_at)
 *     from migration 2026_04_30_000002_add_composite_performance_indexes
 *
 * publish():
 *   → Page: find($pageId) — primary key lookup, always indexed
 *   → PageDraft: where('page_id', $id) + whereIn('status', [...]) + latest('updated_at')
 *   → Covered by idx_page_draft_lookup
 *
 * updateTranslation():
 *   → PageTranslation: updateOrCreate(['page_id' => ..., 'locale' => ...], ...)
 *   → page_translations has UNIQUE(page_id, locale) — fully covered
 *
 * updateSeo():
 *   → PageSeoMeta: updateOrCreate(['page_id' => ..., 'locale' => ...], ...)
 *   → page_seo_meta has UNIQUE(page_id, locale) — fully covered
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

    public function updateBaseMetadata(int $pageId, PageMetadataDTO $payload, ?int $userId = null): bool
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
            $this->auditService->log('page.updated', $userId, Page::class, (int) $page->getKey(), [
                'field' => 'metadata',
            ]);
        }

        return $updated;
    }

    public function updateArabicTranslation(int $pageId, PageTranslationDTO $payload, ?int $userId = null): bool
    {
        return $this->updateTranslation($pageId, 'ar', $payload, $userId);
    }

    public function updateEnglishTranslation(int $pageId, PageTranslationDTO $payload, ?int $userId = null): bool
    {
        return $this->updateTranslation($pageId, 'en', $payload, $userId);
    }

    public function updateArabicSeo(int $pageId, PageSeoInputDTO $payload, ?int $userId = null): bool
    {
        return $this->updateSeo($pageId, 'ar', $payload, $userId);
    }

    public function updateEnglishSeo(int $pageId, PageSeoInputDTO $payload, ?int $userId = null): bool
    {
        return $this->updateSeo($pageId, 'en', $payload, $userId);
    }

    public function saveDraft(int $pageId, PageDraftDataDTO $payload, int $userId, ?int $expectedVersion = null): PageDraftDTO
    {
        $page = Page::query()->findOrFail($pageId);

        // Optimistic locking: check version if expectedVersion is provided
        if ($expectedVersion !== null) {
            $currentDraft = PageDraft::query()
                ->where('page_id', $pageId)
                ->whereIn('status', self::EDITABLE_STATUSES)
                ->latest()
                ->first();

            if ($currentDraft instanceof PageDraft && (int) $currentDraft->version !== $expectedVersion) {
                $this->auditService->log(
                    action: 'draft.conflict',
                    userId: $userId,
                    entityType: Page::class,
                    entityId: $pageId,
                    metadata: [
                        'expected_version' => $expectedVersion,
                        'actual_version' => (int) $currentDraft->version,
                        'draft_id' => (int) $currentDraft->getKey(),
                    ],
                );

                DraftConflictDetected::dispatch(
                    PageDraft::class,
                    (int) $currentDraft->getKey(),
                    $expectedVersion,
                    (int) $currentDraft->version,
                    $userId,
                );

                throw new ConflictException(
                    'Draft has been modified by another editor.',
                    (int) $currentDraft->version,
                );
            }
        }

        // Determine the next version number
        $latestDraft = PageDraft::query()
            ->where('page_id', $pageId)
            ->whereIn('status', self::EDITABLE_STATUSES)
            ->latest()
            ->first();
        $nextVersion = $latestDraft instanceof PageDraft ? ((int) $latestDraft->version) + 1 : 1;

        $draft = PageDraft::query()->create([
            'page_id' => $pageId,
            'payload_json' => $this->draftService->pageDraftPayloadToArray($payload),
            'status' => $payload->metadata->status,
            'created_by' => $userId,
            'updated_by' => $userId,
            'scheduled_at' => $payload->metadata->publishAt,
            'version' => $nextVersion,
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
                    fn (int $id, string $locale, PageTranslationDTO $dto): bool => $this->updateTranslation($id, $locale, $dto, $userId),
                    fn (int $id, string $locale, PageSeoInputDTO $dto): bool => $this->updateSeo($id, $locale, $dto, $userId),
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

    private function updateTranslation(int $pageId, string $locale, PageTranslationDTO $payload, ?int $userId = null): bool
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
                'cta_payload' => $this->sanitizeUrlPayload($payload->ctaPayload),
                'sidebar_payload' => $payload->sidebarPayload,
                'excerpt' => $payload->excerpt,
                'body' => $sanitizedBody,
                'raw_excerpt' => $payload->rawExcerpt,
                'meta_title_fallback' => $payload->metaTitleFallback,
            ],
        );

        $this->touchPageCaches((int) $page->getKey());
        $this->auditService->log('page.updated', $userId, Page::class, (int) $page->getKey(), [
            'field' => 'translation',
            'locale' => $locale,
        ]);

        return $translation->exists;
    }

    private function updateSeo(int $pageId, string $locale, PageSeoInputDTO $payload, ?int $userId = null): bool
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
                'og_image_url' => UrlSanitizer::sanitize($payload->ogImage, ['http', 'https'], true),
                'canonical_url' => UrlSanitizer::sanitize($payload->canonicalUrl, ['http', 'https'], false),
                'robots' => $payload->robots,
            ],
        );

        $this->touchPageCaches((int) $page->getKey());
        $this->auditService->log('page.updated', $userId, Page::class, (int) $page->getKey(), [
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
        $this->cacheService->flushTags(['pages', 'public-pages', 'public-shell', 'seo', 'sitemap', 'navigation', 'settings']);
    }

    /** @param  array<string, mixed>|null  $payload */
    private function sanitizeUrlPayload(?array $payload): ?array
    {
        if ($payload === null) {
            return null;
        }

        if (isset($payload['url']) && is_string($payload['url'])) {
            $payload['url'] = UrlSanitizer::sanitize($payload['url']);
        }

        return $payload;
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
