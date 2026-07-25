<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Form\DynamicFormSubmission;
use App\Models\User\User;

final class DynamicFormSubmissionPolicy
{
    public function viewAny(User $user): bool
    {
        return in_array($user->role_slug, ['super_admin', 'editor'], true);
    }

    public function view(User $user, DynamicFormSubmission $dynamicFormSubmission): bool
    {
        return $this->viewAny($user);
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
        return in_array($user->role_slug, ['super_admin', 'editor'], true);
    }

    public function downloadAttachment(User $user, DynamicFormSubmission $dynamicFormSubmission): bool
    {
        return in_array($user->role_slug, ['super_admin', 'editor'], true);
    }
}
