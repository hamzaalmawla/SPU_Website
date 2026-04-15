<?php

declare(strict_types=1);

namespace App\Contracts;

use App\DTOs\BreadcrumbTrailDTO;
use App\DTOs\HomepageDraftDTO;
use App\DTOs\PageDraftDataDTO;
use App\DTOs\PageDTO;
use App\DTOs\PageMetadataDTO;
use App\DTOs\PageSeoInputDTO;
use App\DTOs\PageShellDataDTO;
use App\DTOs\PageTranslationDTO;
use App\DTOs\PreviewDTO;
use DateTimeInterface;

/**
 * Defines service-layer operations for bilingual landing-page shells.
 */
interface PageServiceInterface
{
    /**
     * Create a page shell used by the homepage and top-level landing pages.
     */
    public function createPageShell(PageShellDataDTO $payload, int $userId): PageDTO;

    /**
     * Update non-translatable page metadata.
     */
    public function updateBaseMetadata(int $pageId, PageMetadataDTO $payload): bool;

    /**
     * Update Arabic page translation payload.
     */
    public function updateArabicTranslation(int $pageId, PageTranslationDTO $payload): bool;

    /**
     * Update English page translation payload.
     */
    public function updateEnglishTranslation(int $pageId, PageTranslationDTO $payload): bool;

    /**
     * Update Arabic SEO payload.
     */
    public function updateArabicSeo(int $pageId, PageSeoInputDTO $payload): bool;

    /**
     * Update English SEO payload.
     */
    public function updateEnglishSeo(int $pageId, PageSeoInputDTO $payload): bool;

    /**
     * Save a draft snapshot for the page editor using the shared draft workflow DTO.
     */
    public function saveDraft(int $pageId, PageDraftDataDTO $payload, int $userId): HomepageDraftDTO;

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
     * Retrieve the public localized page by slug.
     */
    public function getPublicPageBySlug(string $slug, string $locale): ?PageDTO;

    /**
     * Retrieve admin editor payload for a page.
     */
    public function getAdminEditorPayload(int $pageId): PageDTO;

    /**
     * Build breadcrumb payload for a page.
     */
    public function buildBreadcrumbPayload(int $pageId, string $locale): BreadcrumbTrailDTO;

    /**
     * Build preview payload for a page.
     */
    public function buildPreviewPayload(int $pageId, string $locale, string $device): PreviewDTO;

    /**
     * Resolve the language-switch target URL for a page.
     */
    public function resolveLanguageSwitchTargetUrl(int $pageId, string $targetLocale): ?string;
}
