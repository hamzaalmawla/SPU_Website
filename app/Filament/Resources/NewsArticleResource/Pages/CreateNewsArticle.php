<?php

declare(strict_types=1);

namespace App\Filament\Resources\NewsArticleResource\Pages;

use App\Contracts\Cms\CmsWorkflowServiceInterface;
use App\Contracts\News\NewsArticleCmsServiceInterface;
use App\DTOs\News\NewsArticleCmsDataDTO;
use App\Filament\Resources\NewsArticleResource;
use App\Models\News\NewsArticle;
use App\Models\User\User;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateNewsArticle extends CreateRecord
{
    protected static string $resource = NewsArticleResource::class;

    private NewsArticleCmsServiceInterface $newsArticleCmsService;

    private CmsWorkflowServiceInterface $cmsWorkflowService;

    public function boot(
        NewsArticleCmsServiceInterface $newsArticleCmsService,
        CmsWorkflowServiceInterface $cmsWorkflowService,
    ): void {
        $this->newsArticleCmsService = $newsArticleCmsService;
        $this->cmsWorkflowService = $cmsWorkflowService;
    }

    /** @param array<string, mixed> $data */
    protected function handleRecordCreation(array $data): Model
    {
        $user = auth()->user();
        abort_unless($user instanceof User, 403);

        $prepared = $this->newsArticleCmsService->prepareDraft(
            new NewsArticleCmsDataDTO(null, $data),
            (int) $user->getKey(),
        );
        $this->cmsWorkflowService->saveDraft(
            $prepared->targetKey ?? throw new \RuntimeException('News article draft target was not created.'),
            $prepared->payload,
            (int) $user->getKey(),
        );

        return NewsArticle::query()->findOrFail($prepared->articleId);
    }

    protected function getCreatedNotificationTitle(): ?string
    {
        return __('admin.news_article.workflow.notifications.draft_saved');
    }
}
