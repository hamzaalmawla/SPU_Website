<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Contracts\Cms\CmsWorkflowServiceInterface;
use App\Contracts\Homepage\HomepagePublishingServiceInterface;
use App\Contracts\Page\PageServiceInterface;
use Illuminate\Console\Command;

final class PublishScheduledContent extends Command
{
    protected $signature = 'content:publish-scheduled';

    protected $description = 'Publish due scheduled homepage drafts, pages, and CMS targets.';

    public function handle(
        HomepagePublishingServiceInterface $homepagePublishingService,
        PageServiceInterface $pageService,
        CmsWorkflowServiceInterface $cmsWorkflowService,
    ): int {
        $homepageCount = $homepagePublishingService->publishDueScheduled();
        $pageCount = $pageService->publishDueScheduled();
        $cmsCount = $cmsWorkflowService->publishDueScheduled();

        $this->info("Published {$homepageCount} scheduled homepage drafts, {$pageCount} scheduled pages, and {$cmsCount} scheduled CMS targets.");

        return self::SUCCESS;
    }
}
