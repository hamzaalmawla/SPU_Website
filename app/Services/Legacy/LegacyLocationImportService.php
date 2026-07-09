<?php

declare(strict_types=1);

namespace App\Services\Legacy;

use App\Contracts\Legacy\LegacyCleanedRowServiceInterface;
use App\Contracts\Legacy\LegacyLocationImportServiceInterface;
use App\DTOs\Legacy\LegacyCleanedRowDTO;
use App\DTOs\Legacy\LegacyLocationImportResultDTO;
use App\Models\Location\City;
use App\Models\Location\CityTranslation;
use App\Models\Location\Country;
use App\Models\Location\CountryTranslation;
use App\Models\Shared\MigrationLog;
use App\Support\LegacyImport\OldDatabaseConnection;
use Illuminate\Support\Str;

final class LegacyLocationImportService implements LegacyLocationImportServiceInterface
{
    private const MODULE = 'locations';

    private const APPROVAL = 'phase6-locations';

    public function __construct(
        private readonly OldDatabaseConnection $oldDatabaseConnection,
        private readonly LegacyCleanedRowServiceInterface $cleanedRowService,
    ) {}

    public function import(bool $write, ?string $approval, ?string $batch, bool $enable): LegacyLocationImportResultDTO
    {
        if ($write && $approval !== self::APPROVAL) {
            throw new \InvalidArgumentException('Location import write mode requires --approve='.self::APPROVAL.'.');
        }

        $batchName = $batch ?: 'phase6-locations-'.now()->format('YmdHis');
        $connection = $this->oldDatabaseConnection->connection();
        $countries = $connection->table('jx_countries')->orderBy('id')->get();
        $cities = $connection->table('jx_cities')->orderBy('id')->get();
        $importableCountries = 0;
        $importableCities = 0;
        $importedCountries = 0;
        $importedCities = 0;
        $skippedRows = 0;
        $skipReasonCounts = [];
        $countryTargets = [];

        foreach ($countries as $countryRow) {
            $sourceId = $this->integerValue($countryRow, 'id');

            if ($sourceId === null) {
                $this->countSkip($skipReasonCounts, 'missing_source_id');
                $skippedRows++;
                continue;
            }

            if ($this->alreadyImported('jx_countries', $sourceId)) {
                $this->countSkip($skipReasonCounts, 'already_imported');
                $skippedRows++;
                continue;
            }

            $cleaned = $this->cleanedRow('countries', 'jx_countries', $countryRow);
            $arName = $this->localizedName($cleaned, $countryRow, 'ar_name', 'en_name');
            $enName = $this->localizedName($cleaned, $countryRow, 'en_name', 'ar_name');

            if ($arName === null || $enName === null) {
                $this->countSkip($skipReasonCounts, 'missing_name');
                $skippedRows++;
                $this->writeSkip($write, $batchName, 'jx_countries', $sourceId, 'Skipped legacy country without AR/EN name.');
                continue;
            }

            $importableCountries++;

            if (! $write) {
                continue;
            }

            $country = Country::query()->create([
                'code' => $this->legacyCountryCode($sourceId),
                'code3' => $this->legacyCountryCode3($sourceId),
                'phone_code' => null,
                'currency_code' => null,
                'is_enabled' => $enable,
                'sort_order' => $sourceId,
            ]);

            foreach (['ar' => $arName, 'en' => $enName] as $locale => $name) {
                CountryTranslation::query()->create([
                    'country_id' => $country->getKey(),
                    'locale' => $locale,
                    'name' => $name,
                    'nationality' => null,
                ]);
            }

            $countryTargets[$sourceId] = (int) $country->getKey();
            $importedCountries++;
            $this->writeSuccess($batchName, 'jx_countries', $sourceId, 'countries', (int) $country->getKey(), [
                'legacy_fr_name' => $this->stringValue($this->rawValue($countryRow, 'fr_name')),
            ], $enable);
        }

        if ($write) {
            $countryTargets += MigrationLog::query()
                ->where('module', self::MODULE)
                ->where('source_table', 'jx_countries')
                ->where('target_table', 'countries')
                ->where('status', 'success')
                ->pluck('target_id', 'source_id')
                ->map(fn (mixed $targetId): int => (int) $targetId)
                ->all();
        }

        foreach ($cities as $cityRow) {
            $sourceId = $this->integerValue($cityRow, 'id');
            $legacyCountryId = $this->integerValue($cityRow, 'country_id');

            if ($sourceId === null) {
                $this->countSkip($skipReasonCounts, 'missing_source_id');
                $skippedRows++;
                continue;
            }

            if ($this->alreadyImported('jx_cities', $sourceId)) {
                $this->countSkip($skipReasonCounts, 'already_imported');
                $skippedRows++;
                continue;
            }

            $cleaned = $this->cleanedRow('cities', 'jx_cities', $cityRow);
            $arName = $this->localizedName($cleaned, $cityRow, 'ar_name', 'en_name');
            $enName = $this->localizedName($cleaned, $cityRow, 'en_name', 'ar_name');

            if ($legacyCountryId === null || ($write && ! isset($countryTargets[$legacyCountryId]))) {
                $this->countSkip($skipReasonCounts, 'missing_country');
                $skippedRows++;
                $this->writeSkip($write, $batchName, 'jx_cities', $sourceId, 'Skipped legacy city without imported country.', ['legacy_country_id' => $legacyCountryId]);
                continue;
            }

            if ($arName === null || $enName === null) {
                $this->countSkip($skipReasonCounts, 'missing_name');
                $skippedRows++;
                $this->writeSkip($write, $batchName, 'jx_cities', $sourceId, 'Skipped legacy city without AR/EN name.', ['legacy_country_id' => $legacyCountryId]);
                continue;
            }

            $importableCities++;

            if (! $write) {
                continue;
            }

            $city = City::query()->create([
                'country_id' => $countryTargets[$legacyCountryId],
                'code' => 'legacy-'.$sourceId,
                'latitude' => null,
                'longitude' => null,
                'is_enabled' => $enable && $this->booleanVisible($cityRow),
                'sort_order' => $sourceId,
            ]);

            foreach (['ar' => $arName, 'en' => $enName] as $locale => $name) {
                CityTranslation::query()->create([
                    'city_id' => $city->getKey(),
                    'locale' => $locale,
                    'name' => $name,
                ]);
            }

            $importedCities++;
            $this->writeSuccess($batchName, 'jx_cities', $sourceId, 'cities', (int) $city->getKey(), [
                'legacy_country_id' => $legacyCountryId,
                'legacy_is_visible' => $this->rawValue($cityRow, 'is_visible'),
            ], $enable);
        }

        return new LegacyLocationImportResultDTO(
            written: $write,
            batch: $batchName,
            enabledOnImport: $enable,
            scannedCountries: $countries->count(),
            scannedCities: $cities->count(),
            importableCountries: $importableCountries,
            importableCities: $importableCities,
            importedCountries: $importedCountries,
            importedCities: $importedCities,
            skippedRows: $skippedRows,
            skipReasonCounts: $skipReasonCounts,
        );
    }

