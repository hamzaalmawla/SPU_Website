<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Shared\AuditLog;
use Illuminate\Console\Command;

final class PruneAuditLogs extends Command
{
    protected $signature = 'audit:prune {--dry-run : Show how many records would be pruned without deleting them}';

    protected $description = 'Prune audit logs older than the configured retention period.';

    public function handle(): int
    {
        $retentionDays = (int) config('audit.retention_days', 90);

        if ($retentionDays <= 0) {
            $this->info('Audit log pruning is disabled because audit.retention_days is 0.');

            return self::SUCCESS;
        }

        $cutoff = now()->subDays($retentionDays);
        $query = AuditLog::query()->where('created_at', '<', $cutoff);
        $count = (int) $query->count();

        if ((bool) $this->option('dry-run')) {
            $this->info("{$count} audit log records are eligible for pruning.");

            return self::SUCCESS;
        }

        $deleted = (int) $query->delete();

        $this->info("Pruned {$deleted} audit log records older than {$retentionDays} days.");

        return self::SUCCESS;
    }
}
