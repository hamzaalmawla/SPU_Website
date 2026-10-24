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
        // widest variant becomes the default `src`. 240 covers the 110px
        // faculty icons at 2x, which otherwise download a 400px original.
        'widths' => [240, 480, 960, 1440],

        // Only paths under these directories are considered. `storage/media`
        // is the CMS upload library on the public disk: uploads are stored at
        // up to 1280px whatever slot they fill, so the homepage's small slots
        // get a srcset from the same manifest.
        'source_directories' => [
            'downloads/files',
            'downloads/files2',
            'storage/media',
        ],
    ],
];
