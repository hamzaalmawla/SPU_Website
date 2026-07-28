<?php

declare(strict_types=1);

return [
    'connection_name' => env('OLD_DB_CONNECTION', 'legacy_mysql'),

    'allow_broad_import' => env('OLD_DB_ALLOW_BROAD_IMPORT', false),

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

    'spam_url_patterns' => [
        '/casino/i',
        '/\bviagra\b/i',
        '/\bpoker\b/i',
    ],

    'rejection_codes' => [
        'invalid_email',
        'unsupported_locale',
        'unsafe_html',
        'conflicting_setting',
        'unknown_mapping',
        'missing_parent',
        'duplicate_conflict',
        'base64_inline_image',
        'spam_link',
        'suspicious_external_url',
        'missing_required_value',
        'orphaned_child',
        'duplicate_legacy_content',
        'legacy_internal_link',
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

    'file_inventory_fields' => [
        ['table' => 'jx_items', 'id_column' => 'id', 'columns' => ['photo', 'en_file', 'ar_file']],
        ['table' => 'jx_categories', 'id_column' => 'id', 'columns' => ['photo']],
        ['table' => 'jx_home_photos', 'id_column' => 'id', 'columns' => ['photo']],
        ['table' => 'jx_member_items', 'id_column' => 'id', 'columns' => ['en_file']],
        ['table' => 'jx_councils', 'id_column' => 'id', 'columns' => ['cv', 'ar_cv']],
        ['table' => 'jx_councils1', 'id_column' => 'id', 'columns' => ['cv']],
        ['table' => 'jx_docs', 'id_column' => 'id', 'columns' => ['file']],
    ],

    // Never treat Laravel's current public directory as proof of the legacy source tree.
    'file_inventory_roots' => array_values(array_filter([
        env('OLD_PUBLIC_ROOT'),
    ])),

    // Only these legacy static trees may be considered for direct public preservation.
    'file_continuity_static_directories' => [
        'downloads/files',
        'downloads/files/thumb',
        'downloads/files2',
        'images',
        'pdf',
        'cv_bank',
        'med/images',
        'dent/images',
        'pharm/images',
        'info/images',
        'petrol/images',
        'admin/images',
        'research/images',
        'hospital/images',
        'dent_clinic/images',
        'alumni/images',
        'clubs/images',
    ],

    'cleaning_inspection_fields' => [
        'admins' => [
            ['table' => 'jx_admins', 'id_column' => 'id', 'fields' => [
                ['column' => 'email', 'type' => 'email', 'required' => true],
                ['column' => 'user_name', 'type' => 'text', 'required' => false],
                ['column' => 'full_name', 'type' => 'text', 'required' => false],
                ['column' => 'reg_date', 'type' => 'date', 'required' => false],
            ]],
        ],
        'settings' => [
            ['table' => 'jx_config', 'id_column' => 'id', 'fields' => [
                ['column' => 'name', 'type' => 'text', 'required' => false],
                ['column' => 'label', 'type' => 'text', 'required' => false],
                ['column' => 'value', 'type' => 'text', 'required' => false],
            ]],
            ['table' => 'jx_config1', 'id_column' => 'id', 'fields' => [
                ['column' => 'name', 'type' => 'text', 'required' => false],
                ['column' => 'label', 'type' => 'text', 'required' => false],
                ['column' => 'value', 'type' => 'text', 'required' => false],
            ]],
        ],
        'homepage' => [
            ['table' => 'jx_home_photos', 'id_column' => 'id', 'fields' => [
                ['column' => 'photo', 'type' => 'url', 'required' => false],
                ['column' => 'url', 'type' => 'url', 'required' => false],
                ['column' => 'ar_name', 'type' => 'text', 'required' => false],
                ['column' => 'en_name', 'type' => 'text', 'required' => false],
                ['column' => 'ar_description', 'type' => 'html', 'required' => false],
                ['column' => 'en_description', 'type' => 'html', 'required' => false],
            ]],
            ['table' => 'jx_logos', 'id_column' => 'id', 'fields' => [
                ['column' => 'photo', 'type' => 'url', 'required' => false],
                ['column' => 'url', 'type' => 'url', 'required' => false],
                ['column' => 'ar_name', 'type' => 'text', 'required' => false],
                ['column' => 'en_name', 'type' => 'text', 'required' => false],
                ['column' => 'ar_data', 'type' => 'html', 'required' => false],
                ['column' => 'en_data', 'type' => 'html', 'required' => false],
            ]],
        ],
        'static_pages' => [
            ['table' => 'jx_site_static_pages', 'id_column' => 'id', 'fields' => [
                ['column' => 'ar_page_data', 'type' => 'html', 'required' => false],
                ['column' => 'en_page_data', 'type' => 'html', 'required' => false],
                ['column' => 'ar_comment', 'type' => 'text', 'required' => false],
                ['column' => 'en_comment', 'type' => 'text', 'required' => false],
                ['column' => 'ar_brief', 'type' => 'text', 'required' => false],
                ['column' => 'en_brief', 'type' => 'text', 'required' => false],
            ]],
        ],
        'links' => [
            ['table' => 'jx_docs', 'id_column' => 'id', 'fields' => [
                ['column' => 'file', 'type' => 'url', 'required' => false],
                ['column' => 'url', 'type' => 'url', 'required' => false],
                ['column' => 'download_file', 'type' => 'url', 'required' => false],
                ['column' => 'name', 'type' => 'text', 'required' => false],
                ['column' => 'brief', 'type' => 'text', 'required' => false],
                ['column' => 'description', 'type' => 'html', 'required' => false],
            ]],
            ['table' => 'jx_sites', 'id_column' => 'id', 'fields' => [
                ['column' => 'url', 'type' => 'url', 'required' => true],
                ['column' => 'ar_name', 'type' => 'text', 'required' => false],
                ['column' => 'en_name', 'type' => 'text', 'required' => false],
            ]],
        ],
        'news' => [
            ['table' => 'jx_categories', 'id_column' => 'id', 'fields' => [
                ['column' => 'ar_name', 'type' => 'text', 'required' => false],
                ['column' => 'en_name', 'type' => 'text', 'required' => false],
                ['column' => 'ar_data', 'type' => 'html', 'required' => false],
                ['column' => 'en_data', 'type' => 'html', 'required' => false],
                ['column' => 'start_date', 'type' => 'date', 'required' => false],
            ]],
            ['table' => 'jx_items', 'id_column' => 'id', 'fields' => [
                ['column' => 'ar_name', 'type' => 'text', 'required' => false],
                ['column' => 'en_name', 'type' => 'text', 'required' => false],
                ['column' => 'ar_description', 'type' => 'html', 'required' => false],
                ['column' => 'en_description', 'type' => 'html', 'required' => false],
                ['column' => 'post_date', 'type' => 'date', 'required' => false],
            ]],
        ],
        'faculties' => [
            ['table' => 'jx_member_categories', 'id_column' => 'id', 'fields' => [
                ['column' => 'ar_name', 'type' => 'text', 'required' => false],
                ['column' => 'en_name', 'type' => 'text', 'required' => false],
                ['column' => 'ar_data', 'type' => 'html', 'required' => false],
                ['column' => 'en_data', 'type' => 'html', 'required' => false],
            ]],
        ],
        'faculty_members' => [
            ['table' => 'jx_members', 'id_column' => 'id', 'fields' => [
                ['column' => 'ar_name', 'type' => 'text', 'required' => false],
                ['column' => 'en_name', 'type' => 'text', 'required' => false],
                ['column' => 'email', 'type' => 'email', 'required' => false],
                ['column' => 'ar_data', 'type' => 'html', 'required' => false],
                ['column' => 'en_data', 'type' => 'html', 'required' => false],
            ]],
            ['table' => 'jx_councils1', 'id_column' => 'id', 'fields' => [
                ['column' => 'ar_name', 'type' => 'text', 'required' => false],
                ['column' => 'en_name', 'type' => 'text', 'required' => false],
                ['column' => 'email', 'type' => 'email', 'required' => false],
                ['column' => 'ar_data', 'type' => 'html', 'required' => false],
                ['column' => 'en_data', 'type' => 'html', 'required' => false],
                ['column' => 'cv', 'type' => 'url', 'required' => false],
            ]],
            ['table' => 'jx_councils', 'id_column' => 'id', 'fields' => [
                ['column' => 'ar_name', 'type' => 'text', 'required' => false],
                ['column' => 'en_name', 'type' => 'text', 'required' => false],
                ['column' => 'email', 'type' => 'email', 'required' => false],
                ['column' => 'ar_data', 'type' => 'html', 'required' => false],
                ['column' => 'en_data', 'type' => 'html', 'required' => false],
                ['column' => 'ar_position', 'type' => 'text', 'required' => false],
                ['column' => 'en_position', 'type' => 'text', 'required' => false],
                ['column' => 'ar_specialization', 'type' => 'text', 'required' => false],
                ['column' => 'en_specialization', 'type' => 'text', 'required' => false],
            ]],
        ],
        'research' => [
            ['table' => 'jx_member_categories', 'id_column' => 'id', 'fields' => [
                ['column' => 'ar_name', 'type' => 'text', 'required' => false],
                ['column' => 'en_name', 'type' => 'text', 'required' => false],
                ['column' => 'ar_data', 'type' => 'html', 'required' => false],
                ['column' => 'en_data', 'type' => 'html', 'required' => false],
                ['column' => 'url', 'type' => 'url', 'required' => false],
                ['column' => 'start_date', 'type' => 'date', 'required' => false],
                ['column' => 'end_date', 'type' => 'date', 'required' => false],
            ]],
            ['table' => 'jx_member_items', 'id_column' => 'id', 'fields' => [
                ['column' => 'ar_name', 'type' => 'text', 'required' => false],
                ['column' => 'en_name', 'type' => 'text', 'required' => false],
                ['column' => 'ar_file', 'type' => 'url', 'required' => false],
                ['column' => 'en_file', 'type' => 'url', 'required' => false],
                ['column' => 'video_link', 'type' => 'url', 'required' => false],
            ]],
        ],
        'councils' => [
            ['table' => 'jx_councils1', 'id_column' => 'id', 'fields' => [
                ['column' => 'ar_name', 'type' => 'text', 'required' => false],
                ['column' => 'en_name', 'type' => 'text', 'required' => false],
                ['column' => 'ar_position', 'type' => 'text', 'required' => false],
                ['column' => 'en_position', 'type' => 'text', 'required' => false],
                ['column' => 'ar_data', 'type' => 'html', 'required' => false],
                ['column' => 'en_data', 'type' => 'html', 'required' => false],
                ['column' => 'cv', 'type' => 'url', 'required' => false],
            ]],
        ],
        'faqs' => [
            ['table' => 'jx_faqs', 'id_column' => 'id', 'fields' => [
                ['column' => 'subject', 'type' => 'text', 'required' => false],
                ['column' => 'question', 'type' => 'html', 'required' => false],
                ['column' => 'answer', 'type' => 'html', 'required' => false],
                ['column' => 'post_date', 'type' => 'date', 'required' => false],
            ]],
        ],
        'complaints' => [
            ['table' => 'jx_complaint_cats', 'id_column' => 'id', 'fields' => [
                ['column' => 'ar_name', 'type' => 'text', 'required' => false],
                ['column' => 'en_name', 'type' => 'text', 'required' => false],
                ['column' => 'email', 'type' => 'email', 'required' => false],
            ]],
            ['table' => 'jx_complaints', 'id_column' => 'id', 'fields' => [
                ['column' => 'question', 'type' => 'html', 'required' => false],
                ['column' => 'answer', 'type' => 'html', 'required' => false],
                ['column' => 'post_date', 'type' => 'date', 'required' => false],
            ]],
        ],
        'career_links' => [
            ['table' => 'jx_job_sites', 'id_column' => 'id', 'fields' => [
                ['column' => 'ar_name', 'type' => 'text', 'required' => false],
                ['column' => 'en_name', 'type' => 'text', 'required' => false],
                ['column' => 'url', 'type' => 'url', 'required' => true],
                ['column' => 'ar_data', 'type' => 'html', 'required' => false],
                ['column' => 'en_data', 'type' => 'html', 'required' => false],
                ['column' => 'added_date', 'type' => 'date', 'required' => false],
            ]],
        ],
        'alumni' => [
            ['table' => 'jx_graduated_students', 'id_column' => 'id', 'fields' => [
                ['column' => 'ar_name', 'type' => 'text', 'required' => false],
                ['column' => 'en_name', 'type' => 'text', 'required' => false],
                ['column' => 'post_date', 'type' => 'date', 'required' => false],
            ]],
        ],
        'honor_students' => [
            ['table' => 'jx_good_students', 'id_column' => 'id', 'fields' => [
                ['column' => 'ar_name', 'type' => 'text', 'required' => false],
                ['column' => 'en_name', 'type' => 'text', 'required' => false],
                ['column' => 'post_date', 'type' => 'date', 'required' => false],
            ]],
        ],
        'countries' => [
            ['table' => 'jx_countries', 'id_column' => 'id', 'fields' => [
                ['column' => 'ar_name', 'type' => 'text', 'required' => false],
                ['column' => 'en_name', 'type' => 'text', 'required' => false],
            ]],
        ],
        'cities' => [
            ['table' => 'jx_cities', 'id_column' => 'id', 'fields' => [
                ['column' => 'ar_name', 'type' => 'text', 'required' => false],
                ['column' => 'en_name', 'type' => 'text', 'required' => false],
            ]],
        ],
    ],

    'integrity_inspection_rules' => [
        'admins' => ['duplicates' => [['table' => 'jx_admins', 'id_column' => 'id', 'columns' => ['email']]]],
        'settings' => ['duplicates' => [
            ['table' => 'jx_config', 'id_column' => 'id', 'columns' => ['name']],
            ['table' => 'jx_config1', 'id_column' => 'id', 'columns' => ['name']],
        ]],
        'homepage' => ['duplicates' => [
            ['table' => 'jx_home_photos', 'id_column' => 'id', 'columns' => ['photo']],
            ['table' => 'jx_logos', 'id_column' => 'id', 'columns' => ['photo']],
        ]],
        'static_pages' => ['duplicates' => [
            ['table' => 'jx_site_static_pages', 'id_column' => 'id', 'columns' => ['ar_brief']],
            ['table' => 'jx_site_static_pages', 'id_column' => 'id', 'columns' => ['en_brief']],
        ]],
        'links' => ['duplicates' => [
            ['table' => 'jx_sites', 'id_column' => 'id', 'columns' => ['url']],
            ['table' => 'jx_docs', 'id_column' => 'id', 'columns' => ['file']],
        ]],
        'news' => [
            'orphans' => [[
                'child_table' => 'jx_items',
                'child_id_column' => 'id',
                'child_parent_column' => 'category_id',
                'parent_table' => 'jx_categories',
                'parent_id_column' => 'id',
            ]],
            'duplicates' => [
                ['table' => 'jx_categories', 'id_column' => 'id', 'columns' => ['service_type', 'ar_name']],
                ['table' => 'jx_categories', 'id_column' => 'id', 'columns' => ['service_type', 'en_name'], 'ignored_values' => ['Under Construction']],
            ],
        ],
        'faculties' => ['duplicates' => [
            ['table' => 'jx_member_categories', 'id_column' => 'id', 'columns' => ['service_type', 'ar_name']],
            ['table' => 'jx_member_categories', 'id_column' => 'id', 'columns' => ['service_type', 'en_name']],
        ]],
        'faculty_members' => [
            'orphans' => [[
                'child_table' => 'jx_members',
                'child_id_column' => 'id',
                'child_parent_column' => 'country_id',
                'parent_table' => 'jx_countries',
                'parent_id_column' => 'id',
            ]],
            'duplicates' => [
                ['table' => 'jx_members', 'id_column' => 'id', 'columns' => ['email']],
                ['table' => 'jx_councils1', 'id_column' => 'id', 'columns' => ['email']],
            ],
        ],
        'research' => [
            'orphans' => [[
                'child_table' => 'jx_member_items',
                'child_id_column' => 'id',
                'child_parent_column' => 'member_category_id',
                'parent_table' => 'jx_member_categories',
                'parent_id_column' => 'id',
            ]],
            'duplicates' => [
                ['table' => 'jx_member_categories', 'id_column' => 'id', 'columns' => ['service_type', 'ar_name']],
                ['table' => 'jx_member_categories', 'id_column' => 'id', 'columns' => ['service_type', 'en_name']],
                ['table' => 'jx_member_items', 'id_column' => 'id', 'columns' => ['member_category_id', 'en_file']],
            ],
        ],
        'councils' => ['duplicates' => [
            ['table' => 'jx_councils1', 'id_column' => 'id', 'columns' => ['service_type', 'ar_name']],
            ['table' => 'jx_councils1', 'id_column' => 'id', 'columns' => ['service_type', 'en_name']],
        ]],
        'faqs' => ['duplicates' => [['table' => 'jx_faqs', 'id_column' => 'id', 'columns' => ['lang', 'subject']]]],
        'complaints' => [
            'orphans' => [[
                'child_table' => 'jx_complaints',
                'child_id_column' => 'id',
                'child_parent_column' => 'complaint_cat_id',
                'parent_table' => 'jx_complaint_cats',
                'parent_id_column' => 'id',
            ]],
            'duplicates' => [
                ['table' => 'jx_complaint_cats', 'id_column' => 'id', 'columns' => ['ar_name']],
                ['table' => 'jx_complaint_cats', 'id_column' => 'id', 'columns' => ['en_name']],
                ['table' => 'jx_complaints', 'id_column' => 'id', 'columns' => ['email', 'question']],
            ],
        ],
        'career_links' => ['duplicates' => [['table' => 'jx_job_sites', 'id_column' => 'id', 'columns' => ['url']]]],
        'alumni' => ['duplicates' => [
            ['table' => 'jx_graduated_students', 'id_column' => 'id', 'columns' => ['department_id', 'section_id', 'ar_name']],
            ['table' => 'jx_graduated_students', 'id_column' => 'id', 'columns' => ['department_id', 'section_id', 'en_name']],
        ]],
        'honor_students' => ['duplicates' => [['table' => 'jx_good_students', 'id_column' => 'id', 'columns' => ['department_id', 'section_id', 'ar_name', 'date_year']]]],
        'countries' => ['duplicates' => [
            ['table' => 'jx_countries', 'id_column' => 'id', 'columns' => ['ar_name']],
            ['table' => 'jx_countries', 'id_column' => 'id', 'columns' => ['en_name']],
        ]],
        'cities' => [
            'orphans' => [[
                'child_table' => 'jx_cities',
                'child_id_column' => 'id',
                'child_parent_column' => 'country_id',
                'parent_table' => 'jx_countries',
                'parent_id_column' => 'id',
            ]],
            'duplicates' => [
                ['table' => 'jx_cities', 'id_column' => 'id', 'columns' => ['country_id', 'ar_name']],
                ['table' => 'jx_cities', 'id_column' => 'id', 'columns' => ['country_id', 'en_name']],
            ],
        ],
    ],

    'internal_link_extraction_fields' => [
        'settings' => [
            ['table' => 'jx_config', 'id_column' => 'id', 'columns' => ['value']],
            ['table' => 'jx_config1', 'id_column' => 'id', 'columns' => ['value']],
        ],
        'homepage' => [
            ['table' => 'jx_home_photos', 'id_column' => 'id', 'columns' => ['photo', 'url', 'ar_name', 'en_name', 'ar_description', 'en_description']],
            ['table' => 'jx_logos', 'id_column' => 'id', 'columns' => ['photo', 'url', 'ar_name', 'en_name', 'ar_data', 'en_data']],
        ],
        'static_pages' => [
            ['table' => 'jx_site_static_pages', 'id_column' => 'id', 'columns' => ['ar_page_data', 'en_page_data']],
        ],
        'links' => [
            ['table' => 'jx_sites', 'id_column' => 'id', 'columns' => ['url', 'ar_data', 'en_data']],
            ['table' => 'jx_docs', 'id_column' => 'id', 'columns' => ['file', 'url', 'download_file', 'description']],
        ],
        'news' => [
            ['table' => 'jx_categories', 'id_column' => 'id', 'columns' => ['ar_data', 'en_data']],
            ['table' => 'jx_items', 'id_column' => 'id', 'columns' => ['ar_description', 'en_description', 'ar_file', 'en_file']],
        ],
        'faculties' => [
            ['table' => 'jx_member_categories', 'id_column' => 'id', 'columns' => ['ar_data', 'en_data', 'url']],
        ],
        'faculty_members' => [
            ['table' => 'jx_members', 'id_column' => 'id', 'columns' => ['ar_data', 'en_data', 'photo']],
            ['table' => 'jx_councils1', 'id_column' => 'id', 'columns' => ['ar_data', 'en_data', 'cv', 'photo']],
        ],
        'research' => [
            ['table' => 'jx_member_categories', 'id_column' => 'id', 'columns' => ['ar_data', 'en_data', 'url']],
            ['table' => 'jx_member_items', 'id_column' => 'id', 'columns' => ['ar_file', 'en_file', 'photo', 'video_link']],
        ],
        'councils' => [
            ['table' => 'jx_councils1', 'id_column' => 'id', 'columns' => ['ar_data', 'en_data', 'cv', 'photo']],
        ],
        'faqs' => [
            ['table' => 'jx_faqs', 'id_column' => 'id', 'columns' => ['question', 'answer']],
        ],
        'complaints' => [
            ['table' => 'jx_complaint_cats', 'id_column' => 'id', 'columns' => ['ar_name', 'en_name']],
            ['table' => 'jx_complaints', 'id_column' => 'id', 'columns' => ['question', 'answer']],
        ],
        'career_links' => [
            ['table' => 'jx_job_sites', 'id_column' => 'id', 'columns' => ['url', 'ar_data', 'en_data']],
        ],
        'alumni' => [
            ['table' => 'jx_graduated_students', 'id_column' => 'id', 'columns' => ['photo']],
        ],
        'honor_students' => [
            ['table' => 'jx_good_students', 'id_column' => 'id', 'columns' => ['photo']],
        ],
    ],

    'classification_rules' => [
        'admins' => [
            'jx_admins' => [
                'bucket' => 'retire_after_approval',
                'rule_key' => 'legacy_admin_identity_review',
                'high_risk' => false,
                'identity_columns' => ['email', 'full_name', 'user_name'],
                'date_columns' => ['reg_date'],
                'notes' => 'Old admin identities are not imported automatically; recreate only verified active accounts.',
            ],
            'jx_admins_services' => [
                'bucket' => 'archive_now_remodel_later',
                'rule_key' => 'legacy_admin_permission_archive',
                'high_risk' => false,
                'identity_columns' => ['admin_id', 'service_id'],
                'notes' => 'Old permission matrix is evidence only; modern roles/policies are rebuilt explicitly.',
            ],
            'jx_admin_category' => [
                'bucket' => 'archive_now_remodel_later',
                'rule_key' => 'legacy_admin_category_archive',
                'id_column' => 'admin_id',
                'high_risk' => false,
                'identity_columns' => ['admin_id', 'category_id'],
                'notes' => 'Old admin/category links are support evidence only.',
            ],
        ],
        'settings' => [
            'jx_config' => [
                'bucket' => 'archive_now_remodel_later',
                'rule_key' => 'legacy_setting_remodel',
                'high_risk' => true,
                'identity_columns' => ['name', 'label'],
                'notes' => 'Legacy settings require explicit modern setting mapping; duplicate keys stay planned before import.',
            ],
            'jx_config1' => [
                'bucket' => 'archive_now_remodel_later',
                'rule_key' => 'legacy_setting_remodel',
                'high_risk' => true,
                'identity_columns' => ['name', 'label'],
                'notes' => 'Alternate legacy settings require explicit modern setting mapping; duplicate keys stay planned before import.',
            ],
        ],
        'homepage' => [
            'jx_home_photos' => [
                'bucket' => 'archive_now_remodel_later',
                'rule_key' => 'legacy_homepage_media_review',
                'high_risk' => false,
                'identity_columns' => ['ar_name', 'en_name'],
                'file_columns' => ['photo'],
                'url_columns' => ['url'],
                'notes' => 'Homepage media/text is evidence for CMS rebuilding; file bytes remain unavailable until OLD_PUBLIC_ROOT is mounted.',
            ],
            'jx_logos' => [
                'bucket' => 'archive_now_remodel_later',
                'rule_key' => 'legacy_logo_media_review',
                'high_risk' => false,
                'identity_columns' => ['ar_name', 'en_name'],
                'file_columns' => ['photo'],
                'url_columns' => ['url'],
                'notes' => 'Legacy logos are preserved as rebuild evidence, not imported blindly.',
            ],
        ],
        'static_pages' => [
            'jx_site_static_pages' => [
                'bucket' => 'archive_now_remodel_later',
                'rule_key' => 'legacy_static_page_remodel',
                'high_risk' => false,
                'identity_columns' => ['ar_brief', 'en_brief', 'ar_comment', 'en_comment'],
                'file_columns' => ['ar_photo', 'en_photo'],
                'notes' => 'Static pages require explicit modern page mapping before canonical import.',
            ],
        ],
        'links' => [
            'jx_docs' => [
                'bucket' => 'file_only_preserve',
                'rule_key' => 'legacy_document_or_link_preserve',
                'high_risk' => true,
                'identity_columns' => ['name', 'brief'],
                'file_columns' => ['file', 'download_file'],
                'url_columns' => ['url'],
                'notes' => 'Legacy document/link rows are preserved until files and menu targets are verified.',
            ],
            'jx_sites' => [
                'bucket' => 'redirect_to_equivalent',
                'rule_key' => 'legacy_external_link_candidate',
                'high_risk' => false,
                'identity_columns' => ['ar_name', 'en_name'],
                'url_columns' => ['url'],
                'notes' => 'External-link rows are redirect/content-link candidates, not final redirects.',
            ],
        ],
        'legacy_categories' => [
            'jx_categories' => [
                'bucket' => 'archive_now_remodel_later',
                'rule_key' => 'legacy_category_context_review',
                'high_risk' => true,
                'identity_columns' => ['ar_name', 'en_name'],
                'file_columns' => ['photo'],
                'url_columns' => ['url'],
                'date_columns' => ['start_date'],
                'notes' => 'Category rows require subsite, service suffix, hierarchy, visibility, and link/file semantics before any typed module import.',
            ],
        ],
        'legacy_items' => [
            'jx_items' => [
                'bucket' => 'file_only_preserve',
                'rule_key' => 'legacy_typed_child_content_preserve',
                'high_risk' => true,
                'identity_columns' => ['ar_name', 'en_name'],
                'file_columns' => ['photo', 'ar_file', 'en_file'],
                'url_columns' => ['video_link'],
                'date_columns' => ['post_date', 'updated_date', 'added_date'],
                'notes' => 'Item rows require parent category context before typed content or file preservation decisions.',
            ],
        ],
        'faculties' => [
            'jx_member_categories' => [
                'bucket' => 'archive_now_remodel_later',
                'rule_key' => 'legacy_faculty_tree_remodel',
                'high_risk' => true,
                'identity_columns' => ['ar_name', 'en_name'],
                'file_columns' => ['photo'],
                'url_columns' => ['url'],
                'date_columns' => ['start_date', 'end_date'],
                'notes' => 'Faculty/member category trees need subsite and ownership mapping before canonical import.',
            ],
        ],
        'faculty_members' => [
            'jx_members' => [
                'bucket' => 'archive_now_remodel_later',
                'rule_key' => 'legacy_member_profile_remodel',
                'high_risk' => false,
                'identity_columns' => ['ar_name', 'en_name', 'email'],
                'file_columns' => ['photo'],
                'notes' => 'Legacy member profiles require verified ownership/faculty mapping before use.',
            ],
            'jx_councils1' => [
                'bucket' => 'archive_now_remodel_later',
                'rule_key' => 'legacy_council_profile_remodel',
                'high_risk' => true,
                'identity_columns' => ['ar_name', 'en_name', 'email'],
                'file_columns' => ['photo', 'cv'],
                'notes' => 'Parallel profile structure is archived for later person/profile remodeling.',
            ],
        ],
        'research' => [
            'jx_member_categories' => [
                'bucket' => 'archive_now_remodel_later',
                'rule_key' => 'legacy_research_tree_remodel',
                'high_risk' => true,
                'identity_columns' => ['ar_name', 'en_name'],
                'file_columns' => ['photo'],
                'url_columns' => ['url'],
                'date_columns' => ['start_date', 'end_date'],
                'notes' => 'Research/category content requires explicit repository/module mapping before canonical import.',
            ],
            'jx_member_items' => [
                'bucket' => 'file_only_preserve',
                'rule_key' => 'legacy_research_attachment_preserve',
                'high_risk' => true,
                'identity_columns' => ['ar_name', 'en_name'],
                'file_columns' => ['ar_file', 'en_file', 'photo'],
                'url_columns' => ['video_link'],
                'notes' => 'Research item files are preserved until parent category and file bytes are reconciled.',
            ],
        ],
        'councils' => [
            'jx_councils' => [
                'bucket' => 'archive_now_remodel_later',
                'rule_key' => 'legacy_council_archive',
                'high_risk' => true,
                'identity_columns' => ['ar_name', 'en_name', 'email'],
                'file_columns' => ['photo', 'cv', 'ar_cv'],
                'notes' => 'Legacy council/person rows are archived until profile target mappings are approved.',
            ],
            'jx_councils1' => [
                'bucket' => 'archive_now_remodel_later',
                'rule_key' => 'legacy_council_archive',
                'high_risk' => true,
                'identity_columns' => ['ar_name', 'en_name', 'email'],
                'file_columns' => ['photo', 'cv'],
                'notes' => 'Legacy council/person rows are archived until profile target mappings are approved.',
            ],
        ],
        'faqs' => [
            'jx_faqs' => [
                'bucket' => 'archive_now_remodel_later',
                'rule_key' => 'legacy_faq_archive',
                'high_risk' => false,
                'identity_columns' => ['subject'],
                'date_columns' => ['post_date'],
                'notes' => 'FAQs are archived for possible later module rebuild; duplicates are decision-planned first.',
            ],
        ],
        'complaints' => [
            'jx_complaint_cats' => [
                'bucket' => 'archive_now_remodel_later',
                'rule_key' => 'legacy_complaint_category_archive',
                'high_risk' => false,
                'identity_columns' => ['ar_name', 'en_name', 'email'],
                'file_columns' => ['photo'],
                'notes' => 'Complaint categories are archived; public CRM is out of current foundation scope.',
            ],
            'jx_complaints' => [
                'bucket' => 'retire_after_approval',
                'rule_key' => 'legacy_complaint_retire',
                'high_risk' => false,
                'identity_columns' => ['first_name', 'last_name', 'email'],
                'date_columns' => ['post_date'],
                'notes' => 'Old complaint/contact rows are not imported publicly without explicit privacy approval.',
            ],
        ],
        'career_links' => [
            'jx_job_sites' => [
                'bucket' => 'redirect_to_equivalent',
                'rule_key' => 'legacy_job_link_candidate',
                'high_risk' => false,
                'identity_columns' => ['ar_name', 'en_name'],
                'file_columns' => ['photo'],
                'url_columns' => ['url'],
                'date_columns' => ['added_date'],
                'notes' => 'Job links are link candidates only; final publication remains gated.',
            ],
        ],
        'alumni' => [
            'jx_graduated_students' => [
                'bucket' => 'archive_now_remodel_later',
                'rule_key' => 'legacy_alumni_archive',
                'high_risk' => false,
                'identity_columns' => ['ar_name', 'en_name'],
                'file_columns' => ['photo'],
                'date_columns' => ['post_date', 'date_year'],
                'notes' => 'Alumni rows are archive candidates; duplicates must be resolved before any publication.',
            ],
        ],
        'honor_students' => [
            'jx_good_students' => [
                'bucket' => 'archive_now_remodel_later',
                'rule_key' => 'legacy_honor_student_archive',
                'high_risk' => false,
                'identity_columns' => ['ar_name', 'en_name'],
                'file_columns' => ['photo'],
                'date_columns' => ['post_date', 'date_year'],
                'notes' => 'Honor student rows are archive candidates; duplicates must be resolved before any publication.',
            ],
        ],
        'countries' => [
            'jx_countries' => [
                'bucket' => 'archive_now_remodel_later',
                'rule_key' => 'legacy_lookup_archive',
                'high_risk' => false,
                'identity_columns' => ['ar_name', 'en_name'],
                'notes' => 'Lookup values are archived as source evidence; modern lookup use requires explicit mapping.',
            ],
        ],
        'cities' => [
            'jx_cities' => [
                'bucket' => 'archive_now_remodel_later',
                'rule_key' => 'legacy_lookup_archive',
                'high_risk' => false,
                'identity_columns' => ['ar_name', 'en_name'],
                'notes' => 'Lookup values are archived as source evidence; modern lookup use requires explicit mapping.',
            ],
        ],
    ],

    'future_module_map' => [
        'jx_members' => 'faculty_members',
        'jx_councils' => 'councils',
        'jx_member_items' => 'members_archive',
        'jx_categories' => 'typed_legacy_content_registry',
        'jx_items' => 'typed_legacy_child_content',
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
