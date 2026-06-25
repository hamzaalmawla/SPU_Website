<?php

declare(strict_types=1);

namespace App\Filament\Resources\NewsArticleResource\Pages;

use App\Contracts\News\NewsAdminWorkflowServiceInterface;
use App\Filament\Resources\NewsArticleResource;
use Filament\Resources\Pages\CreateRecord;

class CreateNewsArticle extends CreateRecord
{
    protected static string $resource = NewsArticleResource::class;

    /** @param array<string, mixed> $data @return array<string, mixed> */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        return $this->newsWorkflow()->prepareArticleDataForCreate($data, auth()->id());
    }

    protected function afterCreate(): void
    {
        $this->newsWorkflow()->recordArticleCreated((int) $this->record->getKey(), auth()->id());
    }

    private function newsWorkflow(): NewsAdminWorkflowServiceInterface
    {
        return app(NewsAdminWorkflowServiceInterface::class);
    }
}
