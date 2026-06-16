<?php

declare(strict_types=1);

namespace Database\Seeders\LegacyImport;

use App\Models\Legacy\LegacyRecordSnapshot;
use App\Models\Media\MediaAsset;
use App\Support\LegacyImport\DateNormalizer;
use App\Support\LegacyImport\EmailValidator;
use App\Support\LegacyImport\HtmlSanitizer;
use App\Support\LegacyImport\LocaleFilter;
use App\Support\LegacyImport\MigrationLogger;
use App\Support\LegacyImport\OldDatabaseConnection;
use App\Support\LegacyImport\TargetIdResolver;
use App\Support\LegacyImport\TextCleaner;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

abstract class BaseLegacyImportSeeder extends Seeder
{
    protected function legacyConnection(): OldDatabaseConnection
    {
        return app(OldDatabaseConnection::class);
    }

    protected function textCleaner(): TextCleaner
    {
        return app(TextCleaner::class);
    }

    protected function htmlSanitizer(): HtmlSanitizer
    {
        return app(HtmlSanitizer::class);
    }

    protected function dateNormalizer(): DateNormalizer
    {
        return app(DateNormalizer::class);
    }

    protected function emailValidator(): EmailValidator
    {
        return app(EmailValidator::class);
    }

    protected function localeFilter(): LocaleFilter
    {
        return app(LocaleFilter::class);
    }

    protected function migrationLogger(): MigrationLogger
    {
        return app(MigrationLogger::class);
    }

    protected function targetIdResolver(): TargetIdResolver
    {
        return app(TargetIdResolver::class);
    }

    protected function batchName(string $module): string
    {
        return $module.'-'.now()->format('YmdHis');
    }

    protected function alreadyImported(string $sourceTable, int|string|null $sourceId, string $targetTable): bool
    {
        if (! is_numeric($sourceId)) {
            return false;
        }

        return $this->targetIdResolver()->resolve($sourceTable, (int) $sourceId, $targetTable) !== null;
    }

    protected function legacyTableExists(string $table): bool
    {
        $this->legacyConnection()->connection();

        return Schema::connection((string) config('old_database.connection_name', 'legacy_mysql'))->hasTable($table);
    }

    protected function legacyTableHasColumn(string $table, string $column): bool
    {
        if (! $this->legacyTableExists($table)) {
            return false;
        }

        $this->legacyConnection()->connection();

        return Schema::connection((string) config('old_database.connection_name', 'legacy_mysql'))->hasColumn($table, $column);
    }

    /**
     * @param  array<int, string>  $columns
     * @return array<int, string>
     */
    protected function legacyAvailableColumns(string $table, array $columns): array
    {
        return array_values(array_filter(
            $columns,
            fn (string $column): bool => $this->legacyTableHasColumn($table, $column),
        ));
    }

    /**
     * @return Collection<int, object>
     */
    protected function legacyRows(string $table): Collection
    {
        if (! $this->legacyTableExists($table)) {
            $this->command?->warn('Legacy table not found: '.$table);

            return collect();
        }

        return collect($this->legacyConnection()->table($table)->get());
    }

    protected function rowValue(object|array $row, array|string $keys, mixed $default = null): mixed
    {
        $keys = is_array($keys) ? $keys : [$keys];

        foreach ($keys as $key) {
            if (is_array($row) && array_key_exists($key, $row)) {
                return $row[$key];
            }

            if (is_object($row) && isset($row->{$key})) {
                return $row->{$key};
            }
        }

        return $default;
    }

    protected function cleanedString(object|array $row, array|string $keys): ?string
    {
        return $this->textCleaner()->clean((string) $this->rowValue($row, $keys, ''));
    }

    protected function normalizedLocale(object|array $row, array|string $keys = ['locale', 'lang', 'language']): ?string
    {
        return $this->localeFilter()->normalize($this->cleanedString($row, $keys));
    }

    protected function normalizedLegacyVisibility(object|array $row, bool $default = true): bool
    {
        return $this->normalizedBoolean(
            $this->rowValue($row, ['is_visible', 'is_active', 'active', 'is_enabled']),
            $default,
        );
    }

