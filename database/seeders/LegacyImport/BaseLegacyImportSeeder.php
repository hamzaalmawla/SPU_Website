<?php

declare(strict_types=1);

namespace Database\Seeders\LegacyImport;

use App\Models\LegacyRecordSnapshot;
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
