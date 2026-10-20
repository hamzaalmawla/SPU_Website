<?php

declare(strict_types=1);

namespace App\DTOs\Staff;

final readonly class StaffIntakeRequestDetailDTO
{
    /**
     * @param  list<array{field: string, before: string, after: string}>  $changes
     * @param  list<array{id: int, name: string, faculty: string, sameFaculty: bool}>  $candidates
     * @param  array{id: int, name: string}|null  $claimedMember
     * @param  array{id: int, name: string, faculty: string}|null  $relatedMember
     * @param  list<array{event: string, name: ?string, changedFields: list<string>, ip: ?string, userAgent: ?string, at: string}>  $accessLog
     */
    public function __construct(
        public int $id,
        public string $reference,
        public string $status,
        public string $type,
        public string $facultyName,
        public string $submitterName,
        public string $submittedAt,
        public bool $possibleDuplicate,
        public array $changes,
        public array $candidates,
        public ?array $claimedMember,
        public ?array $relatedMember,
        public ?string $linkedPersonSlug,
        public bool $hasNewPhoto,
        public bool $hasNewCv,
        public ?string $reviewerName,
        public ?string $reviewedAt,
        public ?string $reviewNote,
        public ?int $appliedMemberId,
        public array $accessLog,
    ) {}
}
