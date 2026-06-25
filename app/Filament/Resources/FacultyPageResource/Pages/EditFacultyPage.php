<?php

declare(strict_types=1);

namespace App\Filament\Resources\FacultyPageResource\Pages;

use App\Contracts\Page\FacultyAdminWorkflowServiceInterface;
use App\Filament\Resources\FacultyPageResource;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;

class EditFacultyPage extends EditRecord
{
    protected static string $resource = FacultyPageResource::class;

    /** @var array<string, mixed> */
    private array $originalPageData = [];

    /** @param array<string, mixed> $data @return array<string, mixed> */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        $this->originalPageData = [
            'faculty_id' => $this->record->getAttribute('faculty_id'),
            'slug' => $this->record->getAttribute('slug'),
            'kind' => $this->record->getAttribute('kind'),
            'sort_order' => $this->record->getAttribute('sort_order'),
            'is_enabled' => $this->record->getAttribute('is_enabled'),
        ];

        return $data;
    }

    protected function afterSave(): void
    {
        $this->facultyWorkflow()->recordFacultyPageUpdated((int) $this->record->getKey(), auth()->id(), $this->originalPageData);
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make()
                ->action(function (): void {
                    $this->facultyWorkflow()->deleteFacultyPage((int) $this->record->getKey(), auth()->id());

                    Notification::make()
                        ->title('Faculty page deleted')
                        ->success()
                        ->send();

                    $this->redirect(FacultyPageResource::getUrl('index'));
                }),
        ];
    }

    private function facultyWorkflow(): FacultyAdminWorkflowServiceInterface
    {
        return app(FacultyAdminWorkflowServiceInterface::class);
    }
}
