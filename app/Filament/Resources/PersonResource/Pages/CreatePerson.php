<?php

declare(strict_types=1);

namespace App\Filament\Resources\PersonResource\Pages;

use App\Contracts\Cms\AboutEntityCmsServiceInterface;
use App\Contracts\Cms\CmsWorkflowServiceInterface;
use App\DTOs\Cms\AboutEntityCmsDataDTO;
use App\Filament\Resources\PersonResource;
use App\Models\Person\Person;
use App\Models\User\User;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreatePerson extends CreateRecord
{
    protected static string $resource = PersonResource::class;

    private AboutEntityCmsServiceInterface $aboutEntityCmsService;

    private CmsWorkflowServiceInterface $cmsWorkflowService;

    public function boot(AboutEntityCmsServiceInterface $aboutEntityCmsService, CmsWorkflowServiceInterface $cmsWorkflowService): void
    {
        $this->aboutEntityCmsService = $aboutEntityCmsService;
        $this->cmsWorkflowService = $cmsWorkflowService;
    }

    protected function handleRecordCreation(array $data): Model
    {
        $user = auth()->user();
        abort_unless($user instanceof User, 403);

        $prepared = $this->aboutEntityCmsService->prepareDraft(
            new AboutEntityCmsDataDTO('person', null, $data),
            (int) $user->getKey(),
        );
        $this->cmsWorkflowService->saveDraft(
            $prepared->targetKey ?? throw new \RuntimeException('Person draft target was not created.'),
            $prepared->payload,
            (int) $user->getKey(),
        );

        return Person::query()->findOrFail($prepared->entityId);
    }
}
