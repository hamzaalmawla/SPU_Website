<?php

declare(strict_types=1);

namespace App\Contracts\Media;

interface MediaDisplaySettingsServiceInterface
{
    public function stylesheet(): string;

    /**
     * Only the rules for images this HTML mentions, small enough to inline in
     * the page head instead of linking every framed image in the library.
     */
    public function stylesheetFor(string $html): string;

    /** A short hash of the stylesheet, so its URL changes whenever a framing does. */
    public function version(): string;
}
