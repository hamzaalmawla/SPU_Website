<?php

declare(strict_types=1);

namespace App\Filament\Resources\PersonResource\Pages;

use App\Contracts\Content\ProfileAdminServiceInterface;
use App\Filament\Resources\PersonResource;
use App\Filament\Support\ProfileFormDataMapper;
use App\Models\User\User;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;

class EditPerson extends EditRecord
{
    protected static string $resource = PersonResource::class;

    private ProfileAdminServiceInterface $profileAdminService;

    public function boot(ProfileAdminServiceInterface $profileAdminService): void
    {
        $this->profileAdminService = $profileAdminService;
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        Gate::authorize('manage-pages');

        $profile = $this->profileAdminService->getPersonData((int) $this->record->getKey());

        return $profile !== null ? ProfileFormDataMapper::personToArray($profile) : $data;
    }

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        Gate::authorize('manage-pages');

        $user = auth()->user();
        abort_unless($user instanceof User, 403);

        $this->profileAdminService->updatePerson(
            (int) $record->getKey(),
            ProfileFormDataMapper::personFromArray($data, (int) $record->getKey()),
            (int) $user->getKey(),
        );

        return $record->refresh();
    }
}
