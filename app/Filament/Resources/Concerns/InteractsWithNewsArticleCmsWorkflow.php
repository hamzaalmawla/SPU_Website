<?php

declare(strict_types=1);

namespace App\Filament\Resources\Concerns;

use App\Contracts\Cms\CmsWorkflowServiceInterface;
use App\Contracts\News\NewsArticleCmsServiceInterface;
use App\DTOs\Cms\CmsDraftDTO;
use App\DTOs\News\NewsArticleCmsDataDTO;
use App\Exceptions\ConflictException;
use App\Models\User\User;
use Filament\Actions\Action;
use Filament\Forms\Components\DateTimePicker;
use Filament\Notifications\Notification;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

trait InteractsWithNewsArticleCmsWorkflow
{
    private NewsArticleCmsServiceInterface $newsArticleCmsService;

    private CmsWorkflowServiceInterface $cmsWorkflowService;

    private ?int $draftVersion = null;

    private bool $draftSaveSucceeded = true;

    public function boot(
        NewsArticleCmsServiceInterface $newsArticleCmsService,
        CmsWorkflowServiceInterface $cmsWorkflowService,
    ): void {
        $this->newsArticleCmsService = $newsArticleCmsService;
        $this->cmsWorkflowService = $cmsWorkflowService;
    }

    /** @param array<string, mixed> $data @return array<string, mixed> */
    protected function mutateFormDataBeforeFill(array $data): array
    {
        $targetKey = $this->targetKey();
        $payload = $this->cmsWorkflowService->latestEditableDraftPayload($targetKey, $this->userId())
            ?? $this->newsArticleCmsService->getStoredData($targetKey)?->payload;
        $this->draftVersion = $this->cmsWorkflowService->latestEditableDraftVersion($targetKey, $this->userId());

        return is_array($payload) ? $this->payloadToFormData($payload) : $data;
    }

    /** @param array<string, mixed> $data */
    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        $this->draftSaveSucceeded = false;

        try {
            $this->saveDraft($data);
            $this->draftSaveSucceeded = true;
        } catch (ConflictException $exception) {
            $this->draftVersion = $exception->currentVersion;
            $this->conflictNotification();
        } catch (ValidationException $exception) {
            $this->validationNotification('save_failed', $exception);
        }

