<?php

declare(strict_types=1);

namespace App\Contracts\Page;

use App\DTOs\Content\ProfilePageDTO;

interface ProfilePageServiceInterface
{
    public function getProfile(string $locale, string $source, string $slug): ?ProfilePageDTO;
}