    protected function normalizedBoolean(mixed $value, bool $default = false): bool
    {
        if ($value === null) {
            return $default;
        }

        if (is_bool($value)) {
            return $value;
        }

        $normalized = strtolower(trim((string) $value));

        return match ($normalized) {
            '1', 'true', 'yes', 'y', 'on' => true,
            '0', 'false', 'no', 'n', 'off' => false,
            default => $default,
        };
    }

    protected function normalizedInteger(mixed $value): ?int
    {
        return is_numeric($value) ? (int) $value : null;
    }

    /**
     * @return array<int, array{slug: string, sort_order: int, name_ar: string, name_en: string, service_types: array<int, int>}>
     */
    protected function legacyFacultyCatalog(): array
    {
        return [
            2 => [
                'slug' => 'medicine',
                'sort_order' => 1,
                'name_ar' => 'كلية الطب البشري',
                'name_en' => 'Faculty of Medicine',
                'service_types' => [3, 4],
            ],
            3 => [
                'slug' => 'dentistry',
                'sort_order' => 2,
                'name_ar' => 'كلية طب الأسنان',
                'name_en' => 'Faculty of Dentistry',
                'service_types' => [5, 6],
            ],
            4 => [
                'slug' => 'pharmacy',
                'sort_order' => 3,
                'name_ar' => 'كلية الصيدلة',
                'name_en' => 'Faculty of Pharmacy',
                'service_types' => [7, 8],
            ],
            5 => [
                'slug' => 'computer-and-informatics-engineering',
                'sort_order' => 4,
                'name_ar' => 'كلية هندسة الحاسوب والمعلوماتية',
                'name_en' => 'Faculty of Computer Engineering and Informatics',
                'service_types' => [9, 10],
            ],
            6 => [
                'slug' => 'petroleum-engineering',
                'sort_order' => 5,
                'name_ar' => 'كلية هندسة البترول',
                'name_en' => 'Faculty of Petroleum Engineering',
                'service_types' => [11, 12],
            ],
            7 => [
                'slug' => 'administrative-sciences',
                'sort_order' => 6,
                'name_ar' => 'كلية العلوم الإدارية',
                'name_en' => 'Faculty of Administrative Sciences',
                'service_types' => [13, 14],
            ],
        ];
    }

    /**
     * @return array<int, array{source_id: int, slug: string, type: string, sort_order: int, name_ar: string, name_en: string, service_types: array<int, int>}>
     */
    protected function legacyCouncilCatalog(): array
    {
        return [
            [
                'source_id' => 1,
                'slug' => 'university-council',
                'type' => 'university',
                'sort_order' => 1,
                'name_ar' => 'مجلس الجامعة',
                'name_en' => 'University Council',
                'service_types' => [1, 2],
            ],
            [
                'source_id' => 2,
                'slug' => 'medicine-council',
                'type' => 'faculty',
                'sort_order' => 2,
                'name_ar' => 'مجلس كلية الطب البشري',
                'name_en' => 'Faculty of Medicine Council',
                'service_types' => [3, 4],
            ],
            [
                'source_id' => 3,
                'slug' => 'dentistry-council',
                'type' => 'faculty',
                'sort_order' => 3,
                'name_ar' => 'مجلس كلية طب الأسنان',
                'name_en' => 'Faculty of Dentistry Council',
                'service_types' => [5, 6],
            ],
            [
                'source_id' => 4,
                'slug' => 'pharmacy-council',
                'type' => 'faculty',
                'sort_order' => 4,
                'name_ar' => 'مجلس كلية الصيدلة',
                'name_en' => 'Faculty of Pharmacy Council',
                'service_types' => [7, 8],
            ],
            [
                'source_id' => 5,
                'slug' => 'computer-and-informatics-engineering-council',
                'type' => 'faculty',
                'sort_order' => 5,
                'name_ar' => 'مجلس كلية هندسة الحاسوب والمعلوماتية',
                'name_en' => 'Faculty of Computer Engineering and Informatics Council',
                'service_types' => [9, 10],
            ],
            [
                'source_id' => 6,
                'slug' => 'petroleum-engineering-council',
                'type' => 'faculty',
                'sort_order' => 6,
                'name_ar' => 'مجلس كلية هندسة البترول',
                'name_en' => 'Faculty of Petroleum Engineering Council',
                'service_types' => [11, 12],
            ],
            [
                'source_id' => 7,
                'slug' => 'administrative-sciences-council',
                'type' => 'faculty',
                'sort_order' => 7,
                'name_ar' => 'مجلس كلية العلوم الإدارية',
                'name_en' => 'Faculty of Administrative Sciences Council',
                'service_types' => [13, 14],
            ],
        ];
    }

