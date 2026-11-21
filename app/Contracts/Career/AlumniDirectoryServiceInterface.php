<?php

declare(strict_types=1);

namespace App\Contracts\Career;

use App\DTOs\Career\AlumniDirectoryPageDTO;

interface AlumniDirectoryServiceInterface
{
    /**
     * @param  array<string, mixed>  $filters
     */
    public function getDirectory(string $locale, array $filters = []): ?AlumniDirectoryPageDTO;

    public function isAvailable(): bool;
}
