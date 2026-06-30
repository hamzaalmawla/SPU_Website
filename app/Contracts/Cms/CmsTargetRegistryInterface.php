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
}
