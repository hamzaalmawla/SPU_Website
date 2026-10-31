<?php

declare(strict_types=1);

namespace App\Filament\Resources\ResearcherResource\Pages;

use App\Contracts\Research\ResearcherAdminServiceInterface;
use App\Filament\Resources\ResearcherResource;
use App\Filament\Resources\ResearcherResource\ResearcherFormData;
use App\Models\User\User;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;

class EditResearcher extends EditRecord
{
    protected static string $resource = ResearcherResource::class;

    /** @param array<string, mixed> $data */
    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        $user = auth()->user();
        abort_unless($user instanceof User, 403);

        app(ResearcherAdminServiceInterface::class)->update((int) $record->getKey(), ResearcherFormData::toDto($data), (int) $user->getKey());

        return $record->refresh();
    }
}
