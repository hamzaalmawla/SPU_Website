<?php

declare(strict_types=1);

namespace App\DTOs\Form;

use App\Enums\FormSubmissionInbox;
use App\Enums\FormSubmissionStatus;

/**
 * @param  list<DynamicFormSubmissionDetailSectionDTO>  $sections
 * @param  list<DynamicFormSubmissionAttachmentDTO>  $attachments
 */
final readonly class DynamicFormSubmissionDetailDTO
{
    public function __construct(
        public int $id,
        public string $formId,
        public string $formLabel,
        public ?FormSubmissionInbox $inbox,
        public string $inboxLabel,
        public ?FormSubmissionStatus $status,
        public string $rawStatus,
        public string $statusLabel,
        public string $submissionLocale,
        public ?string $applicantName,
        public ?string $applicantEmail,
        public string $submittedAt,
        public array $sections,
        public array $attachments,
    ) {}
}
