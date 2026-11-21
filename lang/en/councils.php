<?php

declare(strict_types=1);

return [
    // The headings a new council starts with; editors change them per council.
    'defaults' => [
        'members_title' => 'Council Members',
        'members_subtitle' => 'The council head and members',
        'head_label' => 'Council Head',
        'member_label' => 'Council Member',
        'profile_label' => 'View profile',
        'functions_title' => 'Council Functions',
        'functions_subtitle' => 'What the council does',
    ],
    // The Councils page under About until an editor publishes its own text.
    'listing' => [
        'title' => 'University Councils',
        'summary' => 'The councils that plan and follow up the university\'s academic and administrative affairs, with each council\'s members and functions.',
    ],
    'card' => [
        'head' => 'Council head',
        'members' => 'Members: :count',
        'functions' => 'Functions: :count',
        'open' => 'View council',
    ],
    'breadcrumb' => 'Councils',
    'empty' => 'No councils have been published yet.',
];
