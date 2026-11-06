<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Audit Log Retention
    |--------------------------------------------------------------------------
    |
    | Number of days to retain audit log records. Records older than this
    | threshold are eligible for pruning by the audit:prune command.
    | Set to 0 to retain all records indefinitely.
    |
    */

    'retention_days' => (int) env('AUDIT_RETENTION_DAYS', 90),

];
