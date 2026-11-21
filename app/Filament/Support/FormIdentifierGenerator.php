<?php

declare(strict_types=1);

namespace App\Filament\Support;

use Illuminate\Support\Str;

/**
 * Intelligent identifier generator for dynamic forms:
 * - Generates clean, regex-valid programmatic field names (snake_case)
 * - Generates form slugs/keys (kebab-case) and option values
 * - Supports automatic derivation from English or Arabic labels
 * - Includes a comprehensive university semantic dictionary for Arabic terms
 * - Provides phonetic Arabic transliteration for arbitrary Arabic phrases
 * - Enforces regex constraints, avoids reserved system keywords, and handles deduplication
 */
final class FormIdentifierGenerator
{
    /**
     * Reserved keywords that dynamic forms cannot use as raw field names.
     *
     * @var list<string>
     */
    public const RESERVED_FIELD_NAMES = [
        'event_source',
        'event_id',
        'job_id',
        'job_slug',
        'website',
        'form',
        '_token',
        '_context',
    ];

    /**
     * High-frequency Arabic semantic dictionary for university and public forms.
     *
     * @var array<string, string>
     */
    private const ARABIC_FIELD_SEMANTIC_MAP = [
        // Identity / Personal Information
        'الاسم' => 'full_name',
        'الاسم الكامل' => 'full_name',
        'الاسم الثلاثي' => 'full_name',
        'اسم الطالب' => 'student_name',
        'اسم المتقدم' => 'applicant_name',
        'اسم المشارك' => 'participant_name',
        'الاسم الاول' => 'first_name',
        'الاسم الثاني' => 'second_name',
        'اسم الاب' => 'father_name',
        'اسم الام' => 'mother_name',
        'الكنية' => 'last_name',
        'اللقب' => 'last_name',
        'النسبة' => 'last_name',
        'اسم العائلة' => 'family_name',
        'الرقم الوطني' => 'national_id',
        'الهوية الوطنية' => 'national_id',
        'رقم الهوية' => 'national_id',
        'رقم البطاقة الشخصية' => 'national_id',
        'الرقم الجامعي' => 'student_id',
        'رقم القيد' => 'registration_number',
        'تاريخ الميلاد' => 'birth_date',
        'تاريخ الولادة' => 'birth_date',
        'مكان الميلاد' => 'birth_place',
        'محل الولادة' => 'birth_place',
        'الجنس' => 'gender',
        'النوع' => 'gender',
        'الجنسية' => 'nationality',
        'الحالة الاجتماعية' => 'marital_status',

        // Contact Information
        'البريد الالكتروني' => 'email',
        'البريد' => 'email',
        'الايميل' => 'email',
        'رقم الهاتف' => 'phone',
        'الهاتف' => 'phone',
        'رقم الجوال' => 'mobile',
        'الجوال' => 'mobile',
        'الموبايل' => 'mobile',
        'رقم الموبايل' => 'mobile',
        'رقم الواتساب' => 'whatsapp',
        'الواتساب' => 'whatsapp',
        'واتساب' => 'whatsapp',
        'رقم الهاتف الارضي' => 'landline_phone',
        'الهاتف الثابت' => 'landline_phone',
        'العنوان' => 'address',
        'عنوان السكن' => 'residence_address',
        'مكان الاقامة' => 'residence_address',
        'السكن' => 'address',
        'المدينة' => 'city',
        'المحافظة' => 'governorate',
        'الدولة' => 'country',
        'البلد' => 'country',
        'الرمز البريدي' => 'postal_code',

        // Academic / Educational
        'الكلية' => 'faculty',
        'القسم' => 'department',
        'التخصص' => 'specialization',
        'الاختصاص' => 'specialization',
        'الفرع' => 'branch',
        'السنة الدراسية' => 'academic_year',
        'السنة' => 'academic_year',
        'الفصل الدراسي' => 'semester',
        'الفصل' => 'semester',
        'المعدل' => 'gpa',
        'المعدل التراكمي' => 'cgpa',
        'مجموع الثانوية' => 'high_school_score',
        'علامات الثانوية' => 'high_school_score',
        'درجات الثانوية' => 'high_school_score',
        'نوع الثانوية' => 'high_school_type',
        'فرع الثانوية' => 'high_school_branch',
        'سنة التخرج' => 'graduation_year',
        'تاريخ التخرج' => 'graduation_date',
        'المؤهل العلمي' => 'qualification',
        'التحصيل العلمي' => 'qualification',
        'الشهادة' => 'certificate',
        'الشهادة الثانوية' => 'high_school_certificate',
        'وثيقة التخرج' => 'graduation_document',
        'كشف العلامات' => 'transcript',
        'كشف درجات' => 'transcript',
        'الدرجة العلمية' => 'academic_degree',
        'عدد الساعات' => 'credit_hours',

        // Professional / Employment
        'السيرة الذاتية' => 'cv_resume',
        'ملف السيرة الذاتية' => 'cv_resume',
        'المسمى الوظيفي' => 'job_title',
        'الوظيفة' => 'job_title',
        'جهة العمل' => 'employer',
        'الشركة' => 'company',
        'المؤسسة' => 'institution',
        'سنوات الخبرة' => 'years_of_experience',
        'الخبرة السابقة' => 'previous_experience',
        'الراتب المتوقع' => 'expected_salary',
        'الراتب الحالي' => 'current_salary',

        // General / Notes / Attachments
        'الملاحظات' => 'notes',
        'ملاحظات' => 'notes',
        'ملاحظات اضافية' => 'additional_notes',
        'الرسالة' => 'message',
        'نص الرسالة' => 'message',
        'الموضوع' => 'subject',
        'عنوان الموضوع' => 'subject',
        'نوع الطلب' => 'request_type',
        'سبب الطلب' => 'request_reason',
        'المبررات' => 'reasons',
        'التفاصيل' => 'details',
        'الصورة الشخصية' => 'photo',
        'الصورة' => 'photo',
        'صورة شخصية' => 'photo',
        'المرفق' => 'attachment',
        'المرفقات' => 'attachments',
        'ملف اضافي' => 'additional_file',
        'الموافقة على الشروط' => 'accept_terms',
        'الشروط والاحكام' => 'terms_agreement',
    ];

