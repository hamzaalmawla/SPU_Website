<?php

declare(strict_types=1);

namespace App\Filament\Resources\FormDefinitionResource\Pages;

use App\Filament\Resources\FormDefinitionResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListFormDefinitions extends ListRecords
{
    protected static string $resource = FormDefinitionResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
