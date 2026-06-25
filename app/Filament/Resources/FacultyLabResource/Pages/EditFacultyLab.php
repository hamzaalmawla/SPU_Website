<?php

declare(strict_types=1);

namespace App\Filament\Resources\FacultyLabResource\Pages;

use App\Contracts\Page\FacultyAdminWorkflowServiceInterface;
use App\Filament\Resources\FacultyLabResource;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;

class EditFacultyLab extends EditRecord
{
    protected static string $resource = FacultyLabResource::class;

    /** @var array<string, mixed> */
    private array $originalLabData = [];

    /** @param array<string, mixed> $data @return array<string, mixed> */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        $this->originalLabData = ['faculty_id' => $this->record->getAttribute('faculty_id'), 'slug' => $this->record->getAttribute('slug'), 'image' => $this->record->getAttribute('image'), 'sort_order' => $this->record->getAttribute('sort_order'), 'is_enabled' => $this->record->getAttribute('is_enabled')];

        return $data;
    }

    protected function afterSave(): void
    {
        $this->facultyWorkflow()->recordFacultyLabUpdated((int) $this->record->getKey(), auth()->id(), $this->originalLabData);
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make()->action(function (): void {
                $this->facultyWorkflow()->deleteFacultyLab((int) $this->record->getKey(), auth()->id());
                Notification::make()->title('Faculty lab deleted')->success()->send();
                $this->redirect(FacultyLabResource::getUrl('index'));
            }),
        ];
    }

    private function facultyWorkflow(): FacultyAdminWorkflowServiceInterface
    {
        return app(FacultyAdminWorkflowServiceInterface::class);
    }
}
