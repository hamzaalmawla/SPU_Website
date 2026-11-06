<?php

declare(strict_types=1);

namespace App\Filament\Resources\RoleResource\Pages;

use App\Contracts\Auth\RoleAdminServiceInterface;
use App\Filament\Resources\RoleResource;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;

class EditRole extends EditRecord
{
    protected static string $resource = RoleResource::class;

    private RoleAdminServiceInterface $roles;

    public function boot(RoleAdminServiceInterface $roles): void
    {
        $this->roles = $roles;
    }

    protected function getHeaderActions(): array
    {
        return [];
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        return [
            ...$data,
            ...RoleResource::permissionsToForm($this->roles->permissionsForRole((int) $this->record->getKey())),
        ];
    }

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        $this->roles->saveRole(
            (int) $record->getKey(),
            (string) ($data['name'] ?? ''),
            (string) $record->getAttribute('slug'),
            RoleResource::permissionsFromForm($data),
            (int) auth()->id(),
        );

        Notification::make()->title(__('admin_permissions.role.saved'))->success()->send();

        return $record->refresh();
    }
}
