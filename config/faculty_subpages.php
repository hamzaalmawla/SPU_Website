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
    | A faculty shows one of these only when it also has the page enabled.
    |
    */
    'restricted' => [
        'training' => ['pharmacy'],
        'clinical-cases' => ['dentistry'],
        'final-exams' => ['medicine', 'dentistry', 'pharmacy', 'artificial-intelligence', 'building-construction-engineering', 'petroleum', 'business-administration'],
    ],
];
