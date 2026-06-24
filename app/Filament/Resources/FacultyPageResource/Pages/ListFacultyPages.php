<?php

declare(strict_types=1);

namespace App\Filament\Resources\FacultyPageResource\Pages;

use App\Filament\Resources\FacultyPageResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListFacultyPages extends ListRecords
{
    protected static string $resource = FacultyPageResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\CreateAction::make()];
    }
}
