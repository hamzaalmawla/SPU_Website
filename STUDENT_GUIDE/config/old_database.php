<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Old Database Connection Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for connecting to the old SPU website database
    | for data migration purposes.
    |
    */

    'connection' => [
        'driver' => 'mysql',
        'host' => env('OLD_DB_HOST', '127.0.0.1'),
        'port' => env('OLD_DB_PORT', '3306'),
        'database' => env('OLD_DB_DATABASE', 'spuedu_old'),
        'username' => env('OLD_DB_USERNAME', 'root'),
        'password' => env('OLD_DB_PASSWORD', ''),
        'charset' => 'utf8mb4',
        'collation' => 'utf8mb4_unicode_ci',
        'prefix' => '',
        'strict' => false,
        'engine' => null,
    ],

    /*
    |--------------------------------------------------------------------------
    | Table Mappings
    |--------------------------------------------------------------------------
    |
    | Maps old database tables to new system entities
    |
    */

    'table_mappings' => [
        // Core System
        'jx_admins' => 'users',
        'jx_admin_category' => 'user_categories',
        'jx_admins_services' => 'user_service_assignments',
        
        // Content
        'jx_items' => 'content_items',
        'jx_categories' => 'categories',
        'jx_site_static_pages' => 'pages',
        'jx_archive' => 'archived_content',
        
        // Homepage
        'jx_home_photos' => 'homepage_media',
        
        // Media
        'jx_docs' => 'documents',
        'jx_logos' => 'logos',
        
        // University Specific
        'jx_members' => 'faculty_members',
        'jx_member_categories' => 'faculty_categories',
        'jx_member_items' => 'faculty_publications',
        'jx_councils' => 'councils',
        'jx_councils1' => 'council_members',
        'jx_good_students' => 'honor_students',
        'jx_graduated_students' => 'alumni',
        
        // Support
        'jx_faqs' => 'faqs',
        'jx_complaints' => 'complaints',
        'jx_complaint_cats' => 'complaint_categories',
        'jx_job_sites' => 'job_postings',
        
        // Configuration
        'jx_config' => 'settings',
        'jx_config1' => 'settings',
        'jx_languages' => 'languages',
        'jx_sites' => 'site_sections',
        
        // Reference Data
        'jx_countries' => 'countries',
        'jx_cities' => 'cities',
        
        // Comments
        'jx_items_comments' => 'comments',
    ],

    /*
    |--------------------------------------------------------------------------
    | Migration Batches
    |--------------------------------------------------------------------------
    |
    | Defines the order and grouping of migrations
    |
    */

    'migration_batches' => [
        'batch_1_foundation' => [
            'jx_languages',
            'jx_countries',
            'jx_cities',
            'jx_admins',
            'jx_admin_category',
        ],
        
        'batch_2_configuration' => [
            'jx_config',
            'jx_config1',
            'jx_sites',
        ],
        
        'batch_3_content_structure' => [
            'jx_categories',
            'jx_logos',
        ],
        
        'batch_4_content' => [
            'jx_items',
            'jx_site_static_pages',
            'jx_home_photos',
            'jx_docs',
            'jx_archive',
        ],
        
        'batch_5_university' => [
            'jx_member_categories',
            'jx_members',
            'jx_member_items',
            'jx_councils',
            'jx_councils1',
        ],
        
        'batch_6_students' => [
            'jx_good_students',
            'jx_graduated_students',
        ],
        
        'batch_7_support' => [
            'jx_faqs',
            'jx_complaint_cats',
            'jx_complaints',
            'jx_job_sites',
        ],
        
        'batch_8_engagement' => [
            'jx_items_comments',
            'jx_admins_services',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Field Transformations
    |--------------------------------------------------------------------------
    |
    | Common field transformation rules
    |
    */

    'transformations' => [
        'password' => 'force_reset', // MD5 → bcrypt with forced reset
        'lang' => ['0' => 'ar', '1' => 'en'], // Language code mapping
        'is_supervisor' => ['0' => 'editor', '1' => 'super_admin'], // Role mapping
        'date_format' => 'Y-m-d H:i:s', // Date format
    ],

    /*
    |--------------------------------------------------------------------------
    | Migration Options
    |--------------------------------------------------------------------------
    */

    'options' => [
        'batch_size' => 100, // Records per batch
        'preserve_ids' => true, // Try to preserve old IDs
        'create_audit_log' => true, // Log all migrations
        'send_notifications' => true, // Notify users of password resets
        'lock_migrated_accounts' => true, // Lock accounts until password reset
        'skip_empty_emails' => true, // Skip users without email
        'validate_before_insert' => true, // Validate data before inserting
    ],
];
