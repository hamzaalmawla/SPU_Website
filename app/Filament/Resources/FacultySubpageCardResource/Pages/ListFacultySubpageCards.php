<?php

declare(strict_types=1);

namespace App\Filament\Resources\FacultySubpageCardResource\Pages;

use App\Filament\Resources\FacultySubpageCardResource;
use Filament\Resources\Pages\ListRecords;

class ListFacultySubpageCards extends ListRecords
{
    protected static string $resource = FacultySubpageCardResource::class;
}
