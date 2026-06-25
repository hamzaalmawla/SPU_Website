<?php

declare(strict_types=1);

namespace App\Filament\Resources\NewsArticleResource\Pages;

use App\Contracts\News\NewsAdminWorkflowServiceInterface;
use App\Filament\Resources\NewsArticleResource;
use Filament\Resources\Pages\EditRecord;

class EditNewsArticle extends EditRecord
{
    protected static string $resource = NewsArticleResource::class;

    /** @var array<string, mixed> */
    private array $originalArticleData = [];

    /** @param array<string, mixed> $data @return array<string, mixed> */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        $this->originalArticleData = [
            'status' => $this->record->getAttribute('status'),
            'published_at' => $this->record->getAttribute('published_at')?->toIso8601String(),
            'scheduled_at' => $this->record->getAttribute('scheduled_at')?->toIso8601String(),
            'faculty_scope_slug' => $this->record->getAttribute('faculty_scope_slug'),
        ];

        return $this->newsWorkflow()->prepareArticleDataForUpdate((int) $this->record->getKey(), $data, auth()->id());
    }

    protected function afterSave(): void
    {
        $this->newsWorkflow()->recordArticleUpdated((int) $this->record->getKey(), auth()->id(), $this->originalArticleData);
    }

    private function newsWorkflow(): NewsAdminWorkflowServiceInterface
    {
        return app(NewsAdminWorkflowServiceInterface::class);
    }
}
