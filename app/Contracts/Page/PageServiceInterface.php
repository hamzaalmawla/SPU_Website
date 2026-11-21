<?php

declare(strict_types=1);

namespace App\Contracts\Page;

use App\DTOs\Page\PageDraftDataDTO;
use App\DTOs\Page\PageDraftDTO;
use App\DTOs\Page\PageDTO;
use App\DTOs\Page\PageMetadataDTO;
use App\DTOs\Page\PageShellDataDTO;
use App\DTOs\Page\PageTranslationDTO;
use App\DTOs\Preview\PreviewDTO;
use App\DTOs\Seo\PageSeoInputDTO;
use App\DTOs\Shared\BreadcrumbTrailDTO;
use DateTimeInterface;

/**
 * Defines service-layer operations for bilingual landing-page shells.
 *
 * Current precedence rule for runtime reads:
 * - localized page_translations payload/body fields are the authoritative source for
 *   locale-specific page content
 * - pages.content_json is shell-level, non-localized supplemental data and must not
 *   displace localized translation content unless a later page type explicitly documents it
 */
interface PageServiceInterface
{
    /** @return list<int> */
    public function invalidParentIds(int $pageId): array;

    /**
     * Create a page shell used by the homepage and top-level landing pages.
     */
    public function createPageShell(PageShellDataDTO $payload, int $userId): PageDTO;

    /**
     * Update non-translatable page metadata.
     */
    public function updateBaseMetadata(int $pageId, PageMetadataDTO $payload, int $userId): bool;

    /**
     * Update Arabic page translation payload.
     */
    public function updateArabicTranslation(int $pageId, PageTranslationDTO $payload, int $userId): bool;

    /**
     * Update English page translation payload.
     */
    public function updateEnglishTranslation(int $pageId, PageTranslationDTO $payload, int $userId): bool;

    /**
     * Update Arabic SEO payload.
     */
    public function updateArabicSeo(int $pageId, PageSeoInputDTO $payload, int $userId): bool;

    /**
     * Update English SEO payload.
     */
    public function updateEnglishSeo(int $pageId, PageSeoInputDTO $payload, int $userId): bool;

    /**
     * Save a draft snapshot for the page editor.
     *
     * @param  int|null  $expectedVersion  When provided, the service checks the current draft version
     *                                     and throws ConflictException on mismatch (optimistic locking).
     */
    public function saveDraft(int $pageId, PageDraftDataDTO $payload, int $userId, ?int $expectedVersion = null): PageDraftDTO;

    /**
     * Publish a page.
     */
    public function publish(int $pageId, int $userId): bool;

    /**
     * Unpublish a page.
     */
    public function unpublish(int $pageId, int $userId): bool;

    /**
     * Schedule a page publication.
     */
    public function schedulePublish(int $pageId, DateTimeInterface $publishAt, int $userId): bool;

    /**
     * Publish all due scheduled pages.
     */
    public function publishDueScheduled(): int;

    /**
     * Retrieve the public localized page by slug using the documented content precedence rule.
     */
    public function getPublicPageBySlug(string $slug, string $locale): ?PageDTO;

    /**
     * Retrieve admin editor payload for a page with both shell-level and localized content sources explicit.
     */
    public function getAdminEditorPayload(int $pageId): PageDTO;

    /**
     * Build breadcrumb payload for a page.
     */
    public function buildBreadcrumbPayload(int $pageId, string $locale): BreadcrumbTrailDTO;

    /**
     * Build preview payload for a page.
     */
    public function buildPreviewPayload(int $pageId, string $locale): PreviewDTO;

    /**
     * Build preview payload for a specific draft snapshot.
     *
     * @param  array<string, mixed>  $snapshot
     */
    public function buildPreviewPayloadFromSnapshot(int $pageId, array $snapshot, string $locale): PreviewDTO;

    /**
     * Resolve the language-switch target URL for a page.
     */
    public function resolveLanguageSwitchTargetUrl(int $pageId, string $targetLocale): ?string;

    /**
     * Return the latest editable draft version for optimistic locking.
     */
    public function latestEditableDraftVersion(int $pageId): ?int;
}