    /**
     * Semantic dictionary for select option labels to values.
     *
     * @var array<string, string>
     */
    private const ARABIC_OPTION_SEMANTIC_MAP = [
        'نعم' => 'yes',
        'لا' => 'no',
        'ذكر' => 'male',
        'ذكور' => 'male',
        'انثى' => 'female',
        'اناث' => 'female',
        'الاولى' => 'first',
        'السنة الاولى' => 'first_year',
        'الثانية' => 'second',
        'السنة الثانية' => 'second_year',
        'الثالثة' => 'third',
        'السنة الثالثة' => 'third_year',
        'الرابعة' => 'fourth',
        'السنة الرابعة' => 'fourth_year',
        'الخامسة' => 'fifth',
        'السنة الخامسة' => 'fifth_year',
        'السادسة' => 'sixth',
        'السنة السادسة' => 'sixth_year',
        'علمي' => 'scientific',
        'ادبي' => 'literary',
        'سوري' => 'syrian',
        'غير سوري' => 'non_syrian',
        'عرب' => 'arab',
        'اجنبي' => 'foreign',
        'نظامي' => 'regular',
        'موازي' => 'parallel',
        'مقبول' => 'accepted',
        'مرفوض' => 'rejected',
        'قيد المعالجة' => 'pending',
        'جديد' => 'new',
        'مستمر' => 'continuing',
        'متخرج' => 'graduate',
        'خريج' => 'graduate',
        'دوام كامل' => 'full_time',
        'دوام جزئي' => 'part_time',
    ];

