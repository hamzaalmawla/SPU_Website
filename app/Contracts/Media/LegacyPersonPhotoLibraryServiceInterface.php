<?php

declare(strict_types=1);

namespace App\Contracts\Media;

use App\DTOs\Media\LegacyPersonPhotoLibraryReportDTO;

/**
 * Registers the photos imported with people (honor students, alumni, faculty
 * members, persons) as legacy media assets titled with the person's name, so an
 * editor can find a photo in the media picker by typing who is in it.
 */
interface LegacyPersonPhotoLibraryServiceInterface
{
    /**
     * Idempotent: a photo already in the legacy library only has its empty
     * title and alt text filled, and a photo already in the main library is left
     * alone. Nothing is written unless $write is set.
     *
     * @param  string  $legacyRoot  old document root the legacy trees are read from
     */
    public function register(string $legacyRoot, bool $write, ?int $userId = null): LegacyPersonPhotoLibraryReportDTO;
}
