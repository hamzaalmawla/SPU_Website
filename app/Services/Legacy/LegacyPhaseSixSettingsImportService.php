<?php

declare(strict_types=1);

namespace App\Services\Legacy;

use App\Contracts\Legacy\LegacyPhaseSixSettingsImportServiceInterface;
use App\Contracts\Shared\CacheServiceInterface;
use App\DTOs\Legacy\LegacyPhaseSixSettingsImportResultDTO;
use App\Models\Legacy\LegacyContentMapping;
use App\Models\Legacy\LegacyReviewItem;
use App\Models\Settings\Setting;
use App\Models\Shared\MigrationLog;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;

final class LegacyPhaseSixSettingsImportService implements LegacyPhaseSixSettingsImportServiceInterface
{
    private const APPROVAL_TOKEN = 'phase6-settings';

    public function __construct(
        private readonly CacheServiceInterface $cacheService,
    ) {}

    public function import(?string $inputPath = null, bool $write = false, ?string $approval = null, string $disk = 'local', ?string $batch = null): LegacyPhaseSixSettingsImportResultDTO
    {
        if ($write && $approval !== self::APPROVAL_TOKEN) {
            throw new InvalidArgumentException('Importing Phase 6 settings requires --approve='.self::APPROVAL_TOKEN.'.');
        }

        $inputPath = $this->inputPath($disk, $inputPath);
        $batch = $batch !== null && trim($batch) !== '' ? trim($batch) : 'phase6-settings-'.now()->format('Ymd_His');
        $rows = $this->readCsv($disk, $inputPath);
        [$units, $skippedRows, $skipReasonCounts] = $this->importUnits($rows);
        $importableRows = count($units);
        $importedRows = 0;

        if ($write) {
            DB::transaction(function () use ($units, $batch, &$importedRows): void {
                foreach ($units as $unit) {
                    $targetId = $this->writeUnit($unit);
                    $this->logSources($unit, $batch, $targetId);
                    $this->markMappingsImported($unit, $targetId);
                    $importedRows++;
                }
            });

            if ($importedRows > 0) {
                $this->invalidateSettingsCaches();
            }
        }

        return new LegacyPhaseSixSettingsImportResultDTO(
            written: $write,
            disk: $disk,
            inputPath: $inputPath,
            batch: $batch,
            scannedRows: count($rows),
            importableRows: $importableRows,
            importedRows: $importedRows,
            duplicateCollapsedRows: count($rows) - $importableRows - $skippedRows,
            skippedRows: $skippedRows,
            skipReasonCounts: $skipReasonCounts,
        );
    }

    private function inputPath(string $disk, ?string $inputPath): string
    {
        if ($inputPath !== null && trim($inputPath) !== '') {
            $inputPath = trim($inputPath, '/');

            if (! Storage::disk($disk)->exists($inputPath)) {
                throw new InvalidArgumentException('Settings mapping input file does not exist: '.$inputPath);
            }

            return $inputPath;
        }

        $files = array_values(array_filter(
            Storage::disk($disk)->files('legacy-import-exports/phase6-settings'),
            static fn (string $path): bool => str_ends_with($path, '_safe_mappings.csv'),
        ));
        sort($files);
        $latest = end($files);

        if (! is_string($latest) || $latest === '') {
            throw new InvalidArgumentException('No Phase 6 settings safe mapping CSV was found. Run legacy-import:phase6-settings-mapping first.');
        }

        return $latest;
    }

    /** @return array<int, array<string, string>> */
    private function readCsv(string $disk, string $path): array
    {
        $payload = Storage::disk($disk)->get($path);
        $stream = fopen('php://temp', 'r+');

        if ($stream === false) {
            return [];
        }

        fwrite($stream, $payload);
        rewind($stream);
        $headers = fgetcsv($stream);
        $rows = [];

        if (! is_array($headers)) {
            fclose($stream);

            return [];
        }

        while (($values = fgetcsv($stream)) !== false) {
            $row = [];

            foreach ($headers as $index => $header) {
                $row[(string) $header] = is_scalar($values[$index] ?? null) ? (string) $values[$index] : '';
            }

            $rows[] = $row;
        }

        fclose($stream);

        return $rows;
    }

