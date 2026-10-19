<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Form\FormDefinition;
use App\Models\User\User;

final class FormDefinitionPolicy
{
    public function viewAny(User $user): bool
    {
        return in_array($user->role_slug, ['super_admin', 'editor'], true);
    }

    public function create(User $user): bool
    {
        return $this->viewAny($user);
    }

    public function update(User $user, FormDefinition $form): bool
    {
        return $this->viewAny($user);
    }

    public function delete(User $user, FormDefinition $form): bool
    {
        return $this->viewAny($user);
    }
}
