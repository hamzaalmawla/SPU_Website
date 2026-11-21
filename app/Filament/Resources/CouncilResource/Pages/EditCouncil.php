<?php

declare(strict_types=1);

namespace App\Filament\Resources\CouncilResource\Pages;

use App\Contracts\Cms\CmsWorkflowServiceInterface;
use App\Contracts\Council\CouncilCatalogServiceInterface;
use App\Exceptions\ConflictException;
use App\Filament\Resources\CouncilResource;
use App\Filament\Support\CouncilEditor;
use App\Models\Content\UniversityCouncil;
use App\Models\User\User;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

/**
 * One council: its address, order and visibility apply at once; its page
 * content (introduction, headings, functions, members) is saved as a CMS draft
 * and goes live on Publish, like every other page.
 */
class EditCouncil extends EditRecord
{
    protected static string $resource = CouncilResource::class;

    public ?int $draftVersion = null;

    /** @var array<string, mixed> The content the form was opened from, to keep keys it does not edit. */
    public array $sourcePayload = [];

    private CouncilCatalogServiceInterface $catalog;

    private CmsWorkflowServiceInterface $cmsWorkflowService;

    public function boot(CouncilCatalogServiceInterface $catalog, CmsWorkflowServiceInterface $cmsWorkflowService): void
    {
        $this->catalog = $catalog;
        $this->cmsWorkflowService = $cmsWorkflowService;
    }

    /** @param array<string, mixed> $data @return array<string, mixed> */
    protected function mutateFormDataBeforeFill(array $data): array
    {
        $targetKey = $this->targetKey();
        $payload = $this->cmsWorkflowService->latestEditableDraftPayload($targetKey, $this->userId())
            ?? $this->catalog->editorPayload((int) $this->record->getKey());
        $this->sourcePayload = $payload;
        $this->draftVersion = $this->cmsWorkflowService->latestEditableDraftVersion($targetKey, $this->userId());

        return [...$data, ...CouncilEditor::formState($payload)];
    }

    /** @param array<string, mixed> $data */
    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        $this->catalog->updateSettings((int) $record->getKey(), (int) ($data['sort_order'] ?? 0), (bool) ($data['is_enabled'] ?? false));

        try {
            $this->saveDraft($data);
        } catch (ConflictException $exception) {
            $this->conflict($exception);
            $this->halt();
        }

        return $record->refresh();
    }

    protected function getSavedNotificationTitle(): ?string
    {
        return __('admin_councils.notifications.draft_saved');
    }

    protected function getSaveFormAction(): Action
    {
        return parent::getSaveFormAction()->label(__('admin_councils.actions.save_draft'));
    }

    /** @return array<int, Action> */
    protected function getHeaderActions(): array
    {
        $canPublish = fn (): bool => Gate::allows('councils.publish');

        return [
            Action::make('view_page')
                ->label(__('admin_councils.actions.view_page'))
                ->icon('heroicon-o-arrow-top-right-on-square')
                ->color('gray')
                ->url(fn (): string => '/'.app()->getLocale().'/about/councils/'.$this->record->getAttribute('slug'), shouldOpenInNewTab: true)
                ->visible(fn (): bool => $this->catalog->isPublished((int) $this->record->getKey())),
            Action::make('preview_ar')
                ->label(__('admin_councils.actions.preview_ar'))
                ->icon('heroicon-o-eye')
                ->color('info')
                ->action(fn (): mixed => $this->preview('ar')),
            Action::make('preview_en')
                ->label(__('admin_councils.actions.preview_en'))
                ->icon('heroicon-o-eye')
                ->color('info')
                ->action(fn (): mixed => $this->preview('en')),
            Action::make('publish')
                ->label(__('admin_councils.actions.publish'))
                ->icon('heroicon-o-paper-airplane')
                ->color('success')
                ->requiresConfirmation()
                ->visible($canPublish)
                ->action(function (): void {
                    try {
                        $this->saveDraft($this->form->getState());
                        $this->cmsWorkflowService->publish($this->targetKey(), $this->userId());
                        $this->draftVersion = null;
                        Notification::make()->title(__('admin_councils.notifications.published'))->success()->send();
                    } catch (ValidationException $exception) {
                        Notification::make()->title(__('admin_councils.notifications.publish_failed'))->body($this->validationMessage($exception))->danger()->persistent()->send();
                    } catch (ConflictException $exception) {
                        $this->conflict($exception);
                    }
                }),
            Action::make('schedule')
                ->label(__('admin_councils.actions.schedule'))
                ->icon('heroicon-o-clock')
                ->color('warning')
                ->visible($canPublish)
                ->form([
                    DateTimePicker::make('publish_at')->label(__('admin_councils.actions.publish_at'))->required()->minDate(now())->native(false),
                ])
                ->action(function (array $data): void {
                    try {
                        $this->saveDraft($this->form->getState());
                        $this->cmsWorkflowService->schedule($this->targetKey(), new \DateTimeImmutable((string) $data['publish_at']), $this->userId());
                        Notification::make()->title(__('admin_councils.notifications.scheduled'))->success()->send();
                    } catch (ValidationException $exception) {
                        Notification::make()->title(__('admin_councils.notifications.schedule_failed'))->body($this->validationMessage($exception))->danger()->persistent()->send();
                    } catch (ConflictException $exception) {
                        $this->conflict($exception);
                    }
                }),
            Action::make('unpublish')
                ->label(__('admin_councils.actions.unpublish'))
                ->icon('heroicon-o-x-circle')
                ->color('danger')
                ->requiresConfirmation()
                ->visible($canPublish)
                ->action(function (): void {
                    $unpublished = $this->cmsWorkflowService->unpublish($this->targetKey(), $this->userId());
                    $notification = Notification::make()->title($unpublished ? __('admin_councils.notifications.unpublished') : __('admin_councils.notifications.not_published'));
                    ($unpublished ? $notification->success() : $notification->warning())->send();
                }),
            DeleteAction::make()
                ->using(fn (UniversityCouncil $record): bool => $this->catalog->deleteCouncil((int) $record->getKey())),
        ];
    }

    /** @param array<string, mixed> $data */
    private function saveDraft(array $data): void
    {
        $payload = CouncilEditor::payload($data, $this->sourcePayload);
        $draft = $this->cmsWorkflowService->saveDraft($this->targetKey(), $payload, $this->userId(), $this->draftVersion);
        $this->draftVersion = $draft->version;
        $this->sourcePayload = $payload;
    }

    private function preview(string $locale): mixed
    {
        try {
            $this->saveDraft($this->form->getState());
        } catch (ConflictException $exception) {
            $this->conflict($exception);

            return null;
        }

        return $this->redirect($this->cmsWorkflowService->preview($this->targetKey(), $locale, $this->userId())->previewUrl);
    }

    private function conflict(ConflictException $exception): void
    {
        $this->draftVersion = $exception->currentVersion;
        Notification::make()->title(__('admin_councils.notifications.conflict'))->body(__('admin_councils.notifications.conflict_body'))->danger()->persistent()->send();
    }

    private function targetKey(): string
    {
        return $this->catalog->targetKey((int) $this->record->getKey());
    }

    private function userId(): int
    {
        $user = auth()->user();
        abort_unless($user instanceof User, 403);

        return (int) $user->getKey();
    }

    private function validationMessage(ValidationException $exception): string
    {
        return collect($exception->errors())->flatten()->implode(' ');
    }
}
