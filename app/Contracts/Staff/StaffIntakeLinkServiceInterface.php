<?php

declare(strict_types=1);

namespace App\Contracts\Staff;

use App\DTOs\Staff\StaffIntakeLinkCreatedDTO;
use App\DTOs\Staff\StaffIntakeLinkInputDTO;

/**
 * Makes, rotates and revokes the per-faculty links sent to academic staff.
 * A faculty editor manages links of their own faculties; editors and super
 * admins manage any.
 */
interface StaffIntakeLinkServiceInterface
{
    public function create(StaffIntakeLinkInputDTO $input, int $actorId): StaffIntakeLinkCreatedDTO;

    /** A new address for the link; the old one stops working at once. */
    public function rotate(int $linkId, int $actorId): StaffIntakeLinkCreatedDTO;

    public function revoke(int $linkId, int $actorId): bool;

    /** @return array<int, string> Faculties the actor may make links for, by id. */
    public function facultyOptions(int $actorId): array;

    public function canManage(int $linkId, int $actorId): bool;
}
