<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\MigrationLog;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

final class LegacyImportVerifyCommand extends Command
{
    protected $signature = 'legacy-import:verify {module?}';

    protected $description = 'Verify successful legacy imports by module and target table.';

    public function handle(): int
    {
        $module = $this->argument('module');
        $module = is_string($module) && $module !== '' ? $module : null;
        $logs = MigrationLog::query()->where('status', 'success');

        if ($module !== null) {
            $logs->where('module', $module);
        }

        $summary = $logs
            ->select('module', 'target_table', DB::raw('count(distinct source_table, source_id) as total'))
            ->groupBy('module', 'target_table')
            ->orderBy('module')
            ->orderBy('target_table')
            ->get()
            ->map(fn (MigrationLog $log): array => [
                'module' => $log->module,
                'target_table' => $log->target_table,
                'total' => $log->total,
            ])
            ->all();

        $this->info('Legacy Import Verification'.($module !== null ? ' ['.$module.']' : ''));

        if ($summary === []) {
            $this->warn('No successful imports found to verify.');

            return self::SUCCESS;
        }

        $this->table(['Module', 'Target Table', 'Imported Rows'], $summary);

        return self::SUCCESS;
    }
}
