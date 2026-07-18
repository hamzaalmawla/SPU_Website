<?php

declare(strict_types=1);

namespace App\Filament\Resources\FacultyMemberResource\Pages;

use App\Filament\Resources\Concerns\InteractsWithAboutEntityCmsWorkflow;
use App\Filament\Resources\FacultyMemberResource;
use Filament\Resources\Pages\EditRecord;

class EditFacultyMember extends EditRecord
{
    use InteractsWithAboutEntityCmsWorkflow;

    protected static string $resource = FacultyMemberResource::class;

    protected function entityType(): string
    {
        return 'faculty-member';
    }
}
