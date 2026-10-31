<?php

declare(strict_types=1);

namespace App\Contracts\Staff;

use App\DTOs\Staff\StaffIntakeApprovalResultDTO;
use App\DTOs\Staff\StaffIntakeRequestDetailDTO;
use App\DTOs\Staff\StaffIntakeUploadDTO;

/**
 * Reviewing what staff sent through an intake link. Approval goes through
 * the same CMS draft and publish workflow as an editor's own change.
 */
interface StaffIntakeReviewServiceInterface
{
    /** @return list<int> Faculties whose requests and links the actor may see. */
    public function reviewableFacultyIds(int $actorId): array;

    public function pendingCount(int $actorId): int;

    /**
     * Read-only overview for the deploy log: each link with its request counts
     * by status and whether its faculty is enabled (requests of a disabled
     * faculty are hidden from every reviewer), and where each named reference
     * is. No submitter names.
     *
     * @param  list<string>  $references
     * @return array{links: list<array<string, mixed>>, orphans: int, references: list<array<string, mixed>>}
     */
    public function statusReport(array $references = []): array;

    /** Whether the actor may approve or reject this request. */
    public function canDecide(int $requestId, int $actorId): bool;

    public function detail(int $requestId, string $locale, int $actorId): StaffIntakeRequestDetailDTO;

    /**
     * Applies the request to the claimed profile, to $targetMemberId when the
     * reviewer picks another existing profile, or to a new profile. Publishes
     * when the actor may publish; otherwise leaves a draft for an editor.
     */
    public function approve(int $requestId, int $actorId, ?int $targetMemberId = null, ?string $note = null): StaffIntakeApprovalResultDTO;

    public function reject(int $requestId, int $actorId, ?string $note = null): bool;

    public function upload(int $requestId, string $kind, int $actorId): StaffIntakeUploadDTO;
}
