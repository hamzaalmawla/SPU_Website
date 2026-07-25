<?php

declare(strict_types=1);

namespace App\Filament\Resources\NewsArticleResource\Pages;

use App\Filament\Resources\Concerns\InteractsWithNewsArticleCmsWorkflow;
use App\Filament\Resources\NewsArticleResource;
use Filament\Actions\Action;
use Filament\Resources\Pages\EditRecord;

class EditNewsArticle extends EditRecord
{
    use InteractsWithNewsArticleCmsWorkflow;

    protected static string $resource = NewsArticleResource::class;

    /** @return array<int, Action> */
    protected function getFormActions(): array
    {
        return [];
    }
}
