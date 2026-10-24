<?php

declare(strict_types=1);

namespace App\Services\Placeholders;

use App\Contracts\Page\PageServiceInterface;
use App\DTOs\Page\PageDraftDataDTO;
use App\DTOs\Page\PageDraftDTO;
use App\DTOs\Page\PageDTO;
use App\DTOs\Page\PageMetadataDTO;
use App\DTOs\Page\PageShellDataDTO;
use App\DTOs\Page\PageTranslationDTO;
use App\DTOs\Preview\PreviewDTO;
use App\DTOs\Seo\PageSeoInputDTO;
use App\DTOs\Shared\BreadcrumbTrailDTO;
use BadMethodCallException;
use DateTimeInterface;

/**
 * Placeholder implementation for the landing-page service contract.
 *
 * When replaced, the real service must preserve the documented read precedence:
 * localized page_translations content first, pages.content_json only as non-localized shell data.
 */
final class PageServicePlaceholder implements PageServiceInterface
{
    public function createPageShell(PageShellDataDTO $payload, int $userId): PageDTO
    {
        throw new BadMethodCallException(__METHOD__.' is not implemented.');
    }

    public function updateBaseMetadata(int $pageId, PageMetadataDTO $payload, int $userId): bool
    {
        throw new BadMethodCallException(__METHOD__.' is not implemented.');
    }

    public function updateArabicTranslation(int $pageId, PageTranslationDTO $payload, int $userId): bool
    {
        throw new BadMethodCallException(__METHOD__.' is not implemented.');
    }

    public function updateEnglishTranslation(int $pageId, PageTranslationDTO $payload, int $userId): bool
    {
        throw new BadMethodCallException(__METHOD__.' is not implemented.');
    }

    public function updateArabicSeo(int $pageId, PageSeoInputDTO $payload, int $userId): bool
    {
        throw new BadMethodCallException(__METHOD__.' is not implemented.');
    }

    public function updateEnglishSeo(int $pageId, PageSeoInputDTO $payload, int $userId): bool
    {
        throw new BadMethodCallException(__METHOD__.' is not implemented.');
    }

    public function saveDraft(int $pageId, PageDraftDataDTO $payload, int $userId, ?int $expectedVersion = null): PageDraftDTO
    {
        throw new BadMethodCallException(__METHOD__.' is not implemented.');
    }

    public function publish(int $pageId, int $userId): bool
    {
        throw new BadMethodCallException(__METHOD__.' is not implemented.');
    }

    public function unpublish(int $pageId, int $userId): bool
    {
        throw new BadMethodCallException(__METHOD__.' is not implemented.');
    }

    public function schedulePublish(int $pageId, DateTimeInterface $publishAt, int $userId): bool
    {
        throw new BadMethodCallException(__METHOD__.' is not implemented.');
    }

    public function publishDueScheduled(): int
    {
        throw new BadMethodCallException(__METHOD__.' is not implemented.');
    }

    public function getPublicPageBySlug(string $slug, string $locale): ?PageDTO
    {
        throw new BadMethodCallException(__METHOD__.' is not implemented.');
    }

    public function getAdminEditorPayload(int $pageId): PageDTO
    {
        throw new BadMethodCallException(__METHOD__.' is not implemented.');
    }

    public function buildBreadcrumbPayload(int $pageId, string $locale): BreadcrumbTrailDTO
    {
        throw new BadMethodCallException(__METHOD__.' is not implemented.');
    }

    public function buildPreviewPayload(int $pageId, string $locale): PreviewDTO
    {
        throw new BadMethodCallException(__METHOD__.' is not implemented.');
    }

    public function buildPreviewPayloadFromSnapshot(int $pageId, array $snapshot, string $locale): PreviewDTO
    {
        throw new BadMethodCallException(__METHOD__.' is not implemented.');
    }

    public function resolveLanguageSwitchTargetUrl(int $pageId, string $targetLocale): ?string
    {
        throw new BadMethodCallException(__METHOD__.' is not implemented.');
    }

    public function latestEditableDraftVersion(int $pageId): ?int
    {
        throw new BadMethodCallException(__METHOD__.' is not implemented.');
    }
}
