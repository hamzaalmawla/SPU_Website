<?php

declare(strict_types=1);

namespace App\Filament\Resources\FacultyHighlightResource\Pages;

use App\Contracts\Page\FacultyAdminWorkflowServiceInterface;
use App\Filament\Resources\FacultyHighlightResource;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;

class EditFacultyHighlight extends EditRecord
{
    protected static string $resource = FacultyHighlightResource::class;

    /** @var array<string, mixed> */
    private array $originalHighlightData = [];

    /** @param array<string, mixed> $data @return array<string, mixed> */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        $this->originalHighlightData = [
            'faculty_id' => $this->record->getAttribute('faculty_id'),
            'key' => $this->record->getAttribute('key'),
            'value' => $this->record->getAttribute('value'),
            'url' => $this->record->getAttribute('url'),
            'sort_order' => $this->record->getAttribute('sort_order'),
            'is_enabled' => $this->record->getAttribute('is_enabled'),
        ];

        return $data;
    }

    protected function afterSave(): void
    {
        $this->facultyWorkflow()->recordFacultyHighlightUpdated((int) $this->record->getKey(), auth()->id(), $this->originalHighlightData);
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make()
                ->action(function (): void {
                    $this->facultyWorkflow()->deleteFacultyHighlight((int) $this->record->getKey(), auth()->id());

                    Notification::make()
                        ->title('Faculty highlight deleted')
                        ->success()
                        ->send();

                    $this->redirect(FacultyHighlightResource::getUrl('index'));
                }),
        ];
    }

    private function facultyWorkflow(): FacultyAdminWorkflowServiceInterface
    {
        return app(FacultyAdminWorkflowServiceInterface::class);
    }
}
