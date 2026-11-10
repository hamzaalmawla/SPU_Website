<?php

/**
 * Database Configuration Example for Students
 *
 * This shows how to add the old database connection to config/database.php
 */

return [
    /*
    |--------------------------------------------------------------------------
    | Add this to your config/database.php file
    |--------------------------------------------------------------------------
    |
    | Add the 'old_spu' connection to the 'connections' array
    |
    */

    'connections' => [

        // ... existing connections (sqlite, mysql, pgsql, etc.)

        /**
         * Old SPU Database Connection
         *
         * This connection is used to read data from the old database
         * during the migration process.
         */
        'old_spu' => [
            'driver' => 'mysql',
            'host' => env('OLD_DB_HOST', '127.0.0.1'),
            'port' => env('OLD_DB_PORT', '3306'),
            'database' => env('OLD_DB_DATABASE', 'spuedu_old'),
            'username' => env('OLD_DB_USERNAME', 'root'),
            'password' => env('OLD_DB_PASSWORD', ''),
            'charset' => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
            'prefix' => '',
            'strict' => false, // Important: old database may have loose constraints
            'engine' => null,
        ],

    ],
];
