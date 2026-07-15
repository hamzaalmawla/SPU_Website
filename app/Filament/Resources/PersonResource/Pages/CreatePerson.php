<?php

declare(strict_types=1);

namespace App\Filament\Resources\PersonResource\Pages;

use App\Contracts\Content\ProfileAdminServiceInterface;
use App\Filament\Resources\PersonResource;
use App\Filament\Support\ProfileFormDataMapper;
use App\Models\Person\Person;
use App\Models\User\User;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;

class CreatePerson extends CreateRecord
{
    protected static string $resource = PersonResource::class;

    private ProfileAdminServiceInterface $profileAdminService;

    public function boot(ProfileAdminServiceInterface $profileAdminService): void
    {
        $this->profileAdminService = $profileAdminService;
    }

    protected function handleRecordCreation(array $data): Model
    {
        Gate::authorize('manage-pages');

        $user = auth()->user();
        abort_unless($user instanceof User, 403);

        $created = $this->profileAdminService->createPerson(
            ProfileFormDataMapper::personFromArray($data),
            (int) $user->getKey(),
        );

        return Person::query()->findOrFail($created->id);
    }
}
