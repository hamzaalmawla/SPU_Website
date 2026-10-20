<?php

declare(strict_types=1);

namespace App\Contracts\Cms;

use App\DTOs\Cms\CmsTargetDTO;
use Illuminate\Support\Collection;

interface CmsTargetRegistryInterface
{
    /** @return Collection<int, CmsTargetDTO> */
    public function all(): Collection;

    /** @return Collection<int, CmsTargetDTO> */
    public function forArea(string $area): Collection;

    public function find(string $key): ?CmsTargetDTO;

    /**
     * Pages a super administrator can grant one by one to a limited editor:
     * every fixed public page except faculty pages (granted with their
     * faculty) and the job board (HR's own screen).
     *
     * @return Collection<int, CmsTargetDTO>
     */
    public function grantablePages(): Collection;
}
