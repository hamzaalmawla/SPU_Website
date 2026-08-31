<?php

return [
    'webp' => [
        'driver' => env('MEDIA_WEBP_DRIVER', 'auto'),
        'quality' => (int) env('MEDIA_WEBP_QUALITY', 82),
    ],

    /*
    |--------------------------------------------------------------------------
    | Legacy image derivatives
    |--------------------------------------------------------------------------
    |
    | The legacy media tree holds original camera JPEGs that were never resized
    | for the web, and it is mounted read only. `media:generate-legacy-derivatives`
    | writes web-sized WebP copies onto the public disk instead, and
    | MediaUrlResolver prefers them when the manifest lists one.
    |
    | Disabling this makes every legacy image resolve back to its original, which
    | is also what happens whenever a derivative is missing.
    |
    */
    'derivatives' => [
        'enabled' => (bool) env('MEDIA_DERIVATIVES_ENABLED', true),

        // Rendered widths across the curated surfaces, smallest first. The
        // widest variant becomes the default `src`.
        'widths' => [480, 960, 1440],

        // Only paths under these legacy directories are considered.
        'source_directories' => [
            'downloads/files',
            'downloads/files2',
        ],
    ],
];
