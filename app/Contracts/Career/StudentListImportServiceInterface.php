<?php

declare(strict_types=1);

namespace App\Contracts\Career;

use App\DTOs\Career\StudentListImportChoicesDTO;
use App\DTOs\Career\StudentListImportPreviewDTO;
use App\DTOs\Career\StudentListImportResultDTO;
use App\DTOs\Career\StudentListImportSummaryDTO;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Collection;
use InvalidArgumentException;

/**
 * Imports a faculty council decision (.docx) into the faculty's graduates
 * (`alumni`) or top students (`honor_students`).
 *
 * The editor uploads the file, sees a preview, confirms the semester and the
 * departments, and the rows go live at once. Students are matched on their
 * university ID, so importing the same file again updates instead of adding.
 * Every import is audited and can be undone.
 *
 * Uploaded files live on the private `local` disk under UPLOAD_DIRECTORY and
 * are only accepted from there. Failures throw InvalidArgumentException whose
 * message is a translation key under `student_lists.errors`.
 */
interface StudentListImportServiceInterface
{
    public const UPLOAD_DIRECTORY = 'student-list-imports';

    public const KIND_ALUMNI = 'alumni';

    public const KIND_HONOR_STUDENTS = 'honor_students';

    /** Whether the user may import into and undo imports of this faculty's list. */
    public function canImport(Authenticatable $user, string $facultySlug, string $kind): bool;

    /**
     * Reads the uploaded file. With choices, the preview reflects them (the
     * semester changes which top students already exist, departments change
     * the names shown).
     *
     * @throws InvalidArgumentException
     */
    public function preview(string $facultySlug, string $kind, string $storedPath, string $originalName, ?StudentListImportChoicesDTO $choices = null): StudentListImportPreviewDTO;

    /**
     * Writes the rows, records the import, flushes the public caches and
     * deletes the uploaded file.
     *
     * @throws InvalidArgumentException
     */
    public function import(string $facultySlug, string $kind, string $storedPath, string $originalName, StudentListImportChoicesDTO $choices, Authenticatable $user): StudentListImportResultDTO;

    /**
     * Removes the rows an import created and restores the rows it updated.
     *
     * @throws InvalidArgumentException
     */
    public function undo(int $importId, Authenticatable $user): bool;

    /** @return Collection<int, StudentListImportSummaryDTO> newest first */
    public function history(string $facultySlug, string $kind, int $limit = 20): Collection;

    /** Deletes an uploaded file that will not be imported. */
    public function discardUpload(string $storedPath): void;
}
