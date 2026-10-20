<?php

declare(strict_types=1);

namespace App\Filament\Resources\Concerns;

use App\Contracts\Cms\CmsWorkflowServiceInterface;
use App\Contracts\Research\ResearchPublicationCmsServiceInterface;
use App\DTOs\Cms\CmsDraftDTO;
use App\DTOs\Research\ResearchPublicationCmsDataDTO;
use App\Exceptions\ConflictException;
use App\Filament\Resources\ResearchPublicationResource;
use App\Models\User\User;
use Filament\Actions\Action;
use Filament\Forms\Components\DateTimePicker;
use Filament\Notifications\Notification;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

trait InteractsWithResearchPublicationCmsWorkflow
{
    private ResearchPublicationCmsServiceInterface $publicationCmsService;

    private CmsWorkflowServiceInterface $cmsWorkflowService;

    private ?int $draftVersion = null;

    private bool $draftSaveSucceeded = true;

    public function boot(
        ResearchPublicationCmsServiceInterface $publicationCmsService,
        CmsWorkflowServiceInterface $cmsWorkflowService,
    ): void {
        $this->publicationCmsService = $publicationCmsService;
        $this->cmsWorkflowService = $cmsWorkflowService;
    }

    /** @param array<string, mixed> $data @return array<string, mixed> */
    protected function mutateFormDataBeforeFill(array $data): array
    {
        $targetKey = $this->targetKey();
        $payload = $this->cmsWorkflowService->latestEditableDraftPayload($targetKey, $this->userId())
            ?? $this->publicationCmsService->getStoredData($targetKey)?->payload;
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
        } catch (\Throwable $exception) {
            report($exception);
            $this->safeFailureNotification('save_failed');
        }

        return $record;
    }

    protected function getSavedNotification(): ?Notification
    {
        return $this->draftSaveSucceeded
            ? Notification::make()->success()->title(__('admin.research_publication.notifications.draft_saved'))
            : null;
    }

    /** @return array<int, Action> */
    protected function getHeaderActions(): array
    {
        return [
            Action::make('save_draft')
                ->label(__('admin.research_publication.actions.save_draft'))
                ->icon('heroicon-o-check')
                ->color('gray')
                ->action(function (): void {
                    $this->save();
                }),
            Action::make('preview_ar')
                ->label(__('admin.research_publication.actions.preview_ar'))
                ->icon('heroicon-o-eye')
                ->color('info')
                ->action(fn (): mixed => $this->previewPublication('ar')),
            Action::make('preview_en')
                ->label(__('admin.research_publication.actions.preview_en'))
                ->icon('heroicon-o-eye')
                ->color('info')
                ->action(fn (): mixed => $this->previewPublication('en')),
            Action::make('publish')
                ->label(__('admin.research_publication.actions.publish'))
                ->icon('heroicon-o-paper-airplane')
                ->color('success')
                ->requiresConfirmation()
                ->visible(fn (): bool => Gate::allows('research_publications.publish'))
                ->action(function (): void {
                    try {
                        $this->saveDraft($this->form->getState());
                        $this->cmsWorkflowService->publish($this->targetKey(), $this->userId());
                        $this->draftVersion = null;
                        $this->record->refresh();
                        Notification::make()->title(__('admin.research_publication.notifications.published'))->success()->send();
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
                ->label(__('admin.research_publication.actions.schedule'))
                ->icon('heroicon-o-clock')
                ->color('warning')
                ->visible(fn (): bool => Gate::allows('research_publications.publish'))
                ->form([
                    DateTimePicker::make('publish_at')
                        ->label(__('admin.research_publication.fields.publish_at'))
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
                        Notification::make()->title(__('admin.research_publication.notifications.scheduled'))->success()->send();
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
                ->label(__('admin.research_publication.actions.unpublish'))
                ->icon('heroicon-o-x-circle')
                ->color('danger')
                ->requiresConfirmation()
                ->visible(fn (): bool => Gate::allows('research_publications.publish'))
                ->action(function (): void {
                    try {
                        $unpublished = $this->cmsWorkflowService->unpublish($this->targetKey(), $this->userId());
                        $this->record->refresh();
                        Notification::make()
                            ->title(__($unpublished
                                ? 'admin.research_publication.notifications.unpublished'
                                : 'admin.research_publication.notifications.not_published'))
                            ->status($unpublished ? 'success' : 'warning')
                            ->send();
                    } catch (\Throwable $exception) {
                        report($exception);
                        $this->safeFailureNotification('unpublish_failed');
                    }
                }),
            Action::make('delete_publication')
                ->label(__('admin.research_publication.actions.delete'))
                ->icon('heroicon-o-trash')
                ->color('danger')
                ->requiresConfirmation()
                ->modalDescription(__('admin.research_publication.help.delete'))
                ->visible(fn (): bool => Gate::allows('delete', $this->record))
                ->action(function (): mixed {
                    try {
                        $this->publicationCmsService->deletePublication($this->targetKey(), $this->userId());
                        Notification::make()->title(__('admin.research_publication.notifications.deleted'))->success()->send();

                        return $this->redirect(ResearchPublicationResource::getUrl('index'));
                    } catch (\Throwable $exception) {
                        report($exception);
                        $this->safeFailureNotification('delete_failed');
                    }

                    return null;
                }),
        ];
    }

    /** @param array<string, mixed> $data */
    private function saveDraft(array $data): CmsDraftDTO
    {
        $prepared = $this->publicationCmsService->prepareDraft(
            new ResearchPublicationCmsDataDTO((int) $this->record->getKey(), $data),
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

    private function previewPublication(string $locale): mixed
    {
        if (! in_array($locale, ['ar', 'en'], true)) {
            return null;
        }

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
        return 'entity.research-publication.'.$this->record->getKey();
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
        $translations = is_array($payload['translations'] ?? null) ? $payload['translations'] : [];
        $payload['translations'] = array_values(array_map(
            fn (string $locale): array => is_array($translations[$locale] ?? null) ? $translations[$locale] : ['locale' => $locale],
            ['ar', 'en'],
        ));
        unset($payload['entity_id'], $payload['updated_by'], $payload['is_legacy'], $payload['legacy_image_path'], $payload['slug']);

        return $payload;
    }

    private function conflictNotification(): void
    {
        Notification::make()
            ->title(__('admin.research_publication.notifications.conflict'))
            ->body(__('admin.research_publication.notifications.conflict_description'))
            ->danger()
            ->persistent()
            ->send();
    }

    private function validationNotification(string $key, ValidationException $exception): void
    {
        Notification::make()
            ->title(__('admin.research_publication.notifications.'.$key))
            ->body(collect($exception->errors())->flatten()->implode(' '))
            ->danger()
            ->persistent()
            ->send();
    }

    private function safeFailureNotification(string $key): void
    {
        Notification::make()
            ->title(__('admin.research_publication.notifications.'.$key))
            ->body(__('admin.research_publication.notifications.safe_error'))
            ->danger()
            ->send();
    }
}
