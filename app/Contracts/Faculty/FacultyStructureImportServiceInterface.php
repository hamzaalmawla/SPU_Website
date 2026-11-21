<?php

declare(strict_types=1);

namespace App\Contracts\Faculty;

use App\DTOs\Faculty\FacultyStructureImportResultDTO;

interface FacultyStructureImportServiceInterface
{
    /**
     * Replace the faculties' departments and labs with the ones in the data file
     * and make sure the subpages it lists exist and have a visible card. Without
     * $write nothing is stored and the result lists what would change.
     *
     * @param  array<string, mixed>  $source
     */
    public function import(array $source, bool $write): FacultyStructureImportResultDTO;
}
