<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Faculty-specific subpages
    |--------------------------------------------------------------------------
    |
    | Subpages that only some faculties have, keyed by slug, with the faculties
    | allowed to use each one. Every other subpage belongs to all faculties.
    | A faculty shows one of these only when it also has the page enabled, or,
    | for a default subpage (FacultyPageService::DEFAULT_SUBPAGES), no page row.
    |
    */
    'restricted' => [
        'training' => ['pharmacy'],
        'clinical-cases' => ['dentistry'],
        'master-program' => ['artificial-intelligence', 'pharmacy', 'petroleum'],
        'final-exams' => ['medicine', 'dentistry', 'pharmacy', 'artificial-intelligence', 'building-construction-engineering', 'petroleum', 'business-administration'],
    ],
];
