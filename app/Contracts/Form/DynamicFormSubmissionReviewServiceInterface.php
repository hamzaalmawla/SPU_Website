<?php

declare(strict_types=1);

namespace App\Contracts\Form;

use App\DTOs\Form\DynamicFormSubmissionDetailDTO;
use App\DTOs\Form\SecureFormSubmissionDownloadDTO;
use App\Enums\FormSubmissionStatus;

interface DynamicFormSubmissionReviewServiceInterface
{
    public function getDetails(int $submissionId, string $adminLocale, int $actorId): DynamicFormSubmissionDetailDTO;

    public function transitionStatus(
        int $submissionId,
        FormSubmissionStatus $expectedStatus,
        FormSubmissionStatus $newStatus,
        int $actorId,
        ?string $reason = null,
    ): bool;

    public function markAsRead(int $submissionId, int $actorId): bool;

    public function markAsUnread(int $submissionId, int $actorId): bool;

    public function assign(int $submissionId, ?int $assigneeId, int $actorId): bool;

    public function updateInternalNotes(int $submissionId, ?string $notes, int $actorId): bool;

    public function resolveAttachment(
        int $submissionId,
        string $field,
        int $actorId,
    ): SecureFormSubmissionDownloadDTO;
}
