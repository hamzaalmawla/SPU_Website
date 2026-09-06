<?php

declare(strict_types=1);

namespace App\Filament\Resources\UserResource\Pages;

use App\Contracts\Auth\AuthServiceInterface;
use App\Filament\Resources\UserResource;
use App\Models\User\User;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateUser extends CreateRecord
{
    protected static string $resource = UserResource::class;

    private AuthServiceInterface $authService;

    public function boot(AuthServiceInterface $authService): void
    {
        $this->authService = $authService;
    }

    protected function handleRecordCreation(array $data): Model
    {
        /** @var User $actor */
        $actor = auth()->user();
        abort_unless($actor instanceof User, 403);

        $userId = $this->authService->createUser($data, (int) $actor->getKey());

        if ($userId === null) {
            throw new \RuntimeException('The user could not be created. Check the email and role, then try again.');
        }

        // Filament requires a Model back from this hook, so the record is
        // rehydrated by the id the service returned. Looking it up by id rather
        // than by re-deriving the email keeps the service the only thing that
        // decides which row was written, and matches the rehydration pattern
        // the other Create pages use.
        return User::query()->findOrFail($userId);
    }
}
