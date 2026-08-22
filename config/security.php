<?php

declare(strict_types=1);

return [
    'trusted_portal_hosts' => array_values(array_filter(array_map(
        static fn (string $host): string => strtolower(trim($host)),
        explode(',', (string) env('TRUSTED_PORTAL_HOSTS', 'my.spu.edu.sy')),
    ))),
];
