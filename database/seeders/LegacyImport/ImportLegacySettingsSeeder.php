<?php

declare(strict_types=1);

namespace Database\Seeders\LegacyImport;

use App\Models\Settings\Setting;
use stdClass;

class ImportLegacySettingsSeeder extends BaseLegacyImportSeeder
{
    public function run(): void
    {
        foreach (['jx_config', 'jx_config1'] as $table) {
            $this->importTable($table);
        }
    }

    private function importTable(string $table): void
    {
        $module = 'settings';
        $batch = $this->batchName($module.'-'.$table);
        $rowsByKey = [];

        foreach ($this->legacyRows($table) as $row) {
            $legacyKey = strtolower((string) ($this->cleanedString($row, ['name', 'key', 'config_key', 'option']) ?? ''));

            if ($legacyKey !== '' && ! array_key_exists($legacyKey, $rowsByKey)) {
                $rowsByKey[$legacyKey] = $row;
            }
        }

        if ($rowsByKey === []) {
            return;
        }

        $this->importStudentPortal($table, $batch, $rowsByKey);
        $this->importApplyCta($table, $batch, $rowsByKey);
        $this->importDefaultSeo($table, $batch, $rowsByKey);
        $this->importSocialContact($table, $batch, $rowsByKey);
        $this->importContactLinks($table, $batch, $rowsByKey);
        $this->importFooter($table, $batch, $rowsByKey);

        foreach ($rowsByKey as $legacyKey => $row) {
            $sourceId = $this->normalizedInteger($this->rowValue($row, 'id'));

            if ($this->alreadyImported($table, $sourceId, 'settings')) {
                continue;
            }

            $this->importFallbackSetting($table, $batch, $legacyKey, $row);
        }
    }

