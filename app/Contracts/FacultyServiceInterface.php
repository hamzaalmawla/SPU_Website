<?php

declare(strict_types=1);

namespace App\Contracts;

use App\DTOs\DepartmentDTO;
use App\DTOs\FacultyDTO;
use App\DTOs\FacultyProfileWriteDTO;
use App\DTOs\StaffDTO;
use Illuminate\Support\Collection;

/**
 * Defines faculty retrieval and profile management operations.
 */
interface FacultyServiceInterface
{
    /**
     * Retrieve all faculties.
     *
     * @return Collection<int, FacultyDTO>
     */
    public function getAll(): Collection;

    /**
     * Find a faculty by its slug.
     */
    public function findBySlug(string $slug): ?FacultyDTO;

    /**
     * Retrieve all departments for a faculty.
     *
     * @return Collection<int, DepartmentDTO>
     */
    public function getDepartments(int|string $facultyId): Collection;

    /**
     * Retrieve all staff members for a faculty.
     *
     * @return Collection<int, StaffDTO>
     */
    public function getStaff(int|string $facultyId): Collection;

    /**
     * Update a faculty profile.
     */
    public function updateProfile(int|string $facultyId, FacultyProfileWriteDTO $data): bool;
}
