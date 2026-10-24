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
