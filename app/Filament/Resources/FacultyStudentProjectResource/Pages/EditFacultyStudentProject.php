<?php

declare(strict_types=1);

namespace App\Filament\Resources\FacultyStudentProjectResource\Pages;

use App\Contracts\Page\FacultyAdminWorkflowServiceInterface;
use App\Filament\Resources\FacultyStudentProjectResource;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;

class EditFacultyStudentProject extends EditRecord
{
    protected static string $resource = FacultyStudentProjectResource::class;

    /** @var array<string, mixed> */
    private array $originalProjectData = [];

    /** @param array<string, mixed> $data @return array<string, mixed> */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        $this->originalProjectData = ['faculty_id' => $this->record->getAttribute('faculty_id'), 'slug' => $this->record->getAttribute('slug'), 'image' => $this->record->getAttribute('image'), 'sort_order' => $this->record->getAttribute('sort_order'), 'is_enabled' => $this->record->getAttribute('is_enabled')];

        return $data;
    }

    protected function afterSave(): void
    {
        $this->facultyWorkflow()->recordFacultyProjectUpdated((int) $this->record->getKey(), auth()->id(), $this->originalProjectData);
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make()->action(function (): void {
                $this->facultyWorkflow()->deleteFacultyProject((int) $this->record->getKey(), auth()->id());
                Notification::make()->title('Faculty project deleted')->success()->send();
                $this->redirect(FacultyStudentProjectResource::getUrl('index'));
            }),
        ];
    }

    private function facultyWorkflow(): FacultyAdminWorkflowServiceInterface
    {
        return app(FacultyAdminWorkflowServiceInterface::class);
    }
}