    /** @param array<int, array<string, string>> $rows @return array{0: array<string, array<string, mixed>>, 1: int, 2: array<string, int>} */
    private function importUnits(array $rows): array
    {
        $units = [];
        $skippedRows = 0;
        $skipReasonCounts = [];

        foreach ($rows as $row) {
            $skipReason = $this->skipReason($row);

            if ($skipReason !== null) {
                $skippedRows++;
                $skipReasonCounts[$skipReason] = ($skipReasonCounts[$skipReason] ?? 0) + 1;

                continue;
            }

            $unitKey = implode('|', [
                $row['legacy_name'],
                $row['target_group'],
                $row['target_key'],
                $row['target_locale'],
                $row['value_shape'],
                $row['normalized_value'],
            ]);
            $units[$unitKey] ??= [
                'legacy_name' => $row['legacy_name'],
                'value' => $row['normalized_value'],
                'target_group' => $row['target_group'],
                'target_key' => $row['target_key'],
                'target_locale' => $row['target_locale'],
                'value_shape' => $row['value_shape'],
                'sources' => [],
            ];
            $units[$unitKey]['sources'][] = [
                'source_table' => $row['source_table'],
                'source_id' => (int) $row['source_id'],
            ];
        }

        return [$units, $skippedRows, $skipReasonCounts];
    }

    /** @param array<string, string> $row */
    private function skipReason(array $row): ?string
    {
        if (($row['mapping_status_detail'] ?? '') !== 'safe_mapping') {
            return 'not_safe_mapping';
        }

        foreach (['source_table', 'source_id', 'legacy_name', 'normalized_value', 'target_group', 'target_key', 'value_shape'] as $key) {
            if (trim((string) ($row[$key] ?? '')) === '') {
                return 'missing_required_column_value';
            }
        }

        if (MigrationLog::query()
            ->where('module', 'settings')
            ->where('source_table', $row['source_table'])
            ->where('source_id', (int) $row['source_id'])
            ->where('target_table', 'settings')
            ->where('status', 'success')
            ->exists()) {
            return 'already_imported';
        }

        return null;
    }

    /** @param array<string, mixed> $unit */
    private function writeUnit(array $unit): int
    {
        $shape = (string) $unit['value_shape'];

        if (str_starts_with($shape, 'social_link:')) {
            return $this->writeSocialLink($unit);
        }

        if ($shape === 'text_url') {
            return $this->writeTextUrl($unit);
        }

        if ($shape === 'apply_cta_url') {
            return $this->writeApplyCta($unit);
        }

        if (str_starts_with($shape, 'contact_email:')) {
            return $this->writeContactEmail($unit);
        }

        throw new InvalidArgumentException('Unsupported Phase 6 settings value shape: '.$shape);
    }

    /** @param array<string, mixed> $unit */
    private function writeSocialLink(array $unit): int
    {
        $platform = substr((string) $unit['value_shape'], strlen('social_link:'));
        $targetId = 0;

        foreach (['ar', 'en'] as $locale) {
            $setting = $this->setting((string) $unit['target_group'], (string) $unit['target_key'], $locale, 'json');
            $payload = is_array($setting->value_json) ? $setting->value_json : [];
            $links = $this->replaceListItem($payload['social_links'] ?? [], 'platform', $platform, [
                'platform' => $platform,
                'url' => (string) $unit['value'],
                'is_enabled' => true,
            ]);
            $setting->forceFill(['value_json' => ['social_links' => $links], 'value_text' => null, 'is_public' => true])->save();
            $targetId = (int) $setting->getKey();
        }

        return $targetId;
    }

    /** @param array<string, mixed> $unit */
    private function writeTextUrl(array $unit): int
    {
        $setting = $this->setting((string) $unit['target_group'], (string) $unit['target_key'], '', 'text');
        $setting->forceFill(['value_json' => null, 'value_text' => (string) $unit['value'], 'is_public' => true])->save();

        return (int) $setting->getKey();
    }

    /** @param array<string, mixed> $unit */
    private function writeApplyCta(array $unit): int
    {
        $targetId = 0;

        foreach (['ar', 'en'] as $locale) {
            $setting = $this->setting((string) $unit['target_group'], (string) $unit['target_key'], $locale, 'json');
            $payload = is_array($setting->value_json) ? $setting->value_json : [];
            $setting->forceFill([
                'value_json' => [
                    'label' => is_string($payload['label'] ?? null) && trim((string) $payload['label']) !== '' ? $payload['label'] : ($locale === 'ar' ? 'قدّم الآن' : 'Apply now'),
                    'url' => (string) $unit['value'],
                    'is_enabled' => true,
                ],
                'value_text' => null,
                'is_public' => true,
            ])->save();
            $targetId = (int) $setting->getKey();
        }

        return $targetId;
    }

