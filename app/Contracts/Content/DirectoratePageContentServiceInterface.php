<?php

declare(strict_types=1);

namespace App\Contracts\Content;

use App\DTOs\Content\DirectorateBlockDTO;
use App\DTOs\Content\DirectorateManagerDTO;

/**
 * What every directorate page shows from its saved content, the same for the
 * public page and the preview: the manager (typed in, or a linked staff
 * profile) and the blocks the editor built the page from.
 */
interface DirectoratePageContentServiceInterface
{
    /**
     * The manager in this language. A linked staff profile gives the name,
     * photo, rank and profile link while it is published; typed fields fill
     * what it leaves empty. A typed manager shows once a name is given in
     * either language (an empty language shows the other).
     *
     * @param  array<string, mixed>  $names  Typed name by locale.
     * @param  array<string, mixed>  $education  Typed qualifications by locale.
     */
    public function manager(?int $memberId, array $names, array $education, ?string $email, ?string $phone, ?string $photo, string $locale): ?DirectorateManagerDTO;

    /**
     * The page's blocks in this language. A button to a form that no longer
     * exists or is switched off is left out.
     *
     * @return list<DirectorateBlockDTO>
     */
    public function blocks(mixed $blocks, string $locale): array;
}
