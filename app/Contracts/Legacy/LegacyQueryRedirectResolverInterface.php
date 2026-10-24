<?php

declare(strict_types=1);

namespace App\Contracts\Legacy;

use App\DTOs\Legacy\RedirectResultDTO;

interface LegacyQueryRedirectResolverInterface
{
    public function resolve(string $path, ?string $queryString = null): ?RedirectResultDTO;
}