    private function cleanedRow(string $module, string $table, object $row): LegacyCleanedRowDTO
    {
        return $this->cleanedRowService->cleanRow($module, $table, $row);
    }

    private function alreadyImported(string $sourceTable, int $sourceId): bool
    {
        return MigrationLog::query()
            ->where('module', self::MODULE)
            ->where('source_table', $sourceTable)
            ->where('source_id', $sourceId)
            ->where('status', 'success')
            ->exists();
    }

    private function localizedName(LegacyCleanedRowDTO $cleaned, object $row, string $primaryKey, string $fallbackKey): ?string
    {
        return $this->stringValue($cleaned->values[$primaryKey] ?? $this->rawValue($row, $primaryKey))
            ?? $this->stringValue($cleaned->values[$fallbackKey] ?? $this->rawValue($row, $fallbackKey));
    }

    private function legacyCountryCode(int $sourceId): string
    {
        return strtoupper(str_pad(base_convert((string) $sourceId, 10, 36), 2, '0', STR_PAD_LEFT));
    }

    private function legacyCountryCode3(int $sourceId): string
    {
        return 'L'.Str::substr($this->legacyCountryCode($sourceId), -2);
    }

    private function booleanVisible(object $row): bool
    {
        $value = $this->rawValue($row, 'is_visible');

        return $value === null || (string) $value === '1' || $value === 1 || $value === true;
    }

    private function integerValue(object $row, string $key): ?int
    {
        $value = $this->rawValue($row, $key);

        return is_numeric($value) ? (int) $value : null;
    }

    private function stringValue(mixed $value): ?string
    {
        if (! is_scalar($value)) {
            return null;
        }

        $value = trim(html_entity_decode((string) $value, ENT_QUOTES | ENT_HTML5, 'UTF-8'));

        return $value !== '' ? $value : null;
    }

    private function rawValue(object $row, string $key): mixed
    {
        return property_exists($row, $key) ? $row->{$key} : null;
    }

    /** @param array<string, int> $skipReasonCounts */
    private function countSkip(array &$skipReasonCounts, string $reason): void
    {
        $skipReasonCounts[$reason] = ($skipReasonCounts[$reason] ?? 0) + 1;
    }

    /** @param array<string, mixed> $metadata */
    private function writeSkip(bool $write, string $batch, string $sourceTable, int $sourceId, string $message, array $metadata = []): void
    {
        if (! $write) {
            return;
        }

        MigrationLog::query()->create([
            'module' => self::MODULE,
            'batch_name' => $batch,
            'source_table' => $sourceTable,
            'source_id' => $sourceId,
            'target_table' => null,
            'target_id' => null,
            'status' => 'skipped',
            'message' => $message,
            'metadata' => $metadata + ['phase' => 'phase6', 'db_first' => true],
        ]);
    }

    /** @param array<string, mixed> $metadata */
    private function writeSuccess(string $batch, string $sourceTable, int $sourceId, string $targetTable, int $targetId, array $metadata, bool $enable): void
    {
        MigrationLog::query()->create([
            'module' => self::MODULE,
            'batch_name' => $batch,
            'source_table' => $sourceTable,
            'source_id' => $sourceId,
            'target_table' => $targetTable,
            'target_id' => $targetId,
            'status' => 'success',
            'message' => 'Imported DB-first legacy reference location.',
            'metadata' => $metadata + ['phase' => 'phase6', 'db_first' => true, 'enabled_on_import' => $enable],
        ]);
    }
}