    /**
     * @return array{slug: string, sort_order: int, name_ar: string, name_en: string, service_types: array<int, int>}|null
     */
    protected function legacyFacultyDefinition(?int $legacyFacultyId): ?array
    {
        return $legacyFacultyId !== null ? ($this->legacyFacultyCatalog()[$legacyFacultyId] ?? null) : null;
    }

    /**
     * @return array{slug: string, sort_order: int, name_ar: string, name_en: string, service_types: array<int, int>}|null
     */
    protected function legacyFacultyDefinitionByServiceType(?int $serviceType): ?array
    {
        if ($serviceType === null) {
            return null;
        }

        foreach ($this->legacyFacultyCatalog() as $definition) {
            if (in_array($serviceType, $definition['service_types'], true)) {
                return $definition;
            }
        }

        return null;
    }

    /**
     * @return array{source_id: int, slug: string, type: string, sort_order: int, name_ar: string, name_en: string, service_types: array<int, int>}|null
     */
    protected function legacyCouncilDefinitionByServiceType(?int $serviceType): ?array
    {
        if ($serviceType === null) {
            return null;
        }

        foreach ($this->legacyCouncilCatalog() as $definition) {
            if (in_array($serviceType, $definition['service_types'], true)) {
                return $definition;
            }
        }

        return null;
    }

    protected function resolveLegacyFacultyId(?int $legacyFacultyId): ?int
    {
        if ($legacyFacultyId === null) {
            return null;
        }

        $mapped = $this->targetIdResolver()->resolve('jx_member_categories', $legacyFacultyId, 'faculties');

        if ($mapped !== null) {
            return $mapped;
        }

        $definition = $this->legacyFacultyDefinition($legacyFacultyId);

        if ($definition === null) {
            return null;
        }

        return DB::table('faculties')
            ->where('slug', $definition['slug'])
            ->value('id');
    }

    protected function resolveLegacyFacultyIdByServiceType(?int $serviceType): ?int
    {
        $definition = $this->legacyFacultyDefinitionByServiceType($serviceType);

        if ($definition === null) {
            return null;
        }

        return DB::table('faculties')
            ->where('slug', $definition['slug'])
            ->value('id');
    }

    /**
     * @return array{canonical_slug: string, legacy_alumni_category_id: int, legacy_service_type: int, ar_name: string, en_name: string, evidence_url: string}|null
     */
    protected function legacyStudentFacultyDefinition(?int $legacyFacultyCode): ?array
    {
        if ($legacyFacultyCode === null) {
            return null;
        }

        $definition = config("legacy_student_taxonomy.faculty_code_map.{$legacyFacultyCode}");

        return is_array($definition) ? $definition : null;
    }

    protected function resolveLegacyStudentFacultyId(?int $legacyFacultyCode): ?int
    {
        $definition = $this->legacyStudentFacultyDefinition($legacyFacultyCode);

        if ($definition === null) {
            return $this->resolveLegacyFacultyId($legacyFacultyCode);
        }

        return DB::table('faculties')
            ->where('slug', $definition['canonical_slug'])
            ->value('id');
    }

    protected function resolveLegacyCouncilIdByServiceType(?int $serviceType): ?int
    {
        $definition = $this->legacyCouncilDefinitionByServiceType($serviceType);

        if ($definition === null) {
            return null;
        }

        return DB::table('councils')
            ->where('slug', $definition['slug'])
            ->value('id');
    }

    protected function legacyLocaleFromLanguageCode(mixed $value): ?string
    {
        return match ($this->normalizedInteger($value)) {
            1 => 'ar',
            2 => 'en',
            default => null,
        };
    }

    /**
     * @return array<string, mixed>|null
     */
    protected function decodeJson(?string $value): ?array
    {
        if ($value === null) {
            return null;
        }

        $decoded = json_decode($value, true);

        return is_array($decoded) ? $decoded : null;
    }

