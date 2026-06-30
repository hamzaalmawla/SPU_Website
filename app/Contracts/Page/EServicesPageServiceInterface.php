<?php

declare(strict_types=1);

namespace App\Contracts\Page;

use App\DTOs\EServices\EServicesPageContentDTO;
use App\DTOs\EServices\EServicesPageDTO;

interface EServicesPageServiceInterface
{
    public function getPage(string $locale): EServicesPageDTO;

    public function getContent(string $locale): EServicesPageContentDTO;

    /** @param array<string, mixed> $content */
    public function buildPreviewPage(string $locale, array $content): EServicesPageDTO;

    public function updatePage(string $locale, EServicesPageContentDTO $content, int $userId): bool;
}
