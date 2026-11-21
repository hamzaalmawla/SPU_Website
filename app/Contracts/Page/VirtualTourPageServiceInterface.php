<?php

declare(strict_types=1);

namespace App\Contracts\Page;

use App\DTOs\VirtualTour\VirtualTourPageDTO;

interface VirtualTourPageServiceInterface
{
    public function getPage(string $locale): VirtualTourPageDTO;

    /** @param array<string, mixed> $content */
    public function buildPreviewPage(string $locale, array $content): VirtualTourPageDTO;

    /** @return array{translations: array{ar: array<string, mixed>, en: array<string, mixed>}} */
    public function getEditablePayload(): array;
}