    /**
     * Phonetic character transliteration map for Arabic to Latin.
     *
     * @var array<string, string>
     */
    private const ARABIC_TRANSLITERATION_MAP = [
        'أ' => 'a', 'إ' => 'e', 'آ' => 'aa', 'ا' => 'a',
        'ب' => 'b', 'ت' => 't', 'ث' => 'th', 'ج' => 'j',
        'ح' => 'h', 'خ' => 'kh', 'د' => 'd', 'ذ' => 'dh',
        'ر' => 'r', 'ز' => 'z', 'س' => 's', 'ش' => 'sh',
        'ص' => 's', 'ض' => 'd', 'ط' => 't', 'ظ' => 'z',
        'ع' => 'a', 'غ' => 'gh', 'ف' => 'f', 'ق' => 'q',
        'ك' => 'k', 'ل' => 'l', 'م' => 'm', 'ن' => 'n',
        'ه' => 'h', 'و' => 'w', 'ي' => 'y', 'ى' => 'a',
        'ة' => 'a', 'ء' => 'a', 'ؤ' => 'o', 'ئ' => 'e',
        'پ' => 'p', 'چ' => 'ch', 'ڤ' => 'v', 'گ' => 'g',
    ];

    /**
     * Generates a valid, clean programmatic field identifier (snake_case, [a-zA-Z][a-zA-Z0-9_]*).
     *
     * @param  list<string>  $existingNames
     */
    public static function generateFieldName(?string $labelEn, ?string $labelAr = null, array $existingNames = []): string
    {
        $candidate = '';

        // Priority 1: Derive from English label if provided
        if (is_string($labelEn) && trim($labelEn) !== '') {
            $candidate = self::slugifyToSnakeCase($labelEn);
        }

        // Priority 2: Derive from Arabic semantic dictionary or transliteration
        if ($candidate === '' && is_string($labelAr) && trim($labelAr) !== '') {
            $normalizedAr = self::normalizeArabic($labelAr);
            $candidate = self::getNormalizedFieldMap()[$normalizedAr]
                ?? self::slugifyToSnakeCase(self::transliterateArabic($labelAr));
        }

        // Priority 3: Fallback generic field identifier
        if ($candidate === '') {
            $candidate = 'field_'.(count($existingNames) + 1);
        }

        // Enforce starting with an ASCII letter
        if (! preg_match('/^[a-zA-Z]/', $candidate)) {
            $candidate = 'field_'.$candidate;
        }

        // Avoid reserved keywords
        if (in_array(strtolower($candidate), self::RESERVED_FIELD_NAMES, true) || str_starts_with($candidate, '_')) {
            $candidate = 'field_'.ltrim($candidate, '_');
        }

        // Trim to safe length (max 50 chars to leave space for deduplication suffix)
        $candidate = substr($candidate, 0, 50);
        $candidate = rtrim($candidate, '_');

        return self::deduplicate($candidate, $existingNames);
    }

    /**
     * Generates a valid form slug/key (kebab-case, [a-z0-9]+(?:-[a-z0-9]+)*).
     */
    public static function generateFormKey(?string $nameEn, ?string $nameAr = null): string
    {
        $candidate = '';

        if (is_string($nameEn) && trim($nameEn) !== '') {
            $candidate = self::slugifyToKebabCase($nameEn);
        }

        if ($candidate === '' && is_string($nameAr) && trim($nameAr) !== '') {
            $candidate = self::slugifyToKebabCase(self::transliterateArabic($nameAr));
        }

        if ($candidate === '') {
            $candidate = 'form-'.substr(md5((string) microtime(true)), 0, 8);
        }

        return substr($candidate, 0, 80);
    }

