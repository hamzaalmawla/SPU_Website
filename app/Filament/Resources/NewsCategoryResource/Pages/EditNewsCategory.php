<?php

declare(strict_types=1);

namespace App\Filament\Resources\NewsCategoryResource\Pages;

use App\Contracts\News\NewsAdminWorkflowServiceInterface;
use App\Filament\Resources\NewsCategoryResource;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;

class EditNewsCategory extends EditRecord
{
    protected static string $resource = NewsCategoryResource::class;

    /** @var array<string, mixed> */
    private array $originalCategoryData = [];

    /** @param array<string, mixed> $data @return array<string, mixed> */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        $this->originalCategoryData = [
            'slug' => $this->record->getAttribute('slug'),
            'type' => $this->record->getAttribute('type'),
            'sort_order' => $this->record->getAttribute('sort_order'),
            'is_enabled' => $this->record->getAttribute('is_enabled'),
        ];

        return $data;
    }

    protected function afterSave(): void
    {
        $this->newsWorkflow()->recordCategoryUpdated((int) $this->record->getKey(), auth()->id(), $this->originalCategoryData);
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make()
                ->action(function (): void {
                    $this->newsWorkflow()->deleteCategory((int) $this->record->getKey(), auth()->id());

                    Notification::make()
                        ->title('News category deleted')
                        ->success()
                        ->send();

                    $this->redirect(NewsCategoryResource::getUrl('index'));
                }),
        ];
    }

    private function newsWorkflow(): NewsAdminWorkflowServiceInterface
    {
        return app(NewsAdminWorkflowServiceInterface::class);
    }
}
