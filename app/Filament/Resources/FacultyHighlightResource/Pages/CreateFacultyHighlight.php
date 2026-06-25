<?php

declare(strict_types=1);

namespace App\Filament\Resources\FacultyHighlightResource\Pages;

use App\Contracts\Page\FacultyAdminWorkflowServiceInterface;
use App\Filament\Resources\FacultyHighlightResource;
use Filament\Resources\Pages\CreateRecord;

class CreateFacultyHighlight extends CreateRecord
{
    protected static string $resource = FacultyHighlightResource::class;

    protected function afterCreate(): void
    {
        $this->facultyWorkflow()->recordFacultyHighlightCreated((int) $this->record->getKey(), auth()->id());
    }

    private function facultyWorkflow(): FacultyAdminWorkflowServiceInterface
    {
        return app(FacultyAdminWorkflowServiceInterface::class);
    }
}