        return $record;
    }

    protected function getSavedNotification(): ?Notification
    {
        return $this->draftSaveSucceeded
            ? Notification::make()->success()->title(__('admin.news_article.workflow.notifications.draft_saved'))
            : null;
    }

    /** @return array<int, Action> */
    protected function getHeaderActions(): array
    {
        return [
            Action::make('save_draft')
                ->label(__('admin.news_article.workflow.actions.save_draft'))
                ->icon('heroicon-o-check')
                ->color('gray')
                ->action(function (): void {
                    $this->save();
                }),
            Action::make('preview_ar')
                ->label(__('admin.news_article.workflow.actions.preview_ar'))
                ->icon('heroicon-o-eye')
                ->color('info')
                ->action(fn (): mixed => $this->previewArticle('ar')),
            Action::make('preview_en')
                ->label(__('admin.news_article.workflow.actions.preview_en'))
                ->icon('heroicon-o-eye')
                ->color('info')
                ->action(fn (): mixed => $this->previewArticle('en')),
            Action::make('publish')
                ->label(__('admin.news_article.workflow.actions.publish'))
                ->icon('heroicon-o-paper-airplane')
                ->color('success')
                ->requiresConfirmation()
                ->visible(fn (): bool => Gate::allows('publish-content'))
                ->action(function (): void {
                    try {
                        $this->saveDraft($this->form->getState());
                        $this->cmsWorkflowService->publish($this->targetKey(), $this->userId());
                        $this->draftVersion = null;
                        Notification::make()->title(__('admin.news_article.workflow.notifications.published'))->success()->send();
                    } catch (ConflictException $exception) {
                        $this->draftVersion = $exception->currentVersion;
                        $this->conflictNotification();
                    } catch (ValidationException $exception) {
                        $this->validationNotification('publish_failed', $exception);
                    } catch (\Throwable $exception) {
                        report($exception);
                        $this->safeFailureNotification('publish_failed');
                    }
                }),
            Action::make('schedule')
                ->label(__('admin.news_article.workflow.actions.schedule'))
                ->icon('heroicon-o-clock')
                ->color('warning')
                ->visible(fn (): bool => Gate::allows('publish-content'))
                ->form([
                    DateTimePicker::make('publish_at')
                        ->label(__('admin.news_article.workflow.fields.publish_at'))
                        ->required()
                        ->minDate(now())
                        ->native(false),
                ])
                ->action(function (array $data): void {
                    try {
                        $this->saveDraft($this->form->getState());
                        $this->cmsWorkflowService->schedule(
                            $this->targetKey(),
                            new \DateTimeImmutable((string) $data['publish_at']),
                            $this->userId(),
                        );
                        Notification::make()->title(__('admin.news_article.workflow.notifications.scheduled'))->success()->send();
                    } catch (ConflictException $exception) {
                        $this->draftVersion = $exception->currentVersion;
                        $this->conflictNotification();
                    } catch (ValidationException $exception) {
                        $this->validationNotification('schedule_failed', $exception);
                    } catch (\Throwable $exception) {
                        report($exception);
                        $this->safeFailureNotification('schedule_failed');
                    }
                }),
            Action::make('unpublish')
                ->label(__('admin.news_article.workflow.actions.unpublish'))
                ->icon('heroicon-o-x-circle')
                ->color('danger')
                ->requiresConfirmation()
                ->visible(fn (): bool => Gate::allows('publish-content'))
                ->action(function (): void {
                    try {
                        $unpublished = $this->cmsWorkflowService->unpublish($this->targetKey(), $this->userId());
                        Notification::make()
                            ->title(__($unpublished
                                ? 'admin.news_article.workflow.notifications.unpublished'
                                : 'admin.news_article.workflow.notifications.not_published'))
                            ->status($unpublished ? 'success' : 'warning')
                            ->send();
                    } catch (\Throwable $exception) {
                        report($exception);
                        $this->safeFailureNotification('unpublish_failed');
                    }
                }),
        ];
    }

    /** @param array<string, mixed> $data */
    private function saveDraft(array $data): CmsDraftDTO
    {
        $prepared = $this->newsArticleCmsService->prepareDraft(
            new NewsArticleCmsDataDTO((int) $this->record->getKey(), $data),
            $this->userId(),
        );
        $draft = $this->cmsWorkflowService->saveDraft(
            $prepared->targetKey ?? $this->targetKey(),
            $prepared->payload,
            $this->userId(),
            $this->draftVersion,
        );
        $this->draftVersion = $draft->version;

        return $draft;
    }

    private function previewArticle(string $locale): mixed
    {
        try {
            $this->saveDraft($this->form->getState());
            $preview = $this->cmsWorkflowService->preview($this->targetKey(), $locale, $this->userId());

            return $this->redirect($preview->previewUrl);
        } catch (ConflictException $exception) {
            $this->draftVersion = $exception->currentVersion;
            $this->conflictNotification();
        } catch (ValidationException $exception) {
            $this->validationNotification('preview_failed', $exception);
        } catch (\Throwable $exception) {
            report($exception);
            $this->safeFailureNotification('preview_failed');
        }

        return null;
    }

    private function targetKey(): string
    {
        return 'entity.news-article.'.$this->record->getKey();
    }

    private function userId(): int
    {
        $user = auth()->user();
        abort_unless($user instanceof User, 403);

        return (int) $user->getKey();
    }

    /** @param array<string, mixed> $payload @return array<string, mixed> */
    private function payloadToFormData(array $payload): array
    {
        unset($payload['entity_id'], $payload['published_at'], $payload['updated_by']);
        $payload['translations'] = array_values(is_array($payload['translations'] ?? null) ? $payload['translations'] : []);
        $payload['attachments'] = array_values(is_array($payload['attachments'] ?? null) ? $payload['attachments'] : []);
        $payload['seoMeta'] = array_values(is_array($payload['seo_meta'] ?? null) ? $payload['seo_meta'] : []);
        unset($payload['seo_meta']);

        return $payload;
    }

    private function conflictNotification(): void
    {
        Notification::make()
            ->title(__('admin.news_article.workflow.notifications.conflict'))
            ->body(__('admin.news_article.workflow.notifications.conflict_description'))
            ->danger()
            ->persistent()
            ->send();
    }

    private function validationNotification(string $key, ValidationException $exception): void
    {
        Notification::make()
            ->title(__('admin.news_article.workflow.notifications.'.$key))
            ->body(collect($exception->errors())->flatten()->implode(' '))
            ->danger()
            ->persistent()
            ->send();
    }

    private function safeFailureNotification(string $key): void
    {
        Notification::make()
            ->title(__('admin.news_article.workflow.notifications.'.$key))
            ->body(__('admin.news_article.workflow.notifications.safe_error'))
            ->danger()
            ->send();
    }
}
