<?php

declare(strict_types=1);

namespace Tests\Feature;

use Tests\TestCase;

/**
 * A missing translation key does not error — Laravel echoes the key itself. So
 * `__('public.toggle_submenu')` with no such key renders the literal string
 * "public.toggle_submenu" into the page.
 *
 * Five keys were missing in exactly this way. Because they were used in
 * aria-labels and alt text, nothing looked wrong on screen while a screen reader
 * announced "public toggle submenu" on every navigation item on every page, in
 * both locales. Nothing in the suite noticed.
 */
final class PublicTranslationKeyCoverageTest extends TestCase
{
    public function test_every_public_key_used_in_a_view_exists_in_both_locales(): void
    {
        $ar = require lang_path('ar/public.php');
        $en = require lang_path('en/public.php');

        $missing = [];

        foreach ($this->viewFiles() as $file) {
            $contents = (string) file_get_contents($file);

            // __('public.x'), __("public.x"), @lang('public.x'), trans('public.x').
            // The closing quote must be followed by ) or , so that a concatenated
            // key — __('public.search_types.'.$type) — is not matched on its
            // static prefix. Those are dynamic by design and cannot be verified
            // here; the group they live in still is, via the parity test.
            preg_match_all(
                '/(?:__|@lang|trans)\(\s*([\'"])public\.([a-zA-Z0-9_.]+)\1\s*[,)]/',
                $contents,
                $matches,
            );

            foreach (array_unique($matches[2] ?? []) as $key) {
                $relative = str_replace(base_path().'/', '', $file);

                if (! $this->keyExists($en, $key)) {
                    $missing[] = "en/public.{$key}  ({$relative})";
                }

                if (! $this->keyExists($ar, $key)) {
                    $missing[] = "ar/public.{$key}  ({$relative})";
                }
            }
        }

        $this->assertSame(
            [],
            array_values(array_unique($missing)),
            "Views reference translation keys that do not exist. Laravel renders the key itself, which lands in aria-labels and alt text where nobody sees it:\n".implode("\n", array_unique($missing)),
        );
    }

    public function test_the_two_locales_define_the_same_keys(): void
    {
        $ar = $this->flatten(require lang_path('ar/public.php'));
        $en = $this->flatten(require lang_path('en/public.php'));

        $this->assertSame([], array_values(array_diff($en, $ar)), 'Keys present in English but missing from Arabic.');
        $this->assertSame([], array_values(array_diff($ar, $en)), 'Keys present in Arabic but missing from English.');
    }

    /** @param array<string, mixed> $translations */
    private function keyExists(array $translations, string $key): bool
    {
        $cursor = $translations;

        foreach (explode('.', $key) as $segment) {
            if (! is_array($cursor) || ! array_key_exists($segment, $cursor)) {
                return false;
            }

            $cursor = $cursor[$segment];
        }

        return true;
    }

    /**
     * @param  array<string, mixed>  $translations
     * @return array<int, string>
     */
    private function flatten(array $translations, string $prefix = ''): array
    {
        $keys = [];

        foreach ($translations as $key => $value) {
            $path = $prefix === '' ? (string) $key : $prefix.'.'.$key;
            $keys = is_array($value)
                ? array_merge($keys, $this->flatten($value, $path))
                : array_merge($keys, [$path]);
        }

        return $keys;
    }

    /** @return array<int, string> */
    private function viewFiles(): array
    {
        $files = [];
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator(resource_path('views'), \FilesystemIterator::SKIP_DOTS),
        );

        foreach ($iterator as $file) {
            if ($file->isFile() && str_ends_with($file->getFilename(), '.blade.php')) {
                $files[] = $file->getPathname();
            }
        }

        return $files;
    }
}
