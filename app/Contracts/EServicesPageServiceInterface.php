<?php

declare(strict_types=1);

namespace App\Contracts;

use App\DTOs\EServicesPageContentDTO;
use App\DTOs\EServicesPageDTO;

interface EServicesPageServiceInterface
{
    public function getPage(string $locale): EServicesPageDTO;

    public function getContent(string $locale): EServicesPageContentDTO;

    public function updatePage(string $locale, EServicesPageContentDTO $content, int $userId): bool;
}
