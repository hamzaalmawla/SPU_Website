<?php

declare(strict_types=1);

namespace App\Filament\Resources\FacultyMemberResource\Pages;

use App\Filament\Resources\FacultyMemberResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListFacultyMembers extends ListRecords
{
    protected static string $resource = FacultyMemberResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\CreateAction::make()];
    }
}
