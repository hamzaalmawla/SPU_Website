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

    /**
     * A derived slug short enough to store, with room left for a "-2" style
     * suffix: long titles are cut at a word, and the numeric suffix is kept.
     * Only for slugs about to be stored; a stored or live slug never changes.
     */
    public static function storable(string $slug, int $maxLength = 180): string
    {
        if (mb_strlen($slug) <= $maxLength || preg_match('/^(.*)-(\d+)$/', $slug, $matches) !== 1) {
            return mb_substr($slug, 0, $maxLength);
        }

        $room = $maxLength - mb_strlen($matches[2]) - 1;
        $head = mb_substr($matches[1], 0, $room);
        $cut = mb_strrpos($head, '-');
        $head = $cut !== false && $cut > $room / 2 ? mb_substr($head, 0, $cut) : rtrim($head, '-');

        return $head.'-'.$matches[2];
    }

    public static function isValid(string $slug): bool
    {
        return mb_strlen($slug) <= 191 && preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $slug) === 1;
    }
}
