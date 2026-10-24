<?php

declare(strict_types=1);

namespace App\Filament\Resources\DirectorateResource\Pages;

use App\Contracts\Cms\AboutEntityCmsServiceInterface;
use App\Contracts\Cms\CmsWorkflowServiceInterface;
use App\DTOs\Cms\AboutEntityCmsDataDTO;
use App\Filament\Resources\DirectorateResource;
use App\Models\Content\Directorate;
use App\Models\User\User;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateDirectorate extends CreateRecord
{
    protected static string $resource = DirectorateResource::class;

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
        $prepared = $this->aboutEntityCmsService->prepareDraft(new AboutEntityCmsDataDTO('directorate', null, $data), (int) $user->getKey());
        $this->cmsWorkflowService->saveDraft(
            $prepared->targetKey ?? throw new \RuntimeException('Directorate draft target was not created.'),
            $prepared->payload,
            (int) $user->getKey(),
        );

        return Directorate::query()->findOrFail($prepared->entityId);
    }
}
