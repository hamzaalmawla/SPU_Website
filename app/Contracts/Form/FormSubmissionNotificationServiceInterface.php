<?php

declare(strict_types=1);

namespace App\Contracts\Form;

use App\Enums\FormSubmissionStatus;

interface FormSubmissionNotificationServiceInterface
{
    public function queueDynamicReceived(int $submissionId): bool;

    public function queueDynamicStatusChanged(int $submissionId, FormSubmissionStatus $from, FormSubmissionStatus $to): bool;

    public function queueContactReceived(int $messageId): bool;

    public function queueContactStatusChanged(int $messageId, string $from, string $to): bool;
}
