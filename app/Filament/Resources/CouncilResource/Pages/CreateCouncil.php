<?php

declare(strict_types=1);

namespace App\Filament\Resources\CouncilResource\Pages;

use App\Contracts\Council\CouncilAdminServiceInterface;
use App\Filament\Resources\CouncilResource;
use App\Models\Content\UniversityCouncil;
use App\Models\User\User;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

/**
 * A new council: its row, and a first draft holding its name and the default
 * headings. Members and functions are added on the edit screen it opens next.
 */
class CreateCouncil extends CreateRecord
{
    protected static string $resource = CouncilResource::class;

    private CouncilAdminServiceInterface $councilAdmin;

    public function boot(CouncilAdminServiceInterface $councilAdmin): void
    {
        $this->councilAdmin = $councilAdmin;
    }

    /** @param array<string, mixed> $data */
    protected function handleRecordCreation(array $data): Model
    {
        $user = auth()->user();
        abort_unless($user instanceof User, 403);

        $id = $this->councilAdmin->createCouncil(
            (string) $data['slug'],
            (int) ($data['sort_order'] ?? 0),
            (bool) ($data['is_enabled'] ?? true),
            (string) ($data['title_ar'] ?? ''),
            (string) ($data['title_en'] ?? ''),
            (int) $user->getKey(),
        );

        return UniversityCouncil::query()->findOrFail($id);
    }

    protected function getRedirectUrl(): string
    {
        return static::getResource()::getUrl('edit', ['record' => $this->getRecord()]);
    }
}
