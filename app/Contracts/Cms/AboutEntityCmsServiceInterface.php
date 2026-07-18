<?php

declare(strict_types=1);

namespace App\Contracts\Cms;

use App\DTOs\Cms\AboutEntityCmsDataDTO;
use App\DTOs\Cms\CmsTargetDTO;
use App\DTOs\Content\DirectorateDTO;
use App\DTOs\Content\PartnershipDTO;
use App\DTOs\Content\ProfilePageDTO;
use DateTimeInterface;

interface AboutEntityCmsServiceInterface
{
    public function prepareDraft(AboutEntityCmsDataDTO $data, int $userId): AboutEntityCmsDataDTO;

    public function getStoredData(string $targetKey): ?AboutEntityCmsDataDTO;

    public function resolveTarget(string $targetKey): ?CmsTargetDTO;

    /** @param array<string, mixed>|null $payload */
    public function authorizeTarget(string $targetKey, int $userId, ?array $payload = null): bool;

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, array<int, string>>
     */
    public function publishErrors(string $targetKey, array $payload): array;

    /** @param array<string, mixed> $payload */
    public function publishTarget(string $targetKey, array $payload, DateTimeInterface $publishedAt): bool;

    public function markDraft(string $targetKey): bool;

    public function markScheduled(string $targetKey): bool;

    public function unpublishTarget(string $targetKey): bool;

    /** @param array<string, mixed> $payload */
    public function buildPersonPreview(array $payload, string $locale): ?ProfilePageDTO;

    /** @param array<string, mixed> $payload */
    public function buildFacultyMemberPreview(array $payload, string $locale): ?ProfilePageDTO;

    /** @param array<string, mixed> $payload */
    public function buildDirectoratePreview(array $payload, string $locale): ?DirectorateDTO;

    /** @param array<string, mixed> $payload */
    public function buildPartnershipPreview(array $payload, string $locale): ?PartnershipDTO;
}
