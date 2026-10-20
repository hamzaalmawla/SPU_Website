<?php

declare(strict_types=1);

namespace App\Contracts\Staff;

use App\DTOs\Staff\StaffIntakeLinkCreatedDTO;
use App\DTOs\Staff\StaffIntakeLinkInputDTO;

/**
 * Makes, rotates and revokes the per-faculty links sent to academic staff.
 * Access comes from the staff_intake_links permissions, within the user's
 * faculty limit when they have one.
 */
interface StaffIntakeLinkServiceInterface
{
    public function create(StaffIntakeLinkInputDTO $input, int $actorId): StaffIntakeLinkCreatedDTO;

    /** A new address for the link; the old one stops working at once. */
    public function rotate(int $linkId, int $actorId): StaffIntakeLinkCreatedDTO;

    public function revoke(int $linkId, int $actorId): bool;

    /**
     * Faculties where the actor holds a staff intake permission, by id:
     * all faculties, or only theirs when their access is limited to faculties.
     *
     * @return array<int, string>
     */
    public function facultyOptions(int $actorId, string $permission = 'staff_intake_links.view'): array;

    public function canManage(int $linkId, int $actorId): bool;
}
