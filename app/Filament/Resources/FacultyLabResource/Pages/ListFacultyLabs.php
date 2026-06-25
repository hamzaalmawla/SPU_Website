<?php

declare(strict_types=1);

namespace App\Filament\Resources\FacultyLabResource\Pages;

use App\Filament\Resources\FacultyLabResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListFacultyLabs extends ListRecords
{
    protected static string $resource = FacultyLabResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\CreateAction::make()];
    }
}
