<?php

declare(strict_types=1);

namespace App\Filament\Resources\AlumniResource\Pages;

use App\Contracts\Page\FacultyAdminWorkflowServiceInterface;
use App\Filament\Resources\AlumniResource;
use Filament\Resources\Pages\CreateRecord;

class CreateAlumni extends CreateRecord
{
    protected static string $resource = AlumniResource::class;

    protected function afterCreate(): void
    {
        $this->facultyWorkflow()->recordAlumniCreated((int) $this->record->getKey(), auth()->id());
    }

    private function facultyWorkflow(): FacultyAdminWorkflowServiceInterface
    {
        return app(FacultyAdminWorkflowServiceInterface::class);
    }
}