    protected function guessMimeType(?string $path): string
    {
        $extension = strtolower((string) pathinfo((string) $path, PATHINFO_EXTENSION));

        return match ($extension) {
            'jpg', 'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'gif' => 'image/gif',
            'webp' => 'image/webp',
            'svg' => 'image/svg+xml',
            'pdf' => 'application/pdf',
            'doc' => 'application/msword',
            'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            default => 'application/octet-stream',
        };
    }

    protected function legacyMediaAssetId(
        ?string $path,
        ?string $directoryPrefix = null,
        ?string $titleAr = null,
        ?string $titleEn = null,
    ): ?int {
        $cleanPath = $this->textCleaner()->clean((string) $path);

        if ($cleanPath === null || $cleanPath === '') {
            return null;
        }

        $normalizedPath = str_replace('\\', '/', $cleanPath);

        if ($directoryPrefix !== null && ! str_contains($normalizedPath, '/')) {
            $normalizedPath = trim($directoryPrefix, '/').'/'.$normalizedPath;
        }

        $media = MediaAsset::query()->updateOrCreate(
            ['disk' => 'legacy', 'path' => $normalizedPath],
            [
                'directory' => dirname($normalizedPath) !== '.' ? dirname($normalizedPath) : null,
                'filename' => basename($normalizedPath),
                'original_name' => basename($cleanPath),
                'mime_type' => $this->guessMimeType($normalizedPath),
                'extension' => strtolower((string) pathinfo($normalizedPath, PATHINFO_EXTENSION)) ?: null,
                'size_bytes' => 0,
                'width' => null,
                'height' => null,
                'alt_text_ar' => null,
                'alt_text_en' => null,
                'caption_ar' => null,
                'caption_en' => null,
                'title_ar' => $titleAr,
                'title_en' => $titleEn,
                'webp_path' => null,
                'srcset_json' => null,
                'uploaded_by' => null,
            ],
        );

        return (int) $media->getKey();
    }

    /**
     * @return list<string>|null
     */
    protected function normalizedLegacyList(?string $value): ?array
    {
        $cleaned = $this->textCleaner()->clean((string) $value);

        if ($cleaned === null || $cleaned === '') {
            return null;
        }

        $parts = preg_split('/[\r\n,;|،]+/u', $cleaned) ?: [];
        $items = array_values(array_filter(array_map(
            fn (string $part): ?string => $this->textCleaner()->clean($part),
            $parts,
        )));

        if ($items === []) {
            return [$cleaned];
        }

        return array_values(array_unique($items));
    }

    protected function slugFrom(object|array $row, array|string $keys, string $fallback): string
    {
        $source = $this->cleanedString($row, $keys) ?? $fallback;
        $slug = Str::slug($source);

        return $slug !== '' ? $slug : Str::slug($fallback);
    }

    /**
     * @param  array<string, mixed>|null  $metadata
     */
    protected function logSkip(
        string $module,
        string $batchName,
        string $sourceTable,
        int|string|null $sourceId,
        string $targetTable,
        string $message,
        ?array $metadata = null,
    ): void {
        $this->migrationLogger()->log($module, $batchName, $sourceTable, $sourceId, $targetTable, null, 'skipped', $message, $metadata);
    }

    /**
     * @param  array<string, mixed>|null  $rawSummary
     */
    protected function reject(
        string $module,
        string $sourceTable,
        int|string|null $sourceId,
        string $reasonCode,
        string $reasonMessage,
        ?array $rawSummary = null,
    ): void {
        $this->migrationLogger()->reject($module, $sourceTable, $sourceId, $reasonCode, $reasonMessage, $rawSummary);
    }

    /**
     * @param  array<string, mixed>|null  $payloadJson
     */
    protected function snapshotLegacyRow(
        string $module,
        string $batchName,
        string $sourceTable,
        int|string|null $sourceId,
        ?string $legacyKey,
        ?string $classification,
        ?string $locale = null,
        ?array $payloadJson = null,
        ?string $payloadText = null,
    ): LegacyRecordSnapshot {
        return LegacyRecordSnapshot::query()->updateOrCreate(
            [
                'source_table' => $sourceTable,
                'source_id' => is_numeric($sourceId) ? (int) $sourceId : null,
                'legacy_key' => $legacyKey,
            ],
            [
                'module' => $module,
                'batch_name' => $batchName,
                'classification' => $classification,
                'locale' => $locale,
                'payload_json' => $payloadJson,
                'payload_text' => $payloadText,
            ],
        );
    }
}
