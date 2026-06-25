<?php

declare(strict_types=1);

namespace App\Filament\Resources\FacultyResource\Pages;

use App\Contracts\Page\FacultyAdminWorkflowServiceInterface;
use App\Filament\Resources\FacultyResource;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;

class EditFaculty extends EditRecord
{
    protected static string $resource = FacultyResource::class;

    /** @var array<string, mixed> */
    private array $originalFacultyData = [];

    /** @param array<string, mixed> $data @return array<string, mixed> */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        $this->originalFacultyData = [
            'slug' => $this->record->getAttribute('slug'),
            'public_slug' => $this->record->getAttribute('public_slug'),
            'faculty_scope_slug' => $this->record->getAttribute('faculty_scope_slug'),
            'sort_order' => $this->record->getAttribute('sort_order'),
            'is_enabled' => $this->record->getAttribute('is_enabled'),
        ];

        return $data;
    }

    protected function afterSave(): void
    {
        $this->facultyWorkflow()->recordFacultyUpdated((int) $this->record->getKey(), auth()->id(), $this->originalFacultyData);
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make()
                ->action(function (): void {
                    $this->facultyWorkflow()->deleteFaculty((int) $this->record->getKey(), auth()->id());

                    Notification::make()
                        ->title('Faculty deleted')
                        ->success()
                        ->send();

                    $this->redirect(FacultyResource::getUrl('index'));
                }),
        ];
    }

    private function facultyWorkflow(): FacultyAdminWorkflowServiceInterface
    {
        return app(FacultyAdminWorkflowServiceInterface::class);
    }
}
