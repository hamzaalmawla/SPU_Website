<?php

declare(strict_types=1);

namespace App\Filament\Resources\ResearcherResource\Pages;

use App\Contracts\Research\ResearcherAdminServiceInterface;
use App\Filament\Resources\ResearcherResource;
use App\Filament\Resources\ResearcherResource\ResearcherFormData;
use App\Models\Research\Researcher;
use App\Models\User\User;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateResearcher extends CreateRecord
{
    protected static string $resource = ResearcherResource::class;

    /** @param array<string, mixed> $data */
    protected function handleRecordCreation(array $data): Model
    {
        $user = auth()->user();
        abort_unless($user instanceof User, 403);

        $id = app(ResearcherAdminServiceInterface::class)->create(ResearcherFormData::toDto($data), (int) $user->getKey());

        return Researcher::query()->findOrFail($id);
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('edit', ['record' => $this->record]);
    }
}
