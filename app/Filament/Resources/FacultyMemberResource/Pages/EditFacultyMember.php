<?php

declare(strict_types=1);

namespace App\Filament\Resources\FacultyMemberResource\Pages;

use App\Filament\Resources\FacultyMemberResource;
use Filament\Resources\Pages\EditRecord;

class EditFacultyMember extends EditRecord
{
    protected static string $resource = FacultyMemberResource::class;
}
