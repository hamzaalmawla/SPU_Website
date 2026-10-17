<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Support\Str;

/**
 * The public slug of a database research publication.
 *
 * Rows without a stored slug derive it from the English (else Arabic) title
 * and a numeric suffix: the legacy source id for imported rows, the primary
 * key otherwise. The admin editor stores the derived value on publish, so a
 * later title edit keeps the URL.
 */
final class ResearchPublicationSlug
{
    public static function derive(?string $englishTitle, ?string $arabicTitle, int $suffix): string
    {
        $title = trim((string) $englishTitle) !== '' ? (string) $englishTitle : (string) $arabicTitle;
        $slug = Str::slug($title !== '' ? $title : 'legacy-research-publication');

        return ($slug !== '' ? $slug : 'legacy-research-publication').'-'.$suffix;
    }

    public static function isValid(string $slug): bool
    {
        return mb_strlen($slug) <= 191 && preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $slug) === 1;
    }
}
