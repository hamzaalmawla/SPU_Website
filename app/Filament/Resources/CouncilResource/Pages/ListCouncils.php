<?php

declare(strict_types=1);

namespace App\Filament\Resources\CouncilResource\Pages;

use App\Filament\Resources\CouncilResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListCouncils extends ListRecords
{
    protected static string $resource = CouncilResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\CreateAction::make()];
    }
}
