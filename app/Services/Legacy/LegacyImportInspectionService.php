<?php

declare(strict_types=1);

namespace App\Services\Legacy;

use App\Contracts\Legacy\LegacyImportInspectionServiceInterface;
use App\DTOs\Legacy\LegacyImportDryRunDTO;
use App\DTOs\Legacy\LegacyImportTableInventoryDTO;
use App\Support\LegacyImport\OldDatabaseConnection;
use Illuminate\Support\Collection;
use Throwable;

final class LegacyImportInspectionService implements LegacyImportInspectionServiceInterface
{
    public function __construct(
        private readonly OldDatabaseConnection $oldDatabase,
    ) {}

    public function inventory(?string $module = null): Collection
    {
        $modules = $this->configuredModules();

        if ($module !== null) {
            $definition = $modules[$module] ?? null;

            return $definition === null ? collect() : collect([$this->buildModuleSummary($module, $definition)]);
        }

        return collect($modules)
            ->map(fn (array $definition, string $name): LegacyImportDryRunDTO => $this->buildModuleSummary($name, $definition))
            ->values();
    }

    public function dryRun(string $module): LegacyImportDryRunDTO
    {
        $definition = $this->configuredModules()[$module] ?? null;

        if ($definition === null) {
            return new LegacyImportDryRunDTO(
                module: $module,
                enabled: false,
                canRun: false,
                sourceTables: collect(),
                targetTables: [],
                estimatedSourceRows: 0,
                status: 'unknown_module',
                warnings: ['Legacy import module is not configured.'],
            );
        }

        return $this->buildModuleSummary($module, $definition);
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function configuredModules(): array
    {
        $modules = config('old_database.modules', []);

        return is_array($modules) ? $modules : [];
    }

    /** @param array<string, mixed> $definition */
    private function buildModuleSummary(string $module, array $definition): LegacyImportDryRunDTO
    {
        $enabled = filter_var($definition['enabled'] ?? false, FILTER_VALIDATE_BOOL);
        $sourceTables = $this->sourceTables($definition);
        $targetTables = $this->targetTables($definition);
        $inventory = collect($sourceTables)
            ->map(fn (string $table): LegacyImportTableInventoryDTO => $this->inspectSourceTable($table))
            ->values();
        $estimatedRows = $inventory->sum(fn (LegacyImportTableInventoryDTO $table): int => $table->rowCount ?? 0);
        $warnings = [];

        if (! $enabled) {
            $warnings[] = 'Module is disabled in config/old_database.php and cannot run.';
        }

        foreach ($inventory as $table) {
            if (! $table->exists) {
                $warnings[] = "Source table [{$table->table}] is unavailable.";
            }
        }

        $hasInspectionError = $inventory->contains(fn (LegacyImportTableInventoryDTO $table): bool => $table->error !== null);
        $hasMissingSource = $inventory->contains(fn (LegacyImportTableInventoryDTO $table): bool => ! $table->exists);
        $canRun = $enabled && ! $hasInspectionError && ! $hasMissingSource;

        return new LegacyImportDryRunDTO(
            module: $module,
            enabled: $enabled,
            canRun: $canRun,
            sourceTables: $inventory,
            targetTables: $targetTables,
            estimatedSourceRows: (int) $estimatedRows,
            status: $this->status($enabled, $hasInspectionError, $hasMissingSource),
            warnings: array_values(array_unique($warnings)),
        );
    }

    /** @param array<string, mixed> $definition @return array<int, string> */
    private function sourceTables(array $definition): array
    {
        $tables = $definition['source_tables'] ?? [];

        return is_array($tables) ? array_values(array_filter($tables, 'is_string')) : [];
    }

    /** @param array<string, mixed> $definition @return array<int, string> */
    private function targetTables(array $definition): array
    {
        $tables = $definition['target_tables'] ?? [];

        return is_array($tables) ? array_values(array_filter($tables, 'is_string')) : [];
    }

    private function inspectSourceTable(string $table): LegacyImportTableInventoryDTO
    {
        try {
            if (! $this->oldDatabase->schema()->hasTable($table)) {
                return new LegacyImportTableInventoryDTO($table, false, null);
            }

            return new LegacyImportTableInventoryDTO($table, true, (int) $this->oldDatabase->table($table)->count());
        } catch (Throwable $e) {
            return new LegacyImportTableInventoryDTO($table, false, null, $e->getMessage());
        }
    }

    private function status(bool $enabled, bool $hasInspectionError, bool $hasMissingSource): string
    {
        if (! $enabled) {
            return 'disabled';
        }

        if ($hasInspectionError) {
            return 'connection_unavailable';
        }

        if ($hasMissingSource) {
            return 'missing_sources';
        }

        return 'ready_for_dry_run';
    }
}
