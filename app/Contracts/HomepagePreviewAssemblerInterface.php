<?php

declare(strict_types=1);

namespace App\Contracts;

use App\DTOs\HomepageDTO;

interface HomepagePreviewAssemblerInterface
{
    /**
     * @param  array<string, mixed>|null  $snapshot
     */
    public function build(string $locale, ?array $snapshot = null): HomepageDTO;
}
