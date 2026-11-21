<?php

declare(strict_types=1);

namespace App\Contracts\Career;

use App\DTOs\Career\GraduationGradeDTO;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Collection;

/**
 * The graduate grades (التقدير) list, shared by every faculty's Alumni page and
 * the Alumni directory. A graduate's record (`alumni.grade`) and an Alumni page
 * item (`gradeKey`) hold a grade's key; names are read from here in each
 * language, so renaming a grade renames it everywhere.
 *
 * Super admins manage the list (permission component `graduation_grades`, held
 * by no default role). Deleting a grade moves its graduates to another grade or
 * leaves them with none; the deleted grade's key keeps pointing at the grade it
 * was moved to, so pages saved before the delete read the same.
 */
interface GraduationGradeServiceInterface
{
    public const PERMISSION_COMPONENT = 'graduation_grades';

    /** @return Collection<int, GraduationGradeDTO> the grades in their order */
    public function all(): Collection;

    public function find(string $key): ?GraduationGradeDTO;

    /** @return array<string, string> key => "امتياز / Excellent", for admin dropdowns */
    public function options(): array;

    /** The grade a stored key stands for now (following deletes), or null. */
    public function resolveKey(?string $key): ?string;

    /** The grade's name in that language, following deletes; null for no grade. */
    public function label(?string $key, string $locale): ?string;

    /** The grade whose Arabic or English name this text is, or null. */
    public function keyForText(?string $text): ?string;

    /**
     * The grade an Alumni page item shows: its own `gradeKey` when it has one
     * (empty means no grade), else its graduate record's grade, else a grade
     * name an older page saved as text.
     *
     * @param  array<string, mixed>  $item
     */
    public function itemGradeKey(array $item, ?string $recordGrade): ?string;

    /**
     * @param  list<int>  $alumniIds
     * @return array<int, string> alumni id => grade key, for records with a grade
     */
    public function recordGrades(array $alumniIds): array;

    /** @return array<string, list<string>> key => names a decision may print, for imports */
    public function importNames(): array;

    /** How many graduates have this grade (records and page entries with no record). */
    public function usageCount(string $key): int;

    /** $action is view, create, update or delete. */
    public function canManage(Authenticatable $user, string $action = 'view'): bool;

    public function create(string $nameAr, string $nameEn, int $userId): GraduationGradeDTO;

    public function update(string $key, string $nameAr, string $nameEn, int $userId): GraduationGradeDTO;

    /** @param list<string> $keys the grades in their new order */
    public function reorder(array $keys, int $userId): void;

    /**
     * Deletes a grade. Its graduates move to $moveToKey, or have no grade when it is null.
     *
     * @return int how many graduate records were moved or cleared
     */
    public function delete(string $key, ?string $moveToKey, int $userId): int;

    /**
     * On publishing a faculty's Alumni page, saves each item's grade on its
     * graduate record, so the Alumni directory shows the same grade.
     *
     * @param  array<string, mixed>  $payload
     */
    public function publishTarget(string $targetKey, array $payload): void;
}
