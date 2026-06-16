<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Contracts\Homepage\HomepagePublishingServiceInterface;
use App\Contracts\Page\PageServiceInterface;
use Illuminate\Console\Command;

final class PublishScheduledContent extends Command
{
    protected $signature = 'content:publish-scheduled';

    protected $description = 'Publish due scheduled homepage drafts and pages.';

    public function handle(
        HomepagePublishingServiceInterface $homepagePublishingService,
        PageServiceInterface $pageService,
    ): int {
        $homepageCount = $homepagePublishingService->publishDueScheduled();
        $pageCount = $pageService->publishDueScheduled();

        $this->info("Published {$homepageCount} scheduled homepage drafts and {$pageCount} scheduled pages.");

        return self::SUCCESS;
    }
}