    private function importFallbackSetting(string $table, string $batch, string $legacyKey, object $row): void
    {
        $sourceId = $this->normalizedInteger($this->rowValue($row, 'id'));

        if ($this->alreadyImported($table, $sourceId, 'legacy_record_snapshots')) {
            return;
        }

        $rawValue = $this->cleanedString($row, 'value');

        if ($rawValue === null) {
            $this->logSkip('settings', $batch, $table, $sourceId, 'legacy_record_snapshots', 'Skipped legacy setting row without a usable value.', ['legacy_key' => $legacyKey]);

            return;
        }

        [$classification, $locale] = $this->fallbackShape($legacyKey);

        $snapshot = $this->snapshotLegacyRow(
            'settings',
            $batch,
            $table,
            $sourceId,
            $legacyKey,
            $classification,
            $locale,
            [
                'legacy_key' => $legacyKey,
                'label' => $this->cleanedString($row, 'label'),
                'raw_value' => $rawValue,
                'field_type' => $this->normalizedInteger($this->rowValue($row, 'field_type')),
                'record_order' => $this->normalizedInteger($this->rowValue($row, 'record_order')),
                'site' => $this->normalizedInteger($this->rowValue($row, 'site')),
            ],
            $rawValue,
        );

        $this->migrationLogger()->log(
            'settings',
            $batch,
            $table,
            $sourceId,
            'legacy_record_snapshots',
            (int) $snapshot->getKey(),
            'success',
            'Preserved legacy setting outside product tables for later decomposition.',
            ['legacy_key' => $legacyKey, 'classification' => $classification],
        );
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function fallbackShape(string $legacyKey): array
    {
        if (str_starts_with($legacyKey, 'ar_')) {
            return ['seo', 'ar'];
        }

        if (str_starts_with($legacyKey, 'en_')) {
            return ['seo', 'en'];
        }

        if (str_starts_with($legacyKey, 'fr_')) {
            return ['seo', 'fr'];
        }

        if (str_starts_with($legacyKey, 'sp_')) {
            return ['seo', 'es'];
        }

        if (str_starts_with($legacyKey, 'ge_')) {
            return ['seo', 'de'];
        }

        return match (true) {
            str_ends_with($legacyKey, '_site_name') => ['seo', $this->legacyLocaleFromNameKey($legacyKey)],
            str_ends_with($legacyKey, '_site_description') => ['seo', $this->legacyLocaleFromNameKey($legacyKey)],
            str_ends_with($legacyKey, '_keywords') => ['seo', $this->legacyLocaleFromNameKey($legacyKey)],
            str_ends_with($legacyKey, '_photo'), str_ends_with($legacyKey, '_banner'), $legacyKey === 'banner_ad' => ['media', ''],
            str_ends_with($legacyKey, '_link'), $legacyKey === 'video_link' => ['link', ''],
            in_array($legacyKey, ['num_pages', 'page_size', 'mailer_pause', 'allow_comments', 'home_news_number'], true) => ['runtime', ''],
            default => ['misc', ''],
        };
    }

    private function legacyLocaleFromNameKey(string $legacyKey): string
    {
        return match (true) {
            str_starts_with($legacyKey, 'arabic_') => 'ar',
            str_starts_with($legacyKey, 'english_') => 'en',
            str_starts_with($legacyKey, 'frensh_') => 'fr',
            str_starts_with($legacyKey, 'spanish_') => 'es',
            str_starts_with($legacyKey, 'german_') => 'de',
            default => '',
        };
    }

    /**
     * @param  array<string, stdClass>  $rowsByKey
     */
    private function importStudentPortal(string $table, string $batch, array $rowsByKey): void
    {
        $row = $rowsByKey['student_gate_link'] ?? null;

        if (! $row instanceof stdClass) {
            return;
        }

        $value = $this->cleanedString($row, 'value');

        if ($value === null) {
            return;
        }

        $setting = Setting::query()->updateOrCreate(
            ['group_key' => 'navigation', 'key' => 'student_portal_url', 'locale' => ''],
            ['type' => 'text', 'value_json' => null, 'value_text' => $value, 'is_public' => true],
        );

        $this->logMappedSetting($table, $batch, $row, $setting, 'Imported student portal URL.', 'student_gate_link');
    }

    /**
     * @param  array<string, stdClass>  $rowsByKey
     */
    private function importApplyCta(string $table, string $batch, array $rowsByKey): void
    {
        $row = $rowsByKey['registration_link'] ?? null;

        if (! $row instanceof stdClass) {
            return;
        }

        $url = $this->cleanedString($row, 'value');

        if ($url === null) {
            return;
        }

        foreach (['ar' => 'قدّم الآن', 'en' => 'Apply now'] as $locale => $label) {
            $setting = Setting::query()->updateOrCreate(
                ['group_key' => 'navigation', 'key' => 'apply_cta', 'locale' => $locale],
                [
                    'type' => 'json',
                    'value_json' => ['label' => $label, 'url' => $url, 'target' => null, 'is_enabled' => true],
                    'value_text' => null,
                    'is_public' => true,
                ],
            );

            $this->logMappedSetting($table, $batch, $row, $setting, 'Imported registration link as apply CTA.', 'registration_link');
        }
    }

    /**
     * @param  array<string, stdClass>  $rowsByKey
     */
    private function importDefaultSeo(string $table, string $batch, array $rowsByKey): void
    {
        $locales = [
            'ar' => ['title' => 'arabic_site_name', 'description' => 'ar_site_description', 'keywords' => 'ar_keywords'],
            'en' => ['title' => 'english_site_name', 'description' => 'en_site_description', 'keywords' => 'en_keywords'],
        ];

        foreach ($locales as $locale => $keys) {
            $title = $this->legacyValue($rowsByKey, $keys['title']);
            $description = $this->legacyValue($rowsByKey, $keys['description']);
            $keywords = $this->legacyValue($rowsByKey, $keys['keywords']);

            if ($title === null && $description === null && $keywords === null) {
                continue;
            }

            $setting = Setting::query()->updateOrCreate(
                ['group_key' => 'seo', 'key' => 'default_seo', 'locale' => $locale],
                [
                    'type' => 'json',
                    'value_json' => [
                        'title' => $title,
                        'meta_description' => $description,
                        'og_title' => $title,
                        'og_description' => $description,
                        'keywords' => $keywords,
                    ],
                    'value_text' => null,
                    'is_public' => true,
                ],
            );

            foreach ($keys as $key) {
                if (isset($rowsByKey[$key])) {
                    $this->logMappedSetting($table, $batch, $rowsByKey[$key], $setting, 'Imported SEO setting payload.', $key);
                }
            }
        }
    }

    /**
     * @param  array<string, stdClass>  $rowsByKey
     */
    private function importSocialContact(string $table, string $batch, array $rowsByKey): void
    {
        $socialMap = [
            'facebook_link' => 'Facebook',
            'instagram_link' => 'Instagram',
            'telegram_link' => 'Telegram',
            'twitter_link' => 'X',
            'whatsapp_link' => 'WhatsApp',
            'youtub_link' => 'YouTube',
            'google_link' => 'Google',
            'pinterest_link' => 'Pinterest',
        ];

        $socialLinks = [];

        foreach ($socialMap as $legacyKey => $label) {
            $value = $this->legacyValue($rowsByKey, $legacyKey);

            if ($value !== null) {
                $socialLinks[] = ['label' => $label, 'url' => $value];
            }
        }

        if ($socialLinks === []) {
            return;
        }

        foreach (['ar', 'en'] as $locale) {
            $setting = Setting::query()->updateOrCreate(
                ['group_key' => 'footer', 'key' => 'social_contact', 'locale' => $locale],
                ['type' => 'json', 'value_json' => ['socialLinks' => $socialLinks], 'value_text' => null, 'is_public' => true],
            );

            foreach (array_keys($socialMap) as $legacyKey) {
                if (isset($rowsByKey[$legacyKey])) {
                    $this->logMappedSetting($table, $batch, $rowsByKey[$legacyKey], $setting, 'Imported social links payload.', $legacyKey);
                }
            }
        }
    }

    /**
     * @param  array<string, stdClass>  $rowsByKey
     */
    private function importContactLinks(string $table, string $batch, array $rowsByKey): void
    {
        $contactMap = [
            'email' => ['label_ar' => 'البريد الإلكتروني', 'label_en' => 'Email', 'type' => 'email'],
            'complaint_email' => ['label_ar' => 'بريد الشكاوى', 'label_en' => 'Complaints Email', 'type' => 'email'],
            'registeration_email' => ['label_ar' => 'بريد التسجيل', 'label_en' => 'Registration Email', 'type' => 'email'],
            'seek_job_email' => ['label_ar' => 'بريد الوظائف', 'label_en' => 'Careers Email', 'type' => 'email'],
            'web_address' => ['label_ar' => 'الموقع الإلكتروني', 'label_en' => 'Website', 'type' => 'url'],
        ];

        $links = ['ar' => [], 'en' => []];

        foreach ($contactMap as $legacyKey => $definition) {
            $value = $this->legacyValue($rowsByKey, $legacyKey);

            if ($value === null) {
                continue;
            }

            $links['ar'][] = ['label' => $definition['label_ar'], 'value' => $value, 'type' => $definition['type']];
            $links['en'][] = ['label' => $definition['label_en'], 'value' => $value, 'type' => $definition['type']];
        }

        foreach ($links as $locale => $contactLinks) {
            if ($contactLinks === []) {
                continue;
            }

            $setting = Setting::query()->updateOrCreate(
                ['group_key' => 'footer', 'key' => 'contact_links', 'locale' => $locale],
                ['type' => 'json', 'value_json' => ['contactLinks' => $contactLinks], 'value_text' => null, 'is_public' => true],
            );

            foreach (array_keys($contactMap) as $legacyKey) {
                if (isset($rowsByKey[$legacyKey])) {
                    $this->logMappedSetting($table, $batch, $rowsByKey[$legacyKey], $setting, 'Imported contact links payload.', $legacyKey);
                }
            }
        }
    }

    /**
     * @param  array<string, stdClass>  $rowsByKey
     */
    private function importFooter(string $table, string $batch, array $rowsByKey): void
    {
        $email = $this->legacyValue($rowsByKey, 'email');
        $website = $this->legacyValue($rowsByKey, 'web_address') ?? $this->legacyValue($rowsByKey, 'domain');

        foreach (['ar' => 'arabic_site_name', 'en' => 'english_site_name'] as $locale => $titleKey) {
            $siteName = $this->legacyValue($rowsByKey, $titleKey);

            if ($siteName === null && $email === null && $website === null) {
                continue;
            }

            $setting = Setting::query()->updateOrCreate(
                ['group_key' => 'footer', 'key' => 'footer', 'locale' => $locale],
                [
                    'type' => 'json',
                    'value_json' => [
                        'copyrightText' => $siteName,
                        'address' => $website,
                        'phone' => null,
                        'email' => $email,
                    ],
                    'value_text' => null,
                    'is_public' => true,
                ],
            );

            foreach (array_filter([$titleKey, 'email', 'web_address', 'domain']) as $legacyKey) {
                if (isset($rowsByKey[$legacyKey])) {
                    $this->logMappedSetting($table, $batch, $rowsByKey[$legacyKey], $setting, 'Imported footer payload.', $legacyKey);
                }
            }
        }
    }

    /**
     * @param  array<string, stdClass>  $rowsByKey
     */
    private function legacyValue(array $rowsByKey, string $legacyKey): ?string
    {
        return isset($rowsByKey[$legacyKey]) ? $this->cleanedString($rowsByKey[$legacyKey], 'value') : null;
    }

    private function logMappedSetting(string $table, string $batch, object $row, Setting $setting, string $message, string $legacyKey): void
    {
        $sourceId = $this->normalizedInteger($this->rowValue($row, 'id'));

        if ($this->alreadyImported($table, $sourceId, 'settings')) {
            return;
        }

        $this->migrationLogger()->log(
            'settings',
            $batch,
            $table,
            $sourceId,
            'settings',
            (int) $setting->getKey(),
            'success',
            $message,
            ['legacy_key' => $legacyKey],
        );
    }
}
