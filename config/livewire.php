<?php

declare(strict_types=1);

// Only the keys this application changes. Livewire merges its own defaults
// for every other top-level key; `temporary_file_upload` is replaced as a
// whole, so it repeats the package defaults it does not change.
return [
    'temporary_file_upload' => [
        'disk' => null,
        'rules' => null,
        'directory' => null,

        // Livewire's default is throttle:60,1 for every upload request. The media
        // batch page accepts 50 images at once, so one batch plus a retry, or two
        // editors behind the same address, ran into it and uploads failed with 429.
        'middleware' => 'throttle:300,1',

        'preview_mimes' => [
            'png', 'gif', 'bmp', 'svg', 'wav', 'mp4',
            'mov', 'avi', 'wmv', 'mp3', 'm4a',
            'jpg', 'jpeg', 'mpga', 'webp', 'wma',
        ],

        // The signed upload URL expires after this many minutes. Five (the
        // default) is too short for a 10 MB image on a slow connection.
        'max_upload_time' => 30,
        'cleanup' => true,
    ],
];
