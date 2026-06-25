<?php

declare(strict_types=1);

namespace App\Filament\Resources\FacultyResource\Pages;

use App\Contracts\Page\FacultyAdminWorkflowServiceInterface;
use App\Filament\Resources\FacultyResource;
use Filament\Resources\Pages\CreateRecord;

class CreateFaculty extends CreateRecord
{
    protected static string $resource = FacultyResource::class;

    protected function afterCreate(): void
    {
        $this->facultyWorkflow()->recordFacultyCreated((int) $this->record->getKey(), auth()->id());
    }

    private function facultyWorkflow(): FacultyAdminWorkflowServiceInterface
    {
        return app(FacultyAdminWorkflowServiceInterface::class);
    }
}
