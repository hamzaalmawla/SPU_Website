<?php

declare(strict_types=1);

namespace App\Http\Controllers\Public;

use App\Contracts\Media\MediaDisplaySettingsServiceInterface;
use App\Http\Controllers\Controller;
use Illuminate\Http\Response;

final class MediaDisplayStylesheetController extends Controller
{
    public function __invoke(MediaDisplaySettingsServiceInterface $displaySettings): Response
    {
        return response($displaySettings->stylesheet(), 200, [
            'Content-Type' => 'text/css; charset=UTF-8',
            'Cache-Control' => 'no-cache, private',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
