<?php

declare(strict_types=1);

namespace App\Filament\Resources\NewsCategoryResource\Pages;

use App\Contracts\News\NewsAdminWorkflowServiceInterface;
use App\Filament\Resources\NewsCategoryResource;
use Filament\Resources\Pages\CreateRecord;

class CreateNewsCategory extends CreateRecord
{
    protected static string $resource = NewsCategoryResource::class;

    protected function afterCreate(): void
    {
        $this->newsWorkflow()->recordCategoryCreated((int) $this->record->getKey(), auth()->id());
    }

    private function newsWorkflow(): NewsAdminWorkflowServiceInterface
    {
        return app(NewsAdminWorkflowServiceInterface::class);
    }
}
