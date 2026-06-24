<?php

declare(strict_types=1);

namespace App\Contracts\Page;

use App\DTOs\VirtualTour\VirtualTourPageDTO;

interface VirtualTourPageServiceInterface
{
    public function getPage(string $locale): VirtualTourPageDTO;
}
