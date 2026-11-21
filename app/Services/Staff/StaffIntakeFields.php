<?php

declare(strict_types=1);

namespace App\Services\Staff;

use App\Models\Person\FacultyMember;
use App\Models\Person\FacultyMemberEducation;

/**
 * The intake form's fields and how they map onto a faculty member. A request
 * stores the teacher's values in this flat shape, and a snapshot of the
 * profile in the same shape, so the two compare field by field.
 */
final class StaffIntakeFields
{
    /** Text fields per language: form key suffix => faculty_member_translations column. */
    public const TRANSLATED = [
        'name' => 'full_name',
        'title' => 'title',
        'position' => 'position',
        'bio' => 'bio',
        'specializations' => 'specializations',
    ];

    public const CONTACT = ['email', 'phone', 'office_location'];

    /** The social_links keys the admin profile form edits. */
    public const LINKS = ['linkedin', 'orcid', 'scopus', 'researchgate', 'scholar', 'twitter'];

    public const EDUCATION = ['degree_ar', 'degree_en', 'institution_ar', 'institution_en', 'field_ar', 'field_en', 'year_start', 'year_end'];

    /**
     * Fields a link may make required, in form order. Both names are always
     * required and are not listed; the department exists only on faculty links.
     */
    public const REQUIRABLE = [
        'title_ar', 'title_en', 'position_ar', 'position_en', 'bio_ar', 'bio_en',
        'specializations_ar', 'specializations_en', 'department_id', 'office_location', 'email', 'phone',
        'linkedin', 'orcid', 'scopus', 'researchgate', 'scholar', 'twitter', 'photo', 'cv',
    ];

    /** Required on a faculty link unless the link picks its own. */
    public const FACULTY_DEFAULT_REQUIRED = ['bio_ar', 'bio_en', 'photo', 'cv'];

    /**
     * Required on a center link unless the link picks its own: a center's staff
     * page shows the title and position. The phone is optional (2026-11-04).
     */
    public const CENTER_DEFAULT_REQUIRED = ['title_ar', 'title_en', 'position_ar', 'position_en', 'bio_ar', 'bio_en', 'email', 'photo', 'cv'];

    /**
     * The fields a link of this kind may make required.
     *
     * @return list<string>
     */
    public static function requirable(bool $isCenter): array
    {
        return array_values(array_filter(self::REQUIRABLE, static fn (string $key): bool => ! $isCenter || $key !== 'department_id'));
    }

    /** @return list<string> */
    public static function defaultRequired(bool $isCenter): array
    {
        return $isCenter ? self::CENTER_DEFAULT_REQUIRED : self::FACULTY_DEFAULT_REQUIRED;
    }

    /**
     * A link's required fields: the ones it stored, kept to those its kind
     * may require and in form order, or the defaults when it stored none.
     *
     * @return list<string>
     */
    public static function required(mixed $stored, bool $isCenter): array
    {
        if (! is_array($stored)) {
            return self::defaultRequired($isCenter);
        }

        return array_values(array_intersect(self::requirable($isCenter), array_filter($stored, 'is_string')));
    }

    /**
     * What a teacher types as a profile link, made into the address it means:
     * "orcid.org/…" or "www.…" gets https://, a bare ORCID iD its orcid.org
     * address and an @handle its x.com address. Anything else is returned as
     * typed, for the url rule to refuse with a message beside the field.
     */
    public static function link(string $field, string $value): string
    {
        $value = trim($value);
        if ($value === '' || preg_match('#^https?://#i', $value) === 1) {
            return $value;
        }

        if ($field === 'orcid' && preg_match('/^\d{4}-\d{4}-\d{4}-\d{3}[\dXx]$/', $value) === 1) {
            return 'https://orcid.org/'.strtoupper($value);
        }

        if ($field === 'twitter' && preg_match('/^@(\w{1,15})$/', $value, $handle) === 1) {
            return 'https://x.com/'.$handle[1];
        }

        if (preg_match('#^(//)?[^\s/@]+\.[a-z]{2,}(/\S*)?$#i', $value) === 1) {
            return 'https://'.ltrim($value, '/');
        }

        return $value;
    }

    /** @return list<string> Every flat field key, in form order. */
    public static function keys(): array
    {
        $keys = [];
        foreach (array_keys(self::TRANSLATED) as $field) {
            $keys[] = $field.'_ar';
            $keys[] = $field.'_en';
        }

        return [...$keys, 'department_id', ...self::CONTACT, ...self::LINKS];
    }

