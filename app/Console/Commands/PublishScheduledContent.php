<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Contracts\Cms\CmsWorkflowServiceInterface;
use App\Contracts\Homepage\HomepagePublishingServiceInterface;
use App\Contracts\News\NewsAdminWorkflowServiceInterface;
use App\Contracts\Page\AboutNavigationCardServiceInterface;
use App\Contracts\Page\PageServiceInterface;
use Illuminate\Console\Command;

final class PublishScheduledContent extends Command
{
    protected $signature = 'content:publish-scheduled';

    protected $description = 'Publish due scheduled homepage drafts, pages, CMS targets, and about navigation cards.';

    public function handle(
        HomepagePublishingServiceInterface $homepagePublishingService,
        PageServiceInterface $pageService,
        CmsWorkflowServiceInterface $cmsWorkflowService,
        NewsAdminWorkflowServiceInterface $newsAdminWorkflowService,
        AboutNavigationCardServiceInterface $aboutNavigationCardService,
    ): int {
        $homepageCount = $homepagePublishingService->publishDueScheduled();
        $pageCount = $pageService->publishDueScheduled();
        $cmsCount = $cmsWorkflowService->publishDueScheduled();
        $newsCount = $newsAdminWorkflowService->publishDueScheduled();
        $aboutNavigationCardCount = $aboutNavigationCardService->publishDueScheduled();

        $this->info("Published {$homepageCount} scheduled homepage drafts, {$pageCount} scheduled pages, {$cmsCount} scheduled CMS targets, {$newsCount} scheduled news articles, and {$aboutNavigationCardCount} scheduled about navigation cards.");

        return self::SUCCESS;
    }
}
