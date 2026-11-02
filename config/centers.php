<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | University centers
    |--------------------------------------------------------------------------
    |
    | Each center is a public page at /{locale}/centers/{slug}, listed as a card
    | on /{locale}/centers in this order. `template` picks how the center is
    | built; `requirements-unit` is the only template so far (a page with its
    | staff, required courses and elective courses). Default content lives in
    | database/seeders/Data/centers/. A center is part of the university, not
    | a faculty: it has a manager instead of a dean and no projects, research
    | or labs.
    |
    */
    'centers' => [
        'requirements-unit' => [
            'template' => 'requirements-unit',
        ],
    ],
];
