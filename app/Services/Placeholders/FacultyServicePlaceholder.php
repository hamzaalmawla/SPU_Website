<?php

declare(strict_types=1);

namespace App\Services\Placeholders;

use App\Contracts\FacultyServiceInterface;
use App\DTOs\FacultyDTO;
use App\DTOs\FacultyProfileWriteDTO;
use BadMethodCallException;
use Illuminate\Support\Collection;

/**
 * Placeholder implementation for faculty service contract.
 */
final class FacultyServicePlaceholder implements FacultyServiceInterface
{
    public function getAll(): Collection
    {
        throw new BadMethodCallException(__METHOD__.' is not implemented.');
    }

    public function findBySlug(string $slug): ?FacultyDTO
    {
        throw new BadMethodCallException(__METHOD__.' is not implemented.');
    }

    public function getDepartments(int|string $facultyId): Collection
    {
        throw new BadMethodCallException(__METHOD__.' is not implemented.');
    }

    public function getStaff(int|string $facultyId): Collection
    {
        throw new BadMethodCallException(__METHOD__.' is not implemented.');
    }

    public function updateProfile(int|string $facultyId, FacultyProfileWriteDTO $data): bool
    {
        throw new BadMethodCallException(__METHOD__.' is not implemented.');
    }
}
