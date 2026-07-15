<?php

declare(strict_types=1);

namespace App\Filament\Resources\FacultyMemberResource\Pages;

use App\Contracts\Content\ProfileAdminServiceInterface;
use App\Filament\Resources\FacultyMemberResource;
use App\Filament\Support\ProfileFormDataMapper;
use App\Models\User\User;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;

class EditFacultyMember extends EditRecord
{
    protected static string $resource = FacultyMemberResource::class;

    private ProfileAdminServiceInterface $profileAdminService;

    public function boot(ProfileAdminServiceInterface $profileAdminService): void
    {
        $this->profileAdminService = $profileAdminService;
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        Gate::authorize('update', $this->record);

        $profile = $this->profileAdminService->getFacultyMemberData((int) $this->record->getKey());

        return $profile !== null ? ProfileFormDataMapper::facultyMemberToArray($profile) : $data;
    }

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        Gate::authorize('update', $record);

        $user = auth()->user();
        abort_unless($user instanceof User, 403);

        $this->profileAdminService->updateFacultyMember(
            (int) $record->getKey(),
            ProfileFormDataMapper::facultyMemberFromArray($data, (int) $record->getKey()),
            (int) $user->getKey(),
        );

        return $record->refresh();
    }
}
