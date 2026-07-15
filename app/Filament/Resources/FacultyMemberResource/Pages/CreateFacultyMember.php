<?php

declare(strict_types=1);

namespace App\Filament\Resources\FacultyMemberResource\Pages;

use App\Contracts\Content\ProfileAdminServiceInterface;
use App\Filament\Resources\FacultyMemberResource;
use App\Filament\Support\ProfileFormDataMapper;
use App\Models\Person\FacultyMember;
use App\Models\User\User;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;

class CreateFacultyMember extends CreateRecord
{
    protected static string $resource = FacultyMemberResource::class;

    private ProfileAdminServiceInterface $profileAdminService;

    public function boot(ProfileAdminServiceInterface $profileAdminService): void
    {
        $this->profileAdminService = $profileAdminService;
    }

    protected function handleRecordCreation(array $data): Model
    {
        Gate::authorize('create', FacultyMember::class);

        $user = auth()->user();
        abort_unless($user instanceof User, 403);

        $created = $this->profileAdminService->createFacultyMember(
            ProfileFormDataMapper::facultyMemberFromArray($data),
            (int) $user->getKey(),
        );

        return FacultyMember::query()->findOrFail($created->id);
    }
}
