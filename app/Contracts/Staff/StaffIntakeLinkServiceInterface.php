<?php

declare(strict_types=1);

namespace App\Contracts\Staff;

use App\DTOs\Staff\StaffIntakeLinkCreatedDTO;
use App\DTOs\Staff\StaffIntakeLinkInputDTO;

/**
 * Makes, rotates and revokes the links sent to a faculty's academic staff, or
 * to a center's staff (config/centers.php). Faculty links need the
 * staff_intake_links permissions, within the user's faculty limit; center
 * links need center_staff_intake.
 */
interface StaffIntakeLinkServiceInterface
{
    public function create(StaffIntakeLinkInputDTO $input, int $actorId): StaffIntakeLinkCreatedDTO;

    /** A new address for the link; the old one stops working at once. */
    public function rotate(int $linkId, int $actorId): StaffIntakeLinkCreatedDTO;

    /**
     * The link's current address, for whoever manages it. Null for links made
     * before addresses were kept: those need a new address (rotate).
     */
    public function address(int $linkId, int $actorId): ?StaffIntakeLinkCreatedDTO;

    public function revoke(int $linkId, int $actorId): bool;

    /**
     * Faculties where the actor holds a staff intake permission, by id:
     * all faculties, or only theirs when their access is limited to faculties.
     *
     * @return array<int, string>
     */
    public function facultyOptions(int $actorId, string $permission = 'staff_intake_links.view'): array;

    public function canManage(int $linkId, int $actorId): bool;

    /** Whether the actor holds center_staff_intake.<action> (center links and requests). */
    public function canUseCenters(int $actorId, string $action = 'view'): bool;

    /**
     * The form fields a link of this kind may make required, in form order.
     *
     * @return list<string>
     */
    public function requirableFields(bool $isCenter): array;

    /**
     * The fields a new link of this kind requires unless it picks its own.
     *
     * @return list<string>
     */
    public function defaultRequiredFields(bool $isCenter): array;

    /**
     * A link's required fields from what it stored (the defaults when it stored none).
     *
     * @return list<string>
     */
    public function requiredFields(mixed $stored, bool $isCenter): array;
}
