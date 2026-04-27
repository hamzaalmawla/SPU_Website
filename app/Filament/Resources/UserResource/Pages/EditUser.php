<?php

declare(strict_types=1);

namespace App\Filament\Resources\UserResource\Pages;

use App\Contracts\AuditServiceInterface;
use App\Filament\Resources\UserResource;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;

class EditUser extends EditRecord
{
    protected static string $resource = UserResource::class;

    private AuditServiceInterface $auditService;

    public function boot(AuditServiceInterface $auditService): void
    {
        $this->auditService = $auditService;
    }

    protected function getHeaderActions(): array
    {
        return [];
    }

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        /** @var \App\Models\User $record */
        $wasLocked = (bool) $record->is_locked;
        $newLocked = (bool) ($data['is_locked'] ?? false);

        $record->update($data);

        /** @var \App\Models\User $actor */
        $actor = auth()->user();

        if ($wasLocked !== $newLocked) {
            $this->auditService->log(
                action: $newLocked ? 'user.locked' : 'user.unlocked',
                userId: (int) $actor->getKey(),
                entityType: 'user',
                entityId: (int) $record->getKey(),
                metadata: [
                    'actor_email' => $actor->email,
                    'target_email' => $record->email,
                ],
            );
        }

        $this->auditService->log(
            action: 'user.updated',
            userId: (int) $actor->getKey(),
            entityType: 'user',
            entityId: (int) $record->getKey(),
            metadata: [
                'actor_email' => $actor->email,
                'target_email' => $record->email,
                'changed_fields' => array_keys($record->getChanges()),
            ],
        );

        Notification::make()
            ->title('User updated successfully')
            ->success()
            ->send();

        return $record->refresh();
    }
}
