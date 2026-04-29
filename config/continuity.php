<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Allowed Redirect Hosts
    |--------------------------------------------------------------------------
    |
    | Legacy redirect destinations are validated against this allowlist.
    | Relative paths are always allowed. Absolute URLs must target one of
    | these hosts or a subdomain of spu.edu.sy.
    |
    */

    'allowed_redirect_hosts' => array_filter(
        explode(',', env('CONTINUITY_ALLOWED_HOSTS', 'spu.edu.sy')),
    ),

];
