<?php

declare(strict_types=1);

namespace App\Filament\Resources\ResearchPublicationResource\Pages;

use App\Contracts\Cms\CmsWorkflowServiceInterface;
use App\Contracts\Research\ResearchPublicationCmsServiceInterface;
use App\DTOs\Research\ResearchPublicationCmsDataDTO;
use App\Filament\Resources\ResearchPublicationResource;
use App\Models\Research\ResearchPublication;
use App\Models\User\User;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateResearchPublication extends CreateRecord
{
    protected static string $resource = ResearchPublicationResource::class;

    private ResearchPublicationCmsServiceInterface $publicationCmsService;

    private CmsWorkflowServiceInterface $cmsWorkflowService;

    public function boot(
        ResearchPublicationCmsServiceInterface $publicationCmsService,
        CmsWorkflowServiceInterface $cmsWorkflowService,
    ): void {
        $this->publicationCmsService = $publicationCmsService;
        $this->cmsWorkflowService = $cmsWorkflowService;
    }

    /** @param array<string, mixed> $data */
    protected function handleRecordCreation(array $data): Model
    {
        $user = auth()->user();
        abort_unless($user instanceof User, 403);

        $prepared = $this->publicationCmsService->prepareDraft(
            new ResearchPublicationCmsDataDTO(null, $data),
            (int) $user->getKey(),
        );
        $this->cmsWorkflowService->saveDraft(
            $prepared->targetKey ?? throw new \RuntimeException('Research publication draft target was not created.'),
            $prepared->payload,
            (int) $user->getKey(),
        );

        return ResearchPublication::query()->findOrFail($prepared->publicationId);
    }

    protected function getCreatedNotificationTitle(): ?string
    {
        return __('admin.research_publication.notifications.draft_saved');
    }
}
