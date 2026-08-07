<?php

declare(strict_types=1);

namespace App\Filament\Resources\FacultyMemberResource\Pages;

use App\Contracts\Cms\AboutEntityCmsServiceInterface;
use App\Contracts\Cms\CmsWorkflowServiceInterface;
use App\DTOs\Cms\AboutEntityCmsDataDTO;
use App\Filament\Resources\FacultyMemberResource;
use App\Models\Person\FacultyMember;
use App\Models\User\User;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateFacultyMember extends CreateRecord
{
    protected static string $resource = FacultyMemberResource::class;

    private AboutEntityCmsServiceInterface $aboutEntityCmsService;

    private CmsWorkflowServiceInterface $cmsWorkflowService;

    public function boot(AboutEntityCmsServiceInterface $aboutEntityCmsService, CmsWorkflowServiceInterface $cmsWorkflowService): void
    {
        $this->aboutEntityCmsService = $aboutEntityCmsService;
        $this->cmsWorkflowService = $cmsWorkflowService;
    }

    /** @param array<string, mixed> $data @return array<string, mixed> */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        return FacultyMemberResource::prepareFacultyMemberFormData($data);
    }

    protected function handleRecordCreation(array $data): Model
    {
        $user = auth()->user();
        abort_unless($user instanceof User, 403);

        $prepared = $this->aboutEntityCmsService->prepareDraft(
            new AboutEntityCmsDataDTO('faculty-member', null, $data),
            (int) $user->getKey(),
        );
        $this->cmsWorkflowService->saveDraft(
            $prepared->targetKey ?? throw new \RuntimeException('Faculty member draft target was not created.'),
            $prepared->payload,
            (int) $user->getKey(),
        );

        return FacultyMember::query()->findOrFail($prepared->entityId);
    }
}
