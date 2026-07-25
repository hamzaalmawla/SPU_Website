<?php

declare(strict_types=1);

namespace App\Contracts\News;

use App\DTOs\Cms\CmsTargetDTO;
use App\DTOs\News\NewsArticleCmsDataDTO;
use App\DTOs\News\NewsArticleDTO;
use DateTimeInterface;

interface NewsArticleCmsServiceInterface
{
    public function prepareDraft(NewsArticleCmsDataDTO $data, int $userId): NewsArticleCmsDataDTO;

    public function getStoredData(string $targetKey): ?NewsArticleCmsDataDTO;

    public function resolveTarget(string $targetKey): ?CmsTargetDTO;

    /** @param array<string, mixed>|null $payload */
    public function authorizeTarget(string $targetKey, int $userId, ?array $payload = null): bool;

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, array<int, string>>
     */
    public function publishErrors(string $targetKey, array $payload): array;

    /** @param array<string, mixed> $payload */
    public function publishTarget(string $targetKey, array $payload, DateTimeInterface $publishedAt, int $userId): bool;

    public function markDraft(string $targetKey): bool;

    public function markScheduled(string $targetKey): bool;

    public function unpublishTarget(string $targetKey): bool;

    /** @param array<string, mixed> $payload */
    public function buildPreview(array $payload, string $locale): ?NewsArticleDTO;
}
