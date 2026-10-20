<?php

declare(strict_types=1);

namespace App\Policies;

use App\Contracts\Auth\AdminAccessServiceInterface;
use App\Models\Contact\ContactMessage;
use App\Models\User\User;

/**
 * Messages arrive from the public form, so nobody creates them here.
 */
final class ContactMessagePolicy
{
    public function __construct(
        private readonly AdminAccessServiceInterface $access,
    ) {}

    public function viewAny(User $user): bool
    {
        return $this->access->can($user, 'contact_messages.view');
    }

    public function view(User $user, ContactMessage $contactMessage): bool
    {
        return $this->access->can($user, 'contact_messages.view');
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, ContactMessage $contactMessage): bool
    {
        return $this->access->can($user, 'contact_messages.update');
    }

    public function updateReview(User $user, ContactMessage $contactMessage): bool
    {
        return $this->access->can($user, 'contact_messages.update');
    }

    public function delete(User $user, ContactMessage $contactMessage): bool
    {
        return $this->access->can($user, 'contact_messages.delete');
    }
}
