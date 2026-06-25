<?php

declare(strict_types=1);

namespace App\Filament\Resources\FacultyLabResource\Pages;

use App\Contracts\Page\FacultyAdminWorkflowServiceInterface;
use App\Filament\Resources\FacultyLabResource;
use Filament\Resources\Pages\CreateRecord;

class CreateFacultyLab extends CreateRecord
{
    protected static string $resource = FacultyLabResource::class;

    protected function afterCreate(): void
    {
        $this->facultyWorkflow()->recordFacultyLabCreated((int) $this->record->getKey(), auth()->id());
    }

    private function facultyWorkflow(): FacultyAdminWorkflowServiceInterface
    {
        return app(FacultyAdminWorkflowServiceInterface::class);
    }
}
