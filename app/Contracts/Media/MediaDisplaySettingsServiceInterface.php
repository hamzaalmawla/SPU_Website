<?php

declare(strict_types=1);

namespace App\Contracts\Media;

interface MediaDisplaySettingsServiceInterface
{
    public function stylesheet(): string;
}
