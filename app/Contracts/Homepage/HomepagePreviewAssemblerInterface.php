<?php

declare(strict_types=1);

namespace App\Contracts\Homepage;

use App\DTOs\Homepage\HomepageDTO;

interface HomepagePreviewAssemblerInterface
{
    /**
     * @param  array<string, mixed>|null  $snapshot
     */
    public function build(string $locale, ?array $snapshot = null): HomepageDTO;
}
