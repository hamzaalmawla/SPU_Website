<?php

declare(strict_types=1);

namespace App\Support;

/**
 * A teaching staff member's academic rank (الرتبة الأكاديمية), kept apart from the
 * positions they hold (dean, vice dean, head of department, ...). The rank is the
 * text typed on the profile (`faculty_member_translations.position`). The three
 * ranks are recognised in either language and shown under one name:
 * أستاذ / Professor, أستاذ مساعد / Associate Professor, مدرس / Lecturer.
 * A leadership title typed there instead (for example "عميد كلية الطب") is not a
 * rank: it is returned as a position, so it is never shown as "Academic rank: Dean".
 */
final class AcademicRank
{
    /** @var array<string, array{ar: string, en: string}> */
    public const RANKS = [
        'professor' => ['ar' => 'أستاذ', 'en' => 'Professor'],
        'associate_professor' => ['ar' => 'أستاذ مساعد', 'en' => 'Associate Professor'],
        'lecturer' => ['ar' => 'مدرس', 'en' => 'Lecturer'],
    ];

    /** Spellings (folded), checked in order: the longer one first. @var array<string, list<string>> */
    private const SPELLINGS = [
        'associate_professor' => ['استاذ مساعد', 'استاذه مساعده', 'استاذ مشارك', 'associate professor', 'assoc. prof', 'assoc prof'],
        'lecturer' => ['مدرس', 'مدرسه', 'lecturer', 'assistant professor', 'asst. prof', 'asst prof'],
        'professor' => ['استاذ دكتور', 'استاذ', 'استاذه', 'ا.د', 'ا. د', 'بروفيسور', 'professor', 'prof.', 'prof'],
    ];

    /** Words that make a typed text a position held, not a rank. */
    private const POSITION_WORDS = ['عميد', 'عميده', 'نائب', 'نائبه', 'رئيس', 'رئيسه', 'مدير', 'مديره', 'امين', 'امينه', 'منسق', 'منسقه', 'مقرر',
        'dean', 'vice', 'head', 'chair', 'director', 'rector', 'president', 'secretary', 'coordinator'];

    /**
     * The rank and, when the typed text is a leadership title instead, that title.
     * Either language's text may name the rank; the shown rank is in `$locale`.
     *
     * @return array{rank: ?string, position: ?string}
     */
    public static function read(?string $typed, ?string $otherLocaleTyped, string $locale): array
    {
        $typed = trim((string) $typed);
        $other = trim((string) $otherLocaleTyped);
        $key = self::key($typed) ?? self::key($other);

        if ($key !== null) {
            return ['rank' => self::RANKS[$key][$locale === 'ar' ? 'ar' : 'en'], 'position' => null];
        }

        if ($typed === '') {
            return ['rank' => null, 'position' => null];
        }

        // Another rank name (teaching assistant, ...) shows as typed; a leadership title is a position.
        return self::isPosition($typed) ? ['rank' => null, 'position' => $typed] : ['rank' => $typed, 'position' => null];
    }

    /**
     * The rank of a profile from its translations (each with `locale` and `position`).
     *
     * @param  iterable<object>  $translations
     * @return array{rank: ?string, position: ?string}
     */
    public static function ofProfile(iterable $translations, string $locale): array
    {
        $typed = ['ar' => null, 'en' => null];

        foreach ($translations as $translation) {
            $translationLocale = (string) ($translation->locale ?? '');

            if (array_key_exists($translationLocale, $typed)) {
                $typed[$translationLocale] = is_string($translation->position ?? null) ? $translation->position : null;
            }
        }

        $other = $locale === 'ar' ? 'en' : 'ar';
        $own = trim((string) $typed[$locale === 'ar' ? 'ar' : 'en']);

        // A profile with text in the other language only shows it as is.
        return $own !== '' ? self::read($own, $typed[$other], $locale) : self::read($typed[$other], null, $locale);
    }

    /** The rank a typed text names (`professor`, `associate_professor`, `lecturer`), or null. */
    public static function key(?string $typed): ?string
    {
        $folded = self::fold((string) $typed);

        if ($folded === '' || self::isPosition((string) $typed)) {
            return null;
        }

        // A title before the rank ("د. أستاذ مساعد") does not hide it.
        $folded = (string) preg_replace('/^(?:د\.?|دكتور|دكتوره|dr\.?)\s+/u', '', $folded);

        // A teaching or research assistant (مدرس مساعد، معيد) holds none of the three ranks.
        if (preg_match('/^(?:مدرس|مدرسه)\s+مساعد/u', $folded) === 1) {
            return null;
        }

        foreach (self::SPELLINGS as $key => $spellings) {
            foreach ($spellings as $spelling) {
                if ($folded === rtrim($spelling) || str_starts_with($folded, rtrim($spelling).' ') || str_starts_with($folded, $spelling) && ! preg_match('/^\p{L}/u', substr($folded, strlen($spelling)))) {
                    return $key;
                }
            }
        }

        return null;
    }

    public static function isPosition(string $typed): bool
    {
        $words = preg_split('/[\s,،.\-\/|]+/u', self::fold($typed)) ?: [];

        return array_intersect($words, array_map(self::fold(...), self::POSITION_WORDS)) !== [];
    }

    private static function fold(string $value): string
    {
        return trim(mb_strtolower(SearchTextNormalizer::normalize(trim($value))));
    }
}
