<?php

declare(strict_types=1);

namespace App\Support;

use App\DTOs\Content\DirectorateManagerDTO;

/**
 * A directorate manager's fields, typed on the directorate: the name and the
 * education per language (directorate_translations.manager_name and
 * manager_education_json), the email and phone once (directorates).
 */
final class DirectorateManagerFields
{
    /**
     * Education as a clean list: a list of strings, or text with one
     * qualification per line, as the admin textarea sends it.
     *
     * @return list<string>
     */
    public static function education(mixed $value): array
    {
        if (is_string($value)) {
            $value = preg_split('/\r\n|\r|\n/', $value) ?: [];
        }

        if (! is_array($value)) {
            return [];
        }

        $lines = [];
        foreach ($value as $line) {
            $line = is_string($line) ? trim($line) : '';
            if ($line !== '' && ! in_array($line, $lines, true)) {
                $lines[] = mb_substr($line, 0, 255);
            }
        }

        return $lines;
    }

    /** The manager to show, or null while no name is given. */
    public static function manager(mixed $name, mixed $education, mixed $email, mixed $phone, mixed $photo = null): ?DirectorateManagerDTO
    {
        $name = is_string($name) ? trim($name) : '';
        if ($name === '') {
            return null;
        }

        return new DirectorateManagerDTO(
            name: $name,
            education: self::education($education),
            email: self::text($email),
            phone: self::text($phone),
            photo: self::photo($photo),
        );
    }

    /** A site path or an http(s) address; anything else shows no photo. */
    public static function photo(mixed $value): ?string
    {
        return UrlSanitizer::sanitize(self::text($value), ['http', 'https']);
    }

    private static function text(mixed $value): ?string
    {
        $value = is_string($value) ? trim($value) : '';

        return $value !== '' ? $value : null;
    }
}
