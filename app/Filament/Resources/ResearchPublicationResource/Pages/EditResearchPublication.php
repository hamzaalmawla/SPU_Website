<?php

declare(strict_types=1);

namespace App\Filament\Resources\ResearchPublicationResource\Pages;

use App\Filament\Resources\Concerns\InteractsWithResearchPublicationCmsWorkflow;
use App\Filament\Resources\ResearchPublicationResource;
use Filament\Actions\Action;
use Filament\Resources\Pages\EditRecord;

class EditResearchPublication extends EditRecord
{
    use InteractsWithResearchPublicationCmsWorkflow;

    protected static string $resource = ResearchPublicationResource::class;

    /** @return array<int, Action> */
    protected function getFormActions(): array
    {
        return [];
    }
}
