<?php

declare(strict_types=1);

namespace App\Filament\Resources\ResearchPublicationResource\Pages;

use App\Filament\Resources\ResearchPublicationResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListResearchPublications extends ListRecords
{
    protected static string $resource = ResearchPublicationResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\CreateAction::make()];
    }
}
