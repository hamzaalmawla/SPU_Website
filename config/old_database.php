<?php

declare(strict_types=1);

return [
    'connection_name' => env('OLD_DB_CONNECTION', 'legacy_mysql'),

    'connection' => [
        'driver' => 'mysql',
        'host' => env('OLD_DB_HOST', '127.0.0.1'),
        'port' => env('OLD_DB_PORT', '3306'),
        'database' => env('OLD_DB_DATABASE', 'spu_legacy'),
        'username' => env('OLD_DB_USERNAME', 'root'),
        'password' => env('OLD_DB_PASSWORD', ''),
        'charset' => env('OLD_DB_CHARSET', 'utf8mb4'),
        'collation' => env('OLD_DB_COLLATION', 'utf8mb4_unicode_ci'),
        'prefix' => '',
        'prefix_indexes' => true,
        'strict' => true,
        'engine' => env('OLD_DB_ENGINE', 'InnoDB'),
    ],

    'allowed_locales' => ['ar', 'en'],

    'fake_dates' => [
        '0000-00-00',
        '0000-00-00 00:00:00',
        '1900-01-01',
        '1900-01-01 00:00:00',
        '1970-01-01',
        '1970-01-01 00:00:00',
    ],

    'unsafe_url_patterns' => [
        '/^javascript:/i',
        '/^vbscript:/i',
        '/^data:text\/html/i',
    ],

    'rejection_codes' => [
        'invalid_email',
        'unsupported_locale',
        'unsafe_html',
        'conflicting_setting',
        'unknown_mapping',
        'missing_parent',
        'duplicate_conflict',
    ],

    'modules' => [
        'admins' => [
            'enabled' => false,
            'source_tables' => ['jx_admins', 'jx_admin_category', 'jx_admins_services'],
            'target_tables' => ['users', 'roles'],
        ],
        'settings' => [
            'enabled' => false,
            'source_tables' => ['jx_config', 'jx_config1'],
            'target_tables' => ['settings'],
        ],
        'homepage' => [
            'enabled' => false,
            'source_tables' => ['jx_home_photos', 'jx_logos'],
            'target_tables' => ['media_assets', 'homepage_sections', 'homepage_section_translations'],
        ],
        'static_pages' => [
            'enabled' => false,
            'source_tables' => ['jx_site_static_pages'],
            'target_tables' => ['pages', 'page_translations', 'page_seo_meta'],
        ],
        'links' => [
            'enabled' => false,
            'source_tables' => ['jx_docs', 'jx_sites'],
            'target_tables' => ['media_assets', 'menu_items'],
        ],
        'news' => [
            'enabled' => false,
            'source_tables' => ['jx_categories', 'jx_items'],
            'target_tables' => ['news_categories', 'news_category_translations', 'news_articles', 'news_article_translations', 'news_article_seo_meta', 'news_article_attachments', 'media_assets'],
        ],
    ],

    'future_module_map' => [
        'jx_members' => 'faculty_members',
        'jx_councils' => 'councils',
        'jx_member_items' => 'research_publications',
        'jx_categories' => 'news_articles',
        'jx_items' => 'news_article_attachments',
        'jx_faqs' => 'faqs',
        'jx_complaint_cats' => 'complaint_categories',
        'jx_complaints' => 'complaints',
        'jx_job_sites' => 'career_links',
        'jx_graduated_students' => 'alumni',
        'jx_good_students' => 'honor_students',
        'jx_countries' => 'countries',
        'jx_cities' => 'cities',
    ],

    'never_import_as_product_tables' => [
        'dent_conf_temp',
        'jx_activation_codes',
    ],
];
