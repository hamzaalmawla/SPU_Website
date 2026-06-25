<?php

declare(strict_types=1);

namespace App\Filament\Resources\AlumniResource\Pages;

use App\Contracts\Page\FacultyAdminWorkflowServiceInterface;
use App\Filament\Resources\AlumniResource;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;

class EditAlumni extends EditRecord
{
    protected static string $resource = AlumniResource::class;

    /** @var array<string, mixed> */
    private array $originalAlumniData = [];

    /** @param array<string, mixed> $data @return array<string, mixed> */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        $this->originalAlumniData = ['student_identifier' => $this->record->getAttribute('student_identifier'), 'faculty_id' => $this->record->getAttribute('faculty_id'), 'department_id' => $this->record->getAttribute('department_id'), 'degree' => $this->record->getAttribute('degree'), 'graduation_year' => $this->record->getAttribute('graduation_year'), 'is_featured' => $this->record->getAttribute('is_featured'), 'is_enabled' => $this->record->getAttribute('is_enabled')];

        return $data;
    }

    protected function afterSave(): void
    {
        $this->facultyWorkflow()->recordAlumniUpdated((int) $this->record->getKey(), auth()->id(), $this->originalAlumniData);
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make()->action(function (): void {
                $this->facultyWorkflow()->deleteAlumni((int) $this->record->getKey(), auth()->id());
                Notification::make()->title('Alumni record deleted')->success()->send();
                $this->redirect(AlumniResource::getUrl('index'));
            }),
        ];
    }

    private function facultyWorkflow(): FacultyAdminWorkflowServiceInterface
    {
        return app(FacultyAdminWorkflowServiceInterface::class);
    }
}
