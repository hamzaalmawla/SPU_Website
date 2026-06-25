<?php

declare(strict_types=1);

namespace App\Filament\Resources\HonorStudentResource\Pages;

use App\Contracts\Page\FacultyAdminWorkflowServiceInterface;
use App\Filament\Resources\HonorStudentResource;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;

class EditHonorStudent extends EditRecord
{
    protected static string $resource = HonorStudentResource::class;

    /** @var array<string, mixed> */
    private array $originalHonorStudentData = [];

    /** @param array<string, mixed> $data @return array<string, mixed> */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        $this->originalHonorStudentData = ['student_identifier' => $this->record->getAttribute('student_identifier'), 'faculty_id' => $this->record->getAttribute('faculty_id'), 'department_id' => $this->record->getAttribute('department_id'), 'academic_year' => $this->record->getAttribute('academic_year'), 'gpa' => $this->record->getAttribute('gpa'), 'sort_order' => $this->record->getAttribute('sort_order'), 'is_enabled' => $this->record->getAttribute('is_enabled')];

        return $data;
    }

    protected function afterSave(): void
    {
        $this->facultyWorkflow()->recordHonorStudentUpdated((int) $this->record->getKey(), auth()->id(), $this->originalHonorStudentData);
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make()->action(function (): void {
                $this->facultyWorkflow()->deleteHonorStudent((int) $this->record->getKey(), auth()->id());
                Notification::make()->title('Honor student deleted')->success()->send();
                $this->redirect(HonorStudentResource::getUrl('index'));
            }),
        ];
    }

    private function facultyWorkflow(): FacultyAdminWorkflowServiceInterface
    {
        return app(FacultyAdminWorkflowServiceInterface::class);
    }
}