    /** @param array<string, mixed> $unit */
    private function writeContactEmail(array $unit): int
    {
        $label = substr((string) $unit['value_shape'], strlen('contact_email:'));
        $targetId = 0;

        foreach (['ar', 'en'] as $locale) {
            $setting = $this->setting((string) $unit['target_group'], (string) $unit['target_key'], $locale, 'json');
            $payload = is_array($setting->value_json) ? $setting->value_json : [];
            $links = $this->replaceListItem($payload['contact_links'] ?? [], 'label', $label, [
                'type' => 'email',
                'label' => $label,
                'value' => (string) $unit['value'],
            ]);
            $setting->forceFill(['value_json' => ['contact_links' => $links], 'value_text' => null, 'is_public' => true])->save();
            $targetId = (int) $setting->getKey();
        }

        return $targetId;
    }

    private function setting(string $group, string $key, string $locale, string $type): Setting
    {
        return Setting::query()->firstOrCreate(
            ['group_key' => $group, 'key' => $key, 'locale' => $locale],
            ['type' => $type, 'value_json' => $type === 'json' ? [] : null, 'value_text' => null, 'is_public' => true],
        );
    }

    /** @param mixed $items @param array<string, mixed> $replacement @return array<int, array<string, mixed>> */
    private function replaceListItem(mixed $items, string $matchKey, string $matchValue, array $replacement): array
    {
        $items = is_array($items) ? array_values(array_filter($items, 'is_array')) : [];
        $replaced = false;

        foreach ($items as $index => $item) {
            if ((string) ($item[$matchKey] ?? '') !== $matchValue) {
                continue;
            }

            $items[$index] = $replacement;
            $replaced = true;
        }

        if (! $replaced) {
            $items[] = $replacement;
        }

        return $items;
    }

    /** @param array<string, mixed> $unit */
    private function logSources(array $unit, string $batch, int $targetId): void
    {
        foreach (is_array($unit['sources'] ?? null) ? $unit['sources'] : [] as $source) {
            MigrationLog::query()->create([
                'module' => 'settings',
                'batch_name' => $batch,
                'source_table' => (string) $source['source_table'],
                'source_id' => (int) $source['source_id'],
                'target_table' => 'settings',
                'target_id' => $targetId,
                'status' => 'success',
                'message' => 'Imported Phase 6 legacy setting from reviewed safe mapping report.',
                'metadata' => [
                    'legacy_name' => $unit['legacy_name'],
                    'target_group' => $unit['target_group'],
                    'target_key' => $unit['target_key'],
                    'value_shape' => $unit['value_shape'],
                    'phase' => 'phase6',
                ],
            ]);
        }
    }

    /** @param array<string, mixed> $unit */
    private function markMappingsImported(array $unit, int $targetId): void
    {
        foreach (is_array($unit['sources'] ?? null) ? $unit['sources'] : [] as $source) {
            LegacyContentMapping::query()
                ->where('source_table', (string) $source['source_table'])
                ->where('source_id', (int) $source['source_id'])
                ->whereIn('mapping_status', ['proposed', 'approved'])
                ->update([
                    'mapping_status' => 'approved',
                    'approved_at' => now(),
                    'target_table' => 'settings',
                    'target_id' => $targetId,
                ]);

            LegacyReviewItem::query()
                ->where('source_table', (string) $source['source_table'])
                ->where('source_id', (int) $source['source_id'])
                ->update([
                    'mapping_status' => 'approved',
                    'review_status' => 'mapping_already_approved',
                ]);
        }
    }

    private function invalidateSettingsCaches(): void
    {
        foreach (['navigation', 'footer'] as $group) {
            $this->cacheService->forget('settings.group.'.$group.'.all');

            foreach (['ar', 'en'] as $locale) {
                $this->cacheService->forget('settings.group.'.$group.'.'.$locale);
                $this->cacheService->forget('settings.public.'.$locale);
                $this->cacheService->forget('navigation.payload.'.$locale);
            }
        }

        foreach (['ar', 'en'] as $locale) {
            $this->cacheService->forget('settings.apply_cta.'.$locale);
            $this->cacheService->forget('settings.footer.'.$locale);
            $this->cacheService->forget('settings.social_contact.'.$locale);
        }

        $this->cacheService->forget('settings.student_portal_url');
        $this->cacheService->forget('settings.staff_access_url');
    }
}
