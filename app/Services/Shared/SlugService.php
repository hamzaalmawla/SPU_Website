<?php

declare(strict_types=1);

namespace App\Services\Shared;

use App\Contracts\Shared\SlugServiceInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Real slug generation service with Arabic transliteration and uniqueness enforcement.
 */
final class SlugService implements SlugServiceInterface
{
    private const MAX_COLLISION_ATTEMPTS = 10;

    /**
     * @var array<string, string>
     */
    private const ARABIC_TRANSLITERATION_MAP = [
        'ا' => 'a', 'أ' => 'a', 'إ' => 'i', 'آ' => 'aa',
        'ب' => 'b', 'ت' => 't', 'ث' => 'th', 'ج' => 'j',
        'ح' => 'h', 'خ' => 'kh', 'د' => 'd', 'ذ' => 'dh',
        'ر' => 'r', 'ز' => 'z', 'س' => 's', 'ش' => 'sh',
        'ص' => 's', 'ض' => 'd', 'ط' => 't', 'ظ' => 'z',
        'ع' => 'a', 'غ' => 'gh', 'ف' => 'f', 'ق' => 'q',
        'ك' => 'k', 'ل' => 'l', 'م' => 'm', 'ن' => 'n',
        'ه' => 'h', 'و' => 'w', 'ي' => 'y', 'ى' => 'a',
        'ة' => 'h', 'ئ' => 'e', 'ؤ' => 'o', 'ء' => '',
        // Common diacritics — strip
        "\u{064B}" => '', "\u{064C}" => '', "\u{064D}" => '',
        "\u{064E}" => '', "\u{064F}" => '', "\u{0650}" => '',
        "\u{0651}" => '', "\u{0652}" => '',
        // Lam-Alef ligatures
        'لا' => 'la', 'لأ' => 'la', 'لإ' => 'li', 'لآ' => 'laa',
    ];

    public function generate(string $source, string $modelClass, string $locale = 'ar', ?int $ignoreId = null): string
    {
        $slug = $this->toSlug($source, $locale);

        if ($slug === '') {
            $slug = 'untitled';
        }

        $table = $this->resolveTable($modelClass);
        $baseSlug = $slug;

        if (! $this->slugExists($table, $slug, $ignoreId)) {
            return $slug;
        }

        for ($i = 1; $i <= self::MAX_COLLISION_ATTEMPTS; $i++) {
            $candidate = $baseSlug.'-'.$i;
            if (! $this->slugExists($table, $candidate, $ignoreId)) {
                return $candidate;
            }
        }

        throw new RuntimeException(
            "Unable to generate a unique slug for '{$source}' after ".self::MAX_COLLISION_ATTEMPTS.' attempts.'
        );
    }

    private function toSlug(string $source, string $locale): string
    {
        $text = trim($source);

        if ($locale === 'ar' || $this->containsArabic($text)) {
            $text = $this->transliterateArabic($text);
        }

        // Convert to ASCII-safe slug
        $slug = Str::slug($text);

        // Ensure max length
        if (mb_strlen($slug) > 200) {
            $slug = mb_substr($slug, 0, 200);
            // Trim trailing hyphens from truncation
            $slug = rtrim($slug, '-');
        }

        return $slug;
    }

    private function containsArabic(string $text): bool
    {
        return (bool) preg_match('/[\x{0600}-\x{06FF}]/u', $text);
    }

    private function transliterateArabic(string $text): string
    {
        // Process multi-character mappings first (Lam-Alef ligatures)
        $multiChar = [
            'لا' => 'la', 'لأ' => 'la', 'لإ' => 'li', 'لآ' => 'laa',
        ];
        $text = str_replace(array_keys($multiChar), array_values($multiChar), $text);

        // Then single-character mappings
        $singleChar = array_diff_key(self::ARABIC_TRANSLITERATION_MAP, $multiChar);
        $text = str_replace(array_keys($singleChar), array_values($singleChar), $text);

        return $text;
    }

    private function resolveTable(string $modelClass): string
    {
        if (! class_exists($modelClass)) {
            throw new RuntimeException("Model class '{$modelClass}' does not exist.");
        }

        /** @var Model $instance */
        $instance = new $modelClass;

        return $instance->getTable();
    }

    private function slugExists(string $table, string $slug, ?int $ignoreId): bool
    {
        $query = DB::table($table)->where('slug', $slug);

        if ($ignoreId !== null) {
            $query->where('id', '!=', $ignoreId);
        }

        return $query->exists();
    }
}
