<?php

declare(strict_types=1);

namespace App\Filament\Resources\FacultyMemberResource\Pages;

use App\Filament\Resources\FacultyMemberResource;
use Filament\Resources\Pages\CreateRecord;

class CreateFacultyMember extends CreateRecord
{
    protected static string $resource = FacultyMemberResource::class;
}
