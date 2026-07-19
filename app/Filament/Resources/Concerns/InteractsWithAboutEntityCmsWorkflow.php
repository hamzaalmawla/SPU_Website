<?php

declare(strict_types=1);

namespace App\Filament\Resources\Concerns;

use App\Contracts\Cms\AboutEntityCmsServiceInterface;
use App\Contracts\Cms\CmsWorkflowServiceInterface;
use App\DTOs\Cms\AboutEntityCmsDataDTO;
use App\DTOs\Cms\CmsDraftDTO;
use App\Models\User\User;
use Filament\Actions\Action;
use Filament\Forms\Components\DateTimePicker;
use Filament\Notifications\Notification;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

trait InteractsWithAboutEntityCmsWorkflow
{
    private AboutEntityCmsServiceInterface $aboutEntityCmsService;

    private CmsWorkflowServiceInterface $cmsWorkflowService;

    private ?int $draftVersion = null;

    abstract protected function entityType(): string;

    public function boot(
        AboutEntityCmsServiceInterface $aboutEntityCmsService,
        CmsWorkflowServiceInterface $cmsWorkflowService,
    ): void {
        $this->aboutEntityCmsService = $aboutEntityCmsService;
        $this->cmsWorkflowService = $cmsWorkflowService;
    }

    /** @param array<string, mixed> $data @return array<string, mixed> */
    protected function mutateFormDataBeforeFill(array $data): array
    {
        $targetKey = $this->targetKey();
        $payload = $this->cmsWorkflowService->latestEditableDraftPayload($targetKey, (int) auth()->id())
            ?? $this->aboutEntityCmsService->getStoredData($targetKey)?->payload;
        $this->draftVersion = $this->cmsWorkflowService->latestEditableDraftVersion($targetKey, (int) auth()->id());

        return is_array($payload) ? $this->payloadToFormData($payload) : $data;
    }

    /** @param array<string, mixed> $data */
    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        $this->saveDraft($data);

        return $record;
    }

    /** @return array<int, Action> */
    protected function getHeaderActions(): array
    {
        return [
            Action::make('preview_ar')
                ->label('Preview AR')
                ->icon('heroicon-o-eye')
                ->color('info')
                ->action(fn (): mixed => $this->previewEntity('ar')),
            Action::make('preview_en')
                ->label('Preview EN')
                ->icon('heroicon-o-eye')
                ->color('info')
                ->action(fn (): mixed => $this->previewEntity('en')),
            Action::make('publish')
                ->label('Publish')
                ->icon('heroicon-o-paper-airplane')
                ->color('success')
                ->requiresConfirmation()
                ->visible(fn (): bool => Gate::allows('publish-content'))
                ->action(function (): void {
                    try {
                        $this->saveDraft($this->form->getState());
                        $this->cmsWorkflowService->publish($this->targetKey(), $this->userId());
                        $this->draftVersion = null;
                        Notification::make()->title('Entity published')->success()->send();
                    } catch (ValidationException $exception) {
                        Notification::make()->title('Publish failed')->body($this->validationMessage($exception))->danger()->persistent()->send();
                    }
                }),
            Action::make('schedule')
                ->label('Schedule')
                ->icon('heroicon-o-clock')
                ->color('warning')
                ->visible(fn (): bool => Gate::allows('publish-content'))
                ->form([
                    DateTimePicker::make('publish_at')->required()->minDate(now())->native(false),
                ])
                ->action(function (array $data): void {
                    try {
                        $this->saveDraft($this->form->getState());
                        $this->cmsWorkflowService->schedule(
                            $this->targetKey(),
                            new \DateTimeImmutable((string) $data['publish_at']),
                            $this->userId(),
                        );
                        Notification::make()->title('Entity scheduled')->success()->send();
                    } catch (ValidationException $exception) {
                        Notification::make()->title('Schedule failed')->body($this->validationMessage($exception))->danger()->persistent()->send();
                    }
                }),
            Action::make('unpublish')
                ->label('Unpublish')
                ->icon('heroicon-o-x-circle')
                ->color('danger')
                ->requiresConfirmation()
                ->visible(fn (): bool => Gate::allows('publish-content'))
                ->action(function (): void {
                    $unpublished = $this->cmsWorkflowService->unpublish($this->targetKey(), $this->userId());
                    $notification = Notification::make()->title($unpublished ? 'Entity unpublished' : 'Entity was not published');
                    ($unpublished ? $notification->success() : $notification->warning())->send();
                }),
        ];
    }

    /** @param array<string, mixed> $data */
    private function saveDraft(array $data): CmsDraftDTO
    {
        $prepared = $this->aboutEntityCmsService->prepareDraft(
            new AboutEntityCmsDataDTO($this->entityType(), (int) $this->record->getKey(), $data),
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

    private function previewEntity(string $locale): mixed
    {
        $this->saveDraft($this->form->getState());
        $preview = $this->cmsWorkflowService->preview($this->targetKey(), $locale, $this->userId());

        return $this->redirect($preview->previewUrl);
    }

    private function targetKey(): string
    {
        return 'entity.'.$this->entityType().'.'.$this->record->getKey();
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
        unset($payload['entity_type'], $payload['entity_id']);
        if (is_array($payload['translations'] ?? null)) {
            $payload['translations'] = array_values($payload['translations']);
        }
        if (in_array($this->entityType(), ['person', 'faculty-member'], true) && is_array($payload['educations'] ?? null)) {
            $payload['educations'] = array_values(array_map(function (mixed $education): mixed {
                if (is_array($education) && is_array($education['translations'] ?? null)) {
                    $education['translations'] = array_values($education['translations']);
                }

                return $education;
            }, $payload['educations']));
        }

        return $payload;
    }

    private function validationMessage(ValidationException $exception): string
    {
        return collect($exception->errors())->flatten()->implode(' ');
    }
}
