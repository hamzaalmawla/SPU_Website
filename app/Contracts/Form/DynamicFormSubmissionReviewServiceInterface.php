<?php

declare(strict_types=1);

namespace App\Contracts\Form;

use App\DTOs\Form\DynamicFormSubmissionDetailDTO;
use App\DTOs\Form\SecureFormSubmissionDownloadDTO;
use App\Enums\FormSubmissionStatus;

interface DynamicFormSubmissionReviewServiceInterface
{
    public function getDetails(int $submissionId, string $adminLocale): DynamicFormSubmissionDetailDTO;

    public function transitionStatus(
        int $submissionId,
        FormSubmissionStatus $expectedStatus,
        FormSubmissionStatus $newStatus,
        int $actorId,
    ): bool;

    public function resolveAttachment(
        int $submissionId,
        string $field,
        int $actorId,
    ): SecureFormSubmissionDownloadDTO;
}
