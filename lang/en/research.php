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
    // The research landing's newest publication or project (picked by date, not by an editor).
    'latest' => [
        'title' => 'Latest research',
        'publication' => 'Publication',
        'project' => 'Research project',
        'authors' => 'Authors',
        'faculty' => 'Faculty',
        'published' => 'Published',
        'period' => 'Period',
        'since' => 'Since :year',
        'read_publication' => 'Read the publication',
        'view_project' => 'View the project',
        'download' => 'Download the file',
        'view_doi' => 'View via DOI',
        'doi' => 'DOI',
        'statuses' => [
            'ongoing' => 'Ongoing',
            'completed' => 'Completed',
            'paused' => 'Paused',
        ],
    ],
    // Researcher directory filters, when the CMS page leaves them empty.
    'directory' => [
        'search_placeholder' => 'Search by name or expertise',
        'all_faculties' => 'All faculties',
        'all_expertise' => 'All expertise',
    ],
];
