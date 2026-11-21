<?php

declare(strict_types=1);

namespace App\Support;

/**
 * The small line under the faculty logo on a faculty page. Editors can change
 * or clear it; until they have saved one, it is built from the English name
 * ("FACULTY OF CONSTRUCTION ENGINEERING").
 */
final class FacultyLogoSubtitle
{
    public static function fromEnglishName(string $name): string
    {
        $name = trim($name);

        if ($name === '') {
            return '';
        }

        return mb_strtoupper((string) preg_replace('/^(faculty of\s+)?/i', 'Faculty of ', $name, 1), 'UTF-8');
    }
}
