<?php

declare(strict_types=1);

// Names for the research office's register codes, shared by the admin panel
// and the public research pages.
return [
    'publication_types' => [
        'original_research' => 'بحث أصيل',
        'review_article' => 'مقالة مراجعة',
        'conference_paper' => 'ورقة مؤتمر',
        'book_chapter' => 'فصل في كتاب',
        'case_report' => 'تقرير حالة',
        'other' => 'أخرى',
    ],
    'study_types' => [
        'fundamental' => 'أساسية',
        'applied' => 'تطبيقية',
        'clinical' => 'سريرية',
        'theoretical' => 'نظرية',
        'empirical' => 'تجريبية',
        'other' => 'أخرى',
    ],
    'roles' => [
        'lead_author' => 'المؤلف الرئيسي',
        'corresponding_author' => 'المؤلف المراسل',
        'co_author' => 'مؤلف مشارك',
        'supervisor' => 'مشرف',
        'contributor' => 'مساهم',
    ],
];
