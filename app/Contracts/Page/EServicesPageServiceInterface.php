<?php

declare(strict_types=1);

namespace App\Contracts\Page;

use App\DTOs\EServices\EServicesDetailPageDTO;
use App\DTOs\EServices\EServicesPageContentDTO;
use App\DTOs\EServices\EServicesPageDTO;

interface EServicesPageServiceInterface
{
    public function getPage(string $locale): EServicesPageDTO;

    public function getSuggestionsComplaintsPage(string $locale): EServicesPageDTO;

    public function getDetailPage(string $locale, string $slug): EServicesDetailPageDTO;

    public function getContent(string $locale): EServicesPageContentDTO;

    /** @param array<string, mixed> $content */
    public function buildPreviewPage(string $locale, array $content): EServicesPageDTO;

    /** @param array<string, mixed> $content */
    public function buildDetailPreviewPage(string $locale, string $slug, array $content): EServicesDetailPageDTO;

    public function updatePage(string $locale, EServicesPageContentDTO $content, int $userId): bool;
}
