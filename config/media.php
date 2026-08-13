<?php

return [
    'webp' => [
        'driver' => env('MEDIA_WEBP_DRIVER', 'auto'),
        'quality' => (int) env('MEDIA_WEBP_QUALITY', 82),
    ],
];
