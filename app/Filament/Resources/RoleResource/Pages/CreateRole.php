<?php

declare(strict_types=1);

namespace App\Filament\Resources\RoleResource\Pages;

use App\Contracts\Auth\RoleAdminServiceInterface;
use App\Filament\Resources\RoleResource;
use App\Models\User\Role;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateRole extends CreateRecord
{
    protected static string $resource = RoleResource::class;

    private RoleAdminServiceInterface $roles;

    public function boot(RoleAdminServiceInterface $roles): void
    {
        $this->roles = $roles;
    }

    protected function handleRecordCreation(array $data): Model
    {
        $id = $this->roles->saveRole(
            null,
            (string) ($data['name'] ?? ''),
            (string) ($data['slug'] ?? ''),
            RoleResource::permissionsFromForm($data),
            (int) auth()->id(),
        );

        // Filament needs a Model back; the service decided which row was written.
        return Role::query()->findOrFail($id);
    }
}
