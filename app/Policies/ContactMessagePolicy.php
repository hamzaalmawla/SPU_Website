<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Contact\ContactMessage;
use App\Models\User\User;

final class ContactMessagePolicy
{
    public function before(User $user, string $ability): ?bool
    {
        return $user->role_slug === 'super_admin' ? true : null;
    }

    public function viewAny(User $user): bool
    {
        return $user->role_slug === 'editor';
    }

    public function view(User $user, ContactMessage $contactMessage): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, ContactMessage $contactMessage): bool
    {
        return in_array($user->role_slug, ['super_admin', 'editor'], true);
    }

    public function updateReview(User $user, ContactMessage $contactMessage): bool
    {
        return in_array($user->role_slug, ['super_admin', 'editor'], true);
    }

    public function delete(User $user, ContactMessage $contactMessage): bool
    {
        return in_array($user->role_slug, ['super_admin', 'editor'], true);
    }
}