    /** @return array<string, mixed> */
    public static function blank(): array
    {
        $fields = array_fill_keys(self::keys(), null);
        $fields['specializations_ar'] = [];
        $fields['specializations_en'] = [];

        return $fields;
    }

    /** @return array<string, mixed> The member's values; relations must be loaded. */
    public static function fromMember(FacultyMember $member): array
    {
        $fields = self::blank();

        foreach (['ar', 'en'] as $locale) {
            $translation = $member->translations->firstWhere('locale', $locale);
            foreach (self::TRANSLATED as $field => $column) {
                $value = $translation?->getAttribute($column);
                $fields[$field.'_'.$locale] = $field === 'specializations'
                    ? self::list($value)
                    : (is_string($value) && trim($value) !== '' ? $value : null);
            }
        }

        $fields['department_id'] = $member->department_id !== null ? (int) $member->department_id : null;
        foreach (self::CONTACT as $field) {
            $value = $member->getAttribute($field);
            $fields[$field] = is_string($value) && $value !== '' ? $value : null;
        }

        $links = $member->getAttribute('social_links');
        $links = is_array($links) ? $links : [];
        foreach (self::LINKS as $field) {
            $fields[$field] = is_string($links[$field] ?? null) && $links[$field] !== '' ? $links[$field] : null;
        }

        return $fields;
    }

    /** @return list<array<string, mixed>> */
    public static function educationsFromMember(FacultyMember $member): array
    {
        return $member->educations
            ->map(function (FacultyMemberEducation $education): array {
                $ar = $education->translations->firstWhere('locale', 'ar');
                $en = $education->translations->firstWhere('locale', 'en');
                $start = $ar?->year_start ?? $en?->year_start;
                $end = $ar?->year_end ?? $en?->year_end;

                return [
                    'id' => (int) $education->getKey(),
                    'degree_ar' => $ar?->degree,
                    'degree_en' => $en?->degree,
                    'institution_ar' => $ar?->institution,
                    'institution_en' => $en?->institution,
                    'field_ar' => $ar?->field_of_study,
                    'field_en' => $en?->field_of_study,
                    'year_start' => $start !== null ? (int) $start : null,
                    'year_end' => $end !== null ? (int) $end : null,
                ];
            })
            ->values()
            ->all();
    }

    /** @return list<string> */
    public static function list(mixed $value): array
    {
        if (is_string($value)) {
            $value = preg_split('/[,;|،؛\r\n]+/u', $value) ?: [];
        }

        if (! is_array($value)) {
            return [];
        }

        $items = [];
        foreach ($value as $item) {
            $item = is_array($item) ? ($item['name'] ?? null) : $item;
            if (is_string($item) && trim($item) !== '' && ! in_array(trim($item), $items, true)) {
                $items[] = trim($item);
            }
        }

        return $items;
    }

    /**
     * Keys whose value differs between two field sets, plus "educations",
     * "photo" and "cv" when those changed.
     *
     * @param  array<string, mixed>  $before
     * @param  array<string, mixed>  $after
     * @param  list<array<string, mixed>>  $educationsBefore
     * @param  list<array<string, mixed>>  $educationsAfter
     * @param  array<string, mixed>  $uploads
     * @return list<string>
     */
    public static function changedKeys(array $before, array $after, array $educationsBefore, array $educationsAfter, array $uploads = []): array
    {
        $changed = [];
        foreach (self::keys() as $key) {
            if (self::comparable($before[$key] ?? null) !== self::comparable($after[$key] ?? null)) {
                $changed[] = $key;
            }
        }

        if (self::comparableEducations($educationsBefore) !== self::comparableEducations($educationsAfter)) {
            $changed[] = 'educations';
        }

        foreach (['photo', 'cv'] as $kind) {
            if (is_array($uploads[$kind] ?? null)) {
                $changed[] = $kind;
            }
        }

        return $changed;
    }

    private static function comparable(mixed $value): string
    {
        if (is_array($value)) {
            return implode("\n", self::list($value));
        }

        return trim((string) $value);
    }

    /** @param list<array<string, mixed>> $educations */
    private static function comparableEducations(array $educations): string
    {
        return implode("\n", array_map(
            static fn (array $row): string => implode('|', array_map(
                static fn (string $key): string => trim((string) ($row[$key] ?? '')),
                self::EDUCATION,
            )),
            $educations,
        ));
    }
}
