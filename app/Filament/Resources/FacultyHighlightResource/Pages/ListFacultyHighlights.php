<?php

declare(strict_types=1);

namespace App\Filament\Resources\FacultyHighlightResource\Pages;

use App\Filament\Resources\FacultyHighlightResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListFacultyHighlights extends ListRecords
{
    protected static string $resource = FacultyHighlightResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\CreateAction::make()];
    }
}
