<?php

declare(strict_types=1);

namespace App\Filament\Resources\FacultyMemberResource\Pages;

use App\Contracts\Cms\AboutEntityCmsServiceInterface;
use App\Contracts\Cms\CmsWorkflowServiceInterface;
use App\Contracts\Content\ProfileAdminServiceInterface;
use App\DTOs\Cms\AboutEntityCmsDataDTO;
use App\Filament\Resources\FacultyMemberResource;
use App\Models\Person\FacultyMember;
use App\Models\User\User;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Model;
use Livewire\Attributes\Url;

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

    /** Set when the form is opened from a faculty page; the new member joins that faculty. */
    #[Url(as: 'faculty_scope')]
    public ?string $facultyScope = null;

    public function getTitle(): string|Htmlable
    {
        $facultyId = FacultyMemberResource::contextFacultyId($this);
        $facultyName = $facultyId !== null
            ? app(ProfileAdminServiceInterface::class)->facultyOptions((int) auth()->id())[$facultyId] ?? null
            : null;

        return $facultyName !== null
            ? __('admin_resources.faculty_member.add_member').' - '.$facultyName
            : parent::getTitle();
    }

    /** @param array<string, mixed> $data @return array<string, mixed> */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $facultyId = FacultyMemberResource::contextFacultyId($this);
        if ($facultyId !== null) {
            $data['faculty_id'] = $facultyId;
        }

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

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('edit', ['record' => $this->record]);
    }
}
