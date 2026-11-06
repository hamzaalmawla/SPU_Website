<?php

declare(strict_types=1);

namespace App\Filament\Resources\FacultyMemberResource\Pages;

use App\Contracts\Content\PersonIdentityServiceInterface;
use App\Filament\Resources\Concerns\InteractsWithAboutEntityCmsWorkflow;
use App\Filament\Resources\FacultyMemberResource;
use App\Filament\Resources\PersonResource;
use Filament\Actions\Action;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Facades\Gate;

class EditFacultyMember extends EditRecord
{
    use InteractsWithAboutEntityCmsWorkflow {
        getHeaderActions as cmsHeaderActions;
    }

    protected static string $resource = FacultyMemberResource::class;

    protected function entityType(): string
    {
        return 'faculty-member';
    }

    /** @param array<string, mixed> $data @return array<string, mixed> */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        return FacultyMemberResource::prepareFacultyMemberFormData($data, (int) $this->record->getKey());
    }

    /** @return array<int, Action> */
    protected function getHeaderActions(): array
    {
        // This profile is the person: a leadership role held by them is edited
        // under Leadership, and it shows this profile's name, photo and bio.
        $roleId = app(PersonIdentityServiceInterface::class)->personIdsForMember((int) $this->record->getKey())[0] ?? null;

        return [
            ...$this->cmsHeaderActions(),
            Action::make('leadershipRole')
                ->label(__('admin_resources.faculty_member.leadership_role'))
                ->icon('heroicon-o-identification')
                ->color('gray')
                ->visible($roleId !== null && Gate::allows('people.view'))
                ->url($roleId !== null ? PersonResource::getUrl('edit', ['record' => $roleId]) : null),
        ];
    }
}
