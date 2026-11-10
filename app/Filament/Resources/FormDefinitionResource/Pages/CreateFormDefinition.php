<?php

declare(strict_types=1);

namespace App\Filament\Resources\FormDefinitionResource\Pages;

use App\Filament\Resources\FormDefinitionResource;
use App\Models\Form\FormDefinition;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateFormDefinition extends CreateRecord
{
    protected static string $resource = FormDefinitionResource::class;

    /** @param array<string, mixed> $data */
    protected function handleRecordCreation(array $data): Model
    {
        $id = FormDefinitionResource::saveForm(null, $data, fn () => $this->halt());

        return FormDefinition::query()->findOrFail($id);
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
