<?php

declare(strict_types=1);

namespace Tests\Feature;

use Tests\TestCase;

/**
 * Arabic is the default locale and English is the secondary one, so a key that
 * exists in only one file renders as a raw key path (e.g. "public.foo") to real
 * visitors in the other language. This guard fails the moment the two drift.
 */
final class LanguageFileParityTest extends TestCase
{
    public function test_arabic_and_english_files_cover_the_same_keys(): void
    {
        $violations = [];

        foreach ($this->localeFiles('ar') as $name => $arabicPath) {
            $englishPath = lang_path('en/'.$name);

            if (! is_file($englishPath)) {
                $violations[] = "lang/en/{$name} is missing";

                continue;
            }

            $arabicKeys = $this->flatten(require $arabicPath);
            $englishKeys = $this->flatten(require $englishPath);

            foreach (array_diff($arabicKeys, $englishKeys) as $key) {
                $violations[] = "{$name}: '{$key}' exists in ar but not en";
            }

            foreach (array_diff($englishKeys, $arabicKeys) as $key) {
                $violations[] = "{$name}: '{$key}' exists in en but not ar";
            }
        }

        $this->assertEmpty(
            $violations,
            "Arabic and English language files must define the same keys:\n".implode("\n", $violations)
        );
    }

    public function test_every_english_file_has_an_arabic_counterpart(): void
    {
        $missing = [];

        foreach (array_keys($this->localeFiles('en')) as $name) {
            if (! is_file(lang_path('ar/'.$name))) {
                $missing[] = "lang/ar/{$name} is missing";
            }
        }

        $this->assertEmpty($missing, implode("\n", $missing));
    }

    public function test_no_translation_value_is_empty(): void
    {
        $empty = [];

        foreach (['ar', 'en'] as $locale) {
            foreach ($this->localeFiles($locale) as $name => $path) {
                foreach ($this->flatten(require $path, '', true) as $key => $value) {
                    if (is_string($value) && trim($value) === '') {
                        $empty[] = "lang/{$locale}/{$name}: '{$key}' is empty";
                    }
                }
            }
        }

        $this->assertEmpty($empty, implode("\n", $empty));
    }

    /**
     * @return array<string, string> Map of file name to absolute path.
     */
    private function localeFiles(string $locale): array
    {
        $files = [];

        foreach (glob(lang_path($locale.'/*.php')) ?: [] as $path) {
            $files[basename($path)] = $path;
        }

        return $files;
    }

    /**
     * Flatten a nested translation array into dotted key paths.
     *
     * @param  array<array-key, mixed>  $items
     * @return array<array-key, mixed> Keys only, or key => value when $withValues.
     */
    private function flatten(array $items, string $prefix = '', bool $withValues = false): array
    {
        $flat = [];

        foreach ($items as $key => $value) {
            $path = $prefix === '' ? (string) $key : $prefix.'.'.$key;

            if (is_array($value)) {
                $flat = array_merge($flat, $this->flatten($value, $path, $withValues));

                continue;
            }

            if ($withValues) {
                $flat[$path] = $value;
            } else {
                $flat[] = $path;
            }
        }

        return $flat;
    }
}
