<?php

declare(strict_types=1);

namespace App\Filament\Resources\FacultyPageResource\Pages;

use App\Filament\Resources\FacultyPageResource;
use Filament\Resources\Pages\CreateRecord;

class CreateFacultyPage extends CreateRecord
{
    protected static string $resource = FacultyPageResource::class;
}
