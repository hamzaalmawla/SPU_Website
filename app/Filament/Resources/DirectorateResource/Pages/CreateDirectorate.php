<?php

declare(strict_types=1);

namespace App\Filament\Resources\DirectorateResource\Pages;

use App\Contracts\Cms\AboutEntityCmsServiceInterface;
use App\Contracts\Cms\CmsWorkflowServiceInterface;
use App\DTOs\Cms\AboutEntityCmsDataDTO;
use App\Filament\Resources\DirectorateResource;
use App\Models\Content\Directorate;
use App\Models\User\User;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

/**
 * A new directorate is published as soon as it is saved, so it shows on the
 * central Directorates page straight away. An editor without the publish
 * permission still gets a draft, and a draft that fails the publish checks
 * (a bad email, for example) is kept with the reason shown.
 */
class CreateDirectorate extends CreateRecord
{
    protected static string $resource = DirectorateResource::class;

    private AboutEntityCmsServiceInterface $aboutEntityCmsService;

    private CmsWorkflowServiceInterface $cmsWorkflowService;

    private bool $published = false;

    public function boot(AboutEntityCmsServiceInterface $aboutEntityCmsService, CmsWorkflowServiceInterface $cmsWorkflowService): void
    {
        $this->aboutEntityCmsService = $aboutEntityCmsService;
        $this->cmsWorkflowService = $cmsWorkflowService;
    }

    protected function handleRecordCreation(array $data): Model
    {
        $user = auth()->user();
        abort_unless($user instanceof User, 403);
        $prepared = $this->aboutEntityCmsService->prepareDraft(new AboutEntityCmsDataDTO('directorate', null, $data), (int) $user->getKey());
        $targetKey = $prepared->targetKey ?? throw new \RuntimeException('Directorate draft target was not created.');
        $this->cmsWorkflowService->saveDraft($targetKey, $prepared->payload, (int) $user->getKey());

        if (Gate::allows('directorates.publish')) {
            try {
                $this->published = $this->cmsWorkflowService->publish($targetKey, (int) $user->getKey());
            } catch (ValidationException $exception) {
                Notification::make()
                    ->title(__('admin_pages.about_entity.publish_failed'))
                    ->body(collect($exception->errors())->flatten()->implode(' '))
                    ->danger()
                    ->persistent()
                    ->send();
            }
        }

        return Directorate::query()->findOrFail($prepared->entityId);
    }

    protected function getCreatedNotificationTitle(): ?string
    {
        return $this->published ? __('admin_pages.about_entity.entity_published') : parent::getCreatedNotificationTitle();
    }
}
