<?php

return [
    'enabled' => (bool) env('LEGACY_MEDIA_ENABLED', true),
    'base_url' => env('LEGACY_MEDIA_BASE_URL'),

    /*
    |--------------------------------------------------------------------------
    | Legacy photo directory
    |--------------------------------------------------------------------------
    |
    | The old CMS stored image columns (jx_categories.photo, jx_items.photo) as
    | a bare filename and prefixed this directory when rendering. The column on
    | the new side holds a path, so the import has to put the directory back —
    | otherwise a cover resolves to "/1494920895_5171123882.jpg" instead of
    | "/downloads/files/1494920895_5171123882.jpg" and every image 404s.
    |
    */
    'photo_directory' => trim((string) env('LEGACY_MEDIA_PHOTO_DIR', 'downloads/files'), '/'),
];
