<?php

declare(strict_types=1);

namespace App\Filament\Resources\ResearcherResource\Pages;

use App\Filament\Resources\ResearcherResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListResearchers extends ListRecords
{
    protected static string $resource = ResearcherResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
