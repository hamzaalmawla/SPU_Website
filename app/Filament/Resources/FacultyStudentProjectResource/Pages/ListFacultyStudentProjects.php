<?php

declare(strict_types=1);

namespace App\Filament\Resources\FacultyStudentProjectResource\Pages;

use App\Filament\Resources\FacultyStudentProjectResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListFacultyStudentProjects extends ListRecords
{
    protected static string $resource = FacultyStudentProjectResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\CreateAction::make()];
    }
}
