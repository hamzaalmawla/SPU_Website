<?php

declare(strict_types=1);

namespace App\Policies;

use App\Contracts\Auth\AdminAccessServiceInterface;
use App\Contracts\Form\FormDefinitionServiceInterface;
use App\Enums\FormSubmissionInbox;
use App\Models\Form\DynamicFormSubmission;
use App\Models\User\User;

/**
 * `form_submissions.*` covers every inbox; `job_applications.*` covers only
 * submissions of forms whose inbox is the jobs inbox. Submissions come from
 * public forms, so nobody creates, edits or deletes them here.
 */
final class DynamicFormSubmissionPolicy
{
    public function __construct(
        private readonly AdminAccessServiceInterface $access,
        private readonly FormDefinitionServiceInterface $forms,
    ) {}

    public function viewAny(User $user): bool
    {
        return $this->access->canAny($user, ['form_submissions.view', 'job_applications.view']);
    }

    public function view(User $user, DynamicFormSubmission $dynamicFormSubmission): bool
    {
        return $this->allowed($user, 'view', $dynamicFormSubmission);
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, DynamicFormSubmission $dynamicFormSubmission): bool
    {
        return false;
    }

    public function delete(User $user, DynamicFormSubmission $dynamicFormSubmission): bool
    {
        return false;
    }

    public function transitionStatus(User $user, DynamicFormSubmission $dynamicFormSubmission): bool
    {
        return $this->allowed($user, 'update', $dynamicFormSubmission);
    }

    public function updateReview(User $user, DynamicFormSubmission $dynamicFormSubmission): bool
    {
        return $this->allowed($user, 'update', $dynamicFormSubmission);
    }

    public function downloadAttachment(User $user, DynamicFormSubmission $dynamicFormSubmission): bool
    {
        return $this->allowed($user, 'update', $dynamicFormSubmission);
    }

    private function allowed(User $user, string $action, DynamicFormSubmission $submission): bool
    {
        return $this->access->can($user, 'form_submissions.'.$action)
            || ($this->access->can($user, 'job_applications.'.$action) && $this->isJobApplication($submission));
    }

    private function isJobApplication(DynamicFormSubmission $submission): bool
    {
        return $this->forms->inboxFor((string) $submission->form_id) === FormSubmissionInbox::JOBS;
    }
}
