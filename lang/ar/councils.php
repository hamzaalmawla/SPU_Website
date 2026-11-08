<?php

declare(strict_types=1);

return [
    // The headings a new council starts with; editors change them per council.
    'defaults' => [
        'members_title' => 'أعضاء المجلس',
        'members_subtitle' => 'رئيس المجلس وأعضاؤه',
        'head_label' => 'رئيس المجلس',
        'member_label' => 'عضو المجلس',
        'profile_label' => 'عرض الملف الشخصي',
        'functions_title' => 'مهام المجلس',
        'functions_subtitle' => 'ما يقوم به المجلس',
    ],
    // The Councils page under About until an editor publishes its own text.
    'listing' => [
        'title' => 'مجالس الجامعة',
        'summary' => 'المجالس التي تخطط لشؤون الجامعة العلمية والإدارية وتتابعها، مع أعضاء كل مجلس ومهامه.',
    ],
    'card' => [
        'head' => 'رئيس المجلس',
        'members' => 'الأعضاء: :count',
        'functions' => 'المهام: :count',
        'open' => 'عرض المجلس',
    ],
    'breadcrumb' => 'المجالس',
    'empty' => 'لا توجد مجالس منشورة بعد.',
];
