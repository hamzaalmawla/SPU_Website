<?php

declare(strict_types=1);

namespace App\Policies;

use App\Contracts\Auth\AdminAccessServiceInterface;
use App\Models\Form\FormDefinition;
use App\Models\User\User;

/**
 * Authorizes FormDefinition records through the `forms.*` permissions.
 */
final class FormDefinitionPolicy
{
    public function __construct(
        private readonly AdminAccessServiceInterface $access,
    ) {}

    public function viewAny(User $user): bool
    {
        return $this->access->can($user, 'forms.view');
    }

    public function view(User $user, FormDefinition $form): bool
    {
        return $this->access->can($user, 'forms.view');
    }

    public function create(User $user): bool
    {
        return $this->access->can($user, 'forms.create');
    }

    public function update(User $user, FormDefinition $form): bool
    {
        return $this->access->can($user, 'forms.update');
    }

    public function delete(User $user, FormDefinition $form): bool
    {
        return $this->access->can($user, 'forms.delete');
    }
}
