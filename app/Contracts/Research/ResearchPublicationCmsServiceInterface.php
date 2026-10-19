<?php

declare(strict_types=1);

namespace App\Contracts\Research;

use App\DTOs\Cms\CmsTargetDTO;
use App\DTOs\Research\ResearcherDataDTO;
use App\DTOs\Research\ResearchPublicationCmsDataDTO;
use DateTimeInterface;

/**
 * Draft, publish and unpublish of database research publications, one CMS
 * target per row (`entity.research-publication.{id}`).
 */
interface ResearchPublicationCmsServiceInterface
{
    public function prepareDraft(ResearchPublicationCmsDataDTO $data, int $userId): ResearchPublicationCmsDataDTO;

    public function getStoredData(string $targetKey): ?ResearchPublicationCmsDataDTO;

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

    /** Soft-deletes the publication, so it leaves the site and every pick of it. */
    public function deletePublication(string $targetKey, int $userId): bool;

    /**
     * Faculty members a user may attribute a publication to, keyed by id.
     *
     * @return array<int, string>
     */
    public function facultyMemberOptions(int $userId, ?string $search = null, ?int $selectedId = null): array;

    /** @return array<string, string> Faculty public slugs keyed to names in the admin locale. */
    public function facultyFilterOptions(): array;

    /** @return array<int, string> Faculties keyed by id, named in the admin locale. */
    public function facultyOptions(): array;

    /** @return array<int, string> */
    public function yearFilterOptions(): array;

    /**
     * Registered researchers matching a name, keyed by id; with $selectedId,
     * just that researcher (to label a saved choice).
     *
     * @return array<int, string>
     */
    public function researcherOptions(?string $search = null, ?int $selectedId = null): array;

    /** Registers a researcher from the publication form and returns their id. */
    public function createResearcher(ResearcherDataDTO $data, int $userId): int;
}
