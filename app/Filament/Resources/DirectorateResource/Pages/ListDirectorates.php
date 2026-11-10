<?php

declare(strict_types=1);

namespace App\Filament\Resources\DirectorateResource\Pages;

use App\Filament\Resources\DirectorateResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListDirectorates extends ListRecords
{
    protected static string $resource = DirectorateResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\CreateAction::make()];
    }
}
