<?php

declare(strict_types=1);

namespace App\Contracts\Media;

interface MediaDisplaySettingsServiceInterface
{
    public function stylesheet(): string;

    /** A short hash of the stylesheet, so its URL changes whenever a framing does. */
    public function version(): string;
}
