<?php

declare(strict_types=1);

namespace App\Contracts\Cms;

use App\DTOs\Cms\CmsBulkPublishResultDTO;
use App\DTOs\Cms\CmsDraftDTO;
use App\DTOs\Cms\CmsPreviewTokenDTO;
use App\DTOs\Cms\CmsPublishReadinessDTO;
use DateTimeInterface;

interface CmsWorkflowServiceInterface
{
    /** @param array<string, mixed> $payload */
    public function saveDraft(string $targetKey, array $payload, int $userId, ?int $expectedVersion = null): CmsDraftDTO;

    public function preview(string $targetKey, string $locale, int $userId, ?string $device = null): CmsPreviewTokenDTO;

    public function publish(string $targetKey, int $userId): bool;

    /**
     * Publishes the pending draft of several targets (an admin list's bulk
     * publish). The user must be allowed to publish every target, or nothing
     * is published. A target without a pending draft is left as it is, and
     * one whose draft is not ready is reported without stopping the others.
     *
     * @param  list<string>  $targetKeys
     */
    public function publishMany(array $targetKeys, int $userId): CmsBulkPublishResultDTO;

    public function schedule(string $targetKey, DateTimeInterface $publishAt, int $userId): bool;

    public function unpublish(string $targetKey, int $userId): bool;

    /** @param array<string, mixed>|null $payload */
    public function readiness(string $targetKey, ?array $payload = null): CmsPublishReadinessDTO;

    public function latestEditableDraftVersion(string $targetKey, int $userId): ?int;

    /** @return array<string, mixed>|null */
    public function latestEditableDraftPayload(string $targetKey, int $userId): ?array;

    /**
     * The draft the admin editor would open for this target, without the per-user
     * authorization of latestEditableDraftPayload(). For console reporting only.
     *
     * @return array{status: string, version: int, updated_at: string|null, payload: array<string, mixed>}|null
     */
    public function latestEditableDraftForReport(string $targetKey): ?array;

    /** @return array<string, mixed>|null */
    public function getPublishedPayload(string $targetKey): ?array;

    public function publishDueScheduled(): int;
}