    /**
     * Generates a valid option value (snake_case, letters and numbers, max 80 chars).
     *
     * @param  list<string>  $existingValues
     */
    public static function generateOptionValue(?string $labelEn, ?string $labelAr = null, array $existingValues = []): string
    {
        $candidate = '';

        if (is_string($labelEn) && trim($labelEn) !== '') {
            $candidate = self::slugifyToSnakeCase($labelEn);
        }

        if ($candidate === '' && is_string($labelAr) && trim($labelAr) !== '') {
            $normalizedAr = self::normalizeArabic($labelAr);
            $candidate = self::getNormalizedOptionMap()[$normalizedAr]
                ?? self::slugifyToSnakeCase(self::transliterateArabic($labelAr));
        }

        if ($candidate === '') {
            $candidate = 'opt_'.(count($existingValues) + 1);
        }

        if (! preg_match('/^[a-zA-Z]/', $candidate)) {
            $candidate = 'opt_'.$candidate;
        }

        $candidate = substr($candidate, 0, 60);

        return self::deduplicate($candidate, $existingValues);
    }

    /**
     * Convert text to snake_case ASCII identifier.
     */
    public static function slugifyToSnakeCase(string $text, int $maxLength = 50): string
    {
        $ascii = Str::ascii($text);
        $cleaned = preg_replace('/[^a-zA-Z0-9]+/u', '_', $ascii);
        $lowered = strtolower(trim((string) $cleaned, '_'));

        if ($lowered === '') {
            return '';
        }

        return substr($lowered, 0, $maxLength);
    }

    /**
     * Convert text to kebab-case ASCII slug.
     */
    public static function slugifyToKebabCase(string $text, int $maxLength = 60): string
    {
        $slug = Str::slug($text, '-');

        return substr($slug, 0, $maxLength);
    }

    /**
     * Normalize Arabic string by stripping diacritics, tatweel, and normalizing letter forms.
     */
    public static function normalizeArabic(string $text): string
    {
        // Remove diacritics (harakat)
        $text = (string) preg_replace('/[\x{064B}-\x{065F}\x{0670}]/u', '', $text);

        // Remove tatweel (kashida)
        $text = str_replace('ـ', '', $text);

        // Normalize hamza variants
        $text = (string) preg_replace('/[أإآ]/u', 'ا', $text);
        $text = str_replace(['ة', 'ى'], ['ه', 'ي'], $text);

        // Collapse multiple spaces
        $text = (string) preg_replace('/\s+/u', ' ', $text);

        return trim($text);
    }

    /**
     * Phonetically transliterate Arabic text to Latin characters.
     */
    public static function transliterateArabic(string $text): string
    {
        // Strip diacritics first
        $clean = (string) preg_replace('/[\x{064B}-\x{065F}\x{0670}]/u', '', $text);
        $clean = str_replace('ـ', '', $clean);

        return strtr($clean, self::ARABIC_TRANSLITERATION_MAP);
    }

    /**
     * @return array<string, string>
     */
    private static function getNormalizedFieldMap(): array
    {
        /** @var array<string, string>|null $map */
        static $map = null;
        if ($map === null) {
            $map = [];
            foreach (self::ARABIC_FIELD_SEMANTIC_MAP as $key => $val) {
                $map[self::normalizeArabic($key)] = $val;
            }
        }

        return $map;
    }

    /**
     * @return array<string, string>
     */
    private static function getNormalizedOptionMap(): array
    {
        /** @var array<string, string>|null $map */
        static $map = null;
        if ($map === null) {
            $map = [];
            foreach (self::ARABIC_OPTION_SEMANTIC_MAP as $key => $val) {
                $map[self::normalizeArabic($key)] = $val;
            }
        }

        return $map;
    }

    /**
     * Deduplicates an identifier against existing names in the form schema.
     *
     * @param  list<string>  $existing
     */
    public static function deduplicate(string $base, array $existing): string
    {
        if (! in_array($base, $existing, true)) {
            return $base;
        }

        $counter = 2;
        while (in_array("{$base}_{$counter}", $existing, true)) {
            $counter++;
        }

        return "{$base}_{$counter}";
    }
}
