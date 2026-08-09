<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Form\DynamicFormSubmission;
use App\Models\User\User;

final class DynamicFormSubmissionPolicy
{
    public function viewAny(User $user): bool
    {
        return in_array($user->role_slug, ['super_admin', 'editor', 'hr'], true);
    }

    public function view(User $user, DynamicFormSubmission $dynamicFormSubmission): bool
    {
        return $this->viewAny($user) && $this->canReviewSubmission($user, $dynamicFormSubmission);
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
        return in_array($user->role_slug, ['super_admin', 'editor'], true)
            || ($user->role_slug === 'hr' && $this->isJobApplication($dynamicFormSubmission));
    }

    public function updateReview(User $user, DynamicFormSubmission $dynamicFormSubmission): bool
    {
        return in_array($user->role_slug, ['super_admin', 'editor'], true)
            || ($user->role_slug === 'hr' && $this->isJobApplication($dynamicFormSubmission));
    }

    public function downloadAttachment(User $user, DynamicFormSubmission $dynamicFormSubmission): bool
    {
        return in_array($user->role_slug, ['super_admin', 'editor'], true)
            || ($user->role_slug === 'hr' && $this->isJobApplication($dynamicFormSubmission));
    }

    private function canReviewSubmission(User $user, DynamicFormSubmission $submission): bool
    {
        return $user->role_slug !== 'hr' || $this->isJobApplication($submission);
    }

    private function isJobApplication(DynamicFormSubmission $submission): bool
    {
        return $submission->form_id === 'job-application';
    }
}
