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

        if (! $this->authService->createUser($data, (int) $actor->getKey())) {
            throw new \RuntimeException('The user could not be created. Check the email and role, then try again.');
        }

        return User::query()
            ->where('email', strtolower(trim((string) $data['email'])))
            ->firstOrFail();
    }
}
