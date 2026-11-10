<?php

declare(strict_types=1);

namespace App\Support;

/**
 * The headings of the four groups on a faculty's members page (leadership,
 * heads of departments, council members, teaching staff). Editors may rename
 * each one per language in the members page editor (`payload.groupLabels`);
 * a heading left empty keeps the default wording below.
 */
final class FacultyMemberGroups
{
    public const GROUPS = ['leadership', 'head', 'council', 'staff'];

    private const DEFAULTS = [
        'ar' => [
            'leadership' => 'قيادة الكلية',
            'head' => 'رؤساء الأقسام',
            'council' => 'أعضاء مجلس الكلية',
            'staff' => 'أعضاء الهيئة التدريسية',
        ],
        'en' => [
            'leadership' => 'Faculty Leadership',
            'head' => 'Heads of Departments',
            'council' => 'Council Members',
            'staff' => 'Academic Staff',
        ],
    ];

    /** @return array<string, string> The default heading of each group in that language. */
    public static function defaults(string $locale): array
    {
        return self::DEFAULTS[$locale === 'ar' ? 'ar' : 'en'];
    }

    /**
     * @param  mixed  $saved  The page's `payload.groupLabels`, as saved by the editor.
     * @return array<string, string> Each group's heading: the editor's wording, else the default.
     */
    public static function labels(mixed $saved, string $locale): array
    {
        $saved = is_array($saved) ? $saved : [];
        $labels = [];

        foreach (self::defaults($locale) as $group => $default) {
            $value = is_string($saved[$group] ?? null) ? trim($saved[$group]) : '';
            $labels[$group] = $value !== '' ? $value : $default;
        }

        return $labels;
    }
}
