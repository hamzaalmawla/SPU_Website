<?php

declare(strict_types=1);

namespace App\Filament\Resources\UserResource\Pages;

use App\Contracts\Auth\AuthServiceInterface;
use App\Filament\Resources\UserResource;
use App\Models\User\User;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;

class EditUser extends EditRecord
{
    protected static string $resource = UserResource::class;

    private AuthServiceInterface $authService;

    public function boot(AuthServiceInterface $authService): void
    {
        $this->authService = $authService;
    }

    protected function getHeaderActions(): array
    {
        return [];
    }

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        /** @var User $actor */
        $actor = auth()->user();

        $this->authService->updateUser((int) $record->getKey(), $data, (int) $actor->getKey());

        Notification::make()
            ->title('User updated successfully')
            ->success()
            ->send();

        return $record->refresh();
    }
}
