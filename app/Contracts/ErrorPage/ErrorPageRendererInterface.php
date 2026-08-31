<?php

declare(strict_types=1);

namespace App\Contracts\ErrorPage;

use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * Renders branded error responses, or defers to Laravel's default handling.
 */
interface ErrorPageRendererInterface
{
    /**
     * Build a branded error response for the given exception.
     *
     * Returns null when Laravel's own handling must win: JSON/API requests,
     * the Filament admin panel, and non-HTTP exceptions while APP_DEBUG is on
     * (so developers keep the debug page).
     */
    public function render(Throwable $exception, Request $request): ?Response;
}
