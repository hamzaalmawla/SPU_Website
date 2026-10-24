<?php

declare(strict_types=1);

namespace App\Http\Controllers\Public;

use App\Contracts\Media\MediaDisplaySettingsServiceInterface;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

final class MediaDisplayStylesheetController extends Controller
{
    public function __invoke(Request $request, MediaDisplaySettingsServiceInterface $displaySettings): Response
    {
        // Pages link the stylesheet with its version, which changes whenever a
        // framing does, so that URL can be cached for good. Any other URL is
        // revalidated.
        $isCurrent = $request->query('v') === $displaySettings->version();

        return response($displaySettings->stylesheet(), 200, [
            'Content-Type' => 'text/css; charset=UTF-8',
            'Cache-Control' => $isCurrent ? 'public, max-age=31536000, immutable' : 'no-cache, private',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
