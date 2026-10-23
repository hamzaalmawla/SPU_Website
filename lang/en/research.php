<?php

declare(strict_types=1);

// Names for the research office's register codes, shared by the admin panel
// and the public research pages.
return [
    'publication_types' => [
        'original_research' => 'Original research',
        'review_article' => 'Review article',
        'conference_paper' => 'Conference paper',
        'book_chapter' => 'Book chapter',
        'case_report' => 'Case report',
        'other' => 'Other',
    ],
    'study_types' => [
        'fundamental' => 'Fundamental',
        'applied' => 'Applied',
        'clinical' => 'Clinical',
        'theoretical' => 'Theoretical',
        'empirical' => 'Empirical',
        'other' => 'Other',
    ],
    'roles' => [
        'lead_author' => 'Lead author',
        'corresponding_author' => 'Corresponding author',
        'co_author' => 'Co-author',
        'supervisor' => 'Supervisor',
        'contributor' => 'Contributor',
    ],
    // Researcher directory filters, when the CMS page leaves them empty.
    'directory' => [
        'search_placeholder' => 'Search by name or expertise',
        'all_faculties' => 'All faculties',
        'all_expertise' => 'All expertise',
    ],
];
