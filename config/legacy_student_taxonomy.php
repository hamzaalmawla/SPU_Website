<?php

declare(strict_types=1);

return [
    // In the legacy student tables, the column named `department_id` is actually a faculty code.
    'legacy_department_id_is_faculty_code' => true,

    'faculty_code_map' => [
        2 => [
            'canonical_slug' => 'medicine',
            'legacy_alumni_category_id' => 315,
            'legacy_service_type' => 22,
            'ar_name' => 'كلية الطب البشري',
            'en_name' => 'Faculty of Medicine',
            'evidence_url' => 'https://www.spu.edu.sy/alumni/index.php?page=list&ex=2&dir=graduated_students&lang=1&d=2',
        ],
        3 => [
            'canonical_slug' => 'dentistry',
            'legacy_alumni_category_id' => 4663,
            'legacy_service_type' => 32,
            'ar_name' => 'كلية طب الأسنان',
            'en_name' => 'Faculty of Dentistry',
            'evidence_url' => 'https://www.spu.edu.sy/alumni/index.php?page=list&ex=2&dir=graduated_students&lang=1&d=3',
        ],
        4 => [
            'canonical_slug' => 'pharmacy',
            'legacy_alumni_category_id' => 4664,
            'legacy_service_type' => 42,
            'ar_name' => 'كلية الصيدلة',
            'en_name' => 'Faculty of Pharmacy',
            'evidence_url' => 'https://www.spu.edu.sy/alumni/index.php?page=list&ex=2&dir=graduated_students&lang=1&d=4',
        ],
        5 => [
            'canonical_slug' => 'ai-engineering',
            'legacy_alumni_category_id' => 4662,
            'legacy_service_type' => 52,
            'ar_name' => 'كلية هندسة الحاسوب والمعلوماتية',
            'en_name' => 'Faculty of Computer Engineering and Informatics',
            'evidence_url' => 'https://www.spu.edu.sy/alumni/index.php?page=list&ex=2&dir=graduated_students&lang=1&d=5',
        ],
        6 => [
            'canonical_slug' => 'petroleum',
            'legacy_alumni_category_id' => 4665,
            'legacy_service_type' => 62,
            'ar_name' => 'كلية هندسة البترول',
            'en_name' => 'Faculty of Petroleum Engineering',
            'evidence_url' => 'https://www.spu.edu.sy/alumni/index.php?page=list&ex=2&dir=graduated_students&lang=1&d=6',
        ],
        7 => [
            'canonical_slug' => 'business',
            'legacy_alumni_category_id' => 4666,
            'legacy_service_type' => 72,
            'ar_name' => 'كلية العلوم الإدارية',
            'en_name' => 'Faculty of Administrative Sciences',
            'evidence_url' => 'https://www.spu.edu.sy/alumni/index.php?page=list&ex=2&dir=graduated_students&lang=1&d=7',
        ],
    ],

    // The legacy student tables also expose a `section_id`, but inspection showed only two shared buckets
    // across all six faculties. There is no verified one-to-one mapping from these values to the new
    // `departments` table, so student imports must preserve the raw value and leave `department_id` null.
    'section_code_map' => [
        1 => [
            'target_department_slug' => null,
            'notes' => 'Legacy section bucket 1. Preserve raw value; do not map to departments.',
        ],
        2 => [
            'target_department_slug' => null,
            'notes' => 'Legacy section bucket 2. Preserve raw value; do not map to departments.',
        ],
    ],
];
