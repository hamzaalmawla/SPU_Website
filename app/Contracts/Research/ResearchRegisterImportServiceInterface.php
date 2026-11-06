<?php

declare(strict_types=1);

namespace App\Contracts\Research;

use App\DTOs\Research\ResearchRegisterImportResultDTO;

/**
 * Loads the research office's register (database/seeders/Data/research/
 * spu_sci_research.json) into the website: the papers the site does not have
 * yet, and the researchers named on them.
 */
interface ResearchRegisterImportServiceInterface
{
    /** Without $write nothing is stored; $write needs the approval token. */
    public function import(string $path, bool $write, ?string $approval): ResearchRegisterImportResultDTO;
}
