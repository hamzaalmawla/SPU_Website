<?php

declare(strict_types=1);

namespace App\Policies;

use App\Contracts\Auth\AdminAccessServiceInterface;
use App\Models\Faculty\FacultySubpageCard;
use App\Models\User\User;

/**
 * Authorizes faculty sub-page cards through the `faculty_subpage_cards.*`
 * permissions, inside the user's faculty limit.
 */
final class FacultySubpageCardPolicy
{
    public function __construct(
        private readonly AdminAccessServiceInterface $access,
    ) {}

    public function viewAny(User $user): bool
    {
        return $this->access->can($user, 'faculty_subpage_cards.view');
    }

    public function view(User $user, FacultySubpageCard $card): bool
    {
        return $this->allowed($user, 'faculty_subpage_cards.view', $card);
    }

    public function create(User $user): bool
    {
        return $this->access->can($user, 'faculty_subpage_cards.create');
    }

    public function update(User $user, FacultySubpageCard $card): bool
    {
        return $this->allowed($user, 'faculty_subpage_cards.update', $card);
    }

    public function publish(User $user, FacultySubpageCard $card): bool
    {
        return $this->allowed($user, 'faculty_subpage_cards.publish', $card);
    }

    public function delete(User $user, FacultySubpageCard $card): bool
    {
        return $this->allowed($user, 'faculty_subpage_cards.delete', $card);
    }

    private function allowed(User $user, string $permission, FacultySubpageCard $card): bool
    {
        return $this->access->can($user, $permission)
            && $this->access->withinFacultyLimit($user, (string) $card->faculty_slug);
    }
}
