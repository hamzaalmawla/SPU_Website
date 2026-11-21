<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Contracts\Legacy\LegacyResearchPublicationPublishingServiceInterface;
use Illuminate\Console\Command;

final class LegacyPublishResearchPublicationsCommand extends Command
{
    protected $signature = 'legacy-import:publish-research
        {--actor= : Unlocked user ID with content publication permission}
        {--write : Enable imported research publications for the public archive}
        {--approve= : Required publication token}
        {--batch= : Optional publication batch name}
        {--include-duplicate-review : Explicitly include duplicate-title review records}
        {--json : Output machine-readable JSON}';

    protected $description = 'Publish imported legacy research publications through an approval-gated archive workflow.';

    public function __construct(private readonly LegacyResearchPublicationPublishingServiceInterface $service)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $result = $this->service->publishImported(
            actorUserId: max(0, (int) $this->option('actor')),
            write: (bool) $this->option('write'),
            approval: is_string($this->option('approve')) ? $this->option('approve') : null,
            batch: is_string($this->option('batch')) ? $this->option('batch') : null,
            includeDuplicateReview: (bool) $this->option('include-duplicate-review'),
        );
        $payload = [
            'written' => $result->written,
            'batch' => $result->batch,
            'requested_rows' => $result->requestedRows,
            'eligible_rows' => $result->eligibleRows,
            'published_rows' => $result->publishedRows,
            'already_published_rows' => $result->alreadyPublishedRows,
            'blocked_rows' => $result->blockedRows,
            'blocked_reason_counts' => $result->blockedReasonCounts,
        ];

        if ((bool) $this->option('json')) {
            $this->line((string) json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

            return self::SUCCESS;
        }

        $this->info('Legacy Research Publication Publishing');
        foreach ($payload as $key => $value) {
            $display = is_array($value) ? json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : $value;
            $this->line(str_replace('_', ' ', ucfirst($key)).': '.(is_bool($display) ? ($display ? 'yes' : 'no') : $display));
        }

        return self::SUCCESS;
    }
}
