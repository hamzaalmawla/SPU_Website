<?php

declare(strict_types=1);

namespace App\Contracts\Cms;

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

    public function schedule(string $targetKey, DateTimeInterface $publishAt, int $userId): bool;

    public function unpublish(string $targetKey, int $userId): bool;

    /** @param array<string, mixed>|null $payload */
    public function readiness(string $targetKey, ?array $payload = null): CmsPublishReadinessDTO;

    public function latestEditableDraftVersion(string $targetKey, int $userId): ?int;

    /** @return array<string, mixed>|null */
    public function latestEditableDraftPayload(string $targetKey, int $userId): ?array;

    /** @return array<string, mixed>|null */
    public function getPublishedPayload(string $targetKey): ?array;

    public function publishDueScheduled(): int;
}
