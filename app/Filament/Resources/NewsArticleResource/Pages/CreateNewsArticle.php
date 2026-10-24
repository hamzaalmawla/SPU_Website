<?php

declare(strict_types=1);

namespace App\Filament\Resources\NewsArticleResource\Pages;

use App\Contracts\Cms\CmsWorkflowServiceInterface;
use App\Contracts\News\NewsArticleCmsServiceInterface;
use App\DTOs\News\NewsArticleCmsDataDTO;
use App\Filament\Resources\NewsArticleResource;
use App\Models\News\NewsArticle;
use App\Models\User\User;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class CreateNewsArticle extends CreateRecord
{
    protected static string $resource = NewsArticleResource::class;

    private NewsArticleCmsServiceInterface $newsArticleCmsService;

    private CmsWorkflowServiceInterface $cmsWorkflowService;

    private bool $published = false;

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
        $targetKey = $prepared->targetKey ?? throw new \RuntimeException('News article draft target was not created.');
        $this->cmsWorkflowService->saveDraft($targetKey, $prepared->payload, (int) $user->getKey());

        // A new article goes live at once for anyone allowed to publish it;
        // otherwise it stays a draft for a publisher to put live.
        if (Gate::allows('news_articles.publish')) {
            $this->publish($targetKey, (int) $user->getKey());
        }

        return NewsArticle::query()->findOrFail($prepared->articleId);
    }

    protected function getCreatedNotificationTitle(): ?string
    {
        return __($this->published
            ? 'admin.news_article.workflow.notifications.published'
            : 'admin.news_article.workflow.notifications.draft_saved');
    }

    /**
     * The draft is already saved, so a failed publish keeps it and tells the
     * editor what to fix on the edit page.
     */
    private function publish(string $targetKey, int $userId): void
    {
        try {
            $this->published = $this->cmsWorkflowService->publish($targetKey, $userId);
        } catch (ValidationException $exception) {
            Notification::make()
                ->title(__('admin.news_article.workflow.notifications.publish_failed'))
                ->body(collect($exception->errors())->flatten()->implode(' '))
                ->danger()
                ->persistent()
                ->send();
        } catch (\Throwable $exception) {
            report($exception);
            Notification::make()
                ->title(__('admin.news_article.workflow.notifications.publish_failed'))
                ->body(__('admin.news_article.workflow.notifications.safe_error'))
                ->danger()
                ->send();
        }
    }
}
