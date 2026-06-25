<?php

declare(strict_types=1);

namespace App\Filament\Resources\HonorStudentResource\Pages;

use App\Contracts\Page\FacultyAdminWorkflowServiceInterface;
use App\Filament\Resources\HonorStudentResource;
use Filament\Resources\Pages\CreateRecord;

class CreateHonorStudent extends CreateRecord
{
    protected static string $resource = HonorStudentResource::class;

    protected function afterCreate(): void
    {
        $this->facultyWorkflow()->recordHonorStudentCreated((int) $this->record->getKey(), auth()->id());
    }

    private function facultyWorkflow(): FacultyAdminWorkflowServiceInterface
    {
        return app(FacultyAdminWorkflowServiceInterface::class);
    }
}
