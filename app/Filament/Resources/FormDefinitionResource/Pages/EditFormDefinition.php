<?php

declare(strict_types=1);

namespace App\Filament\Resources\FormDefinitionResource\Pages;

use App\Filament\Resources\FormDefinitionResource;
use App\Models\Form\FormDefinition;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;

class EditFormDefinition extends EditRecord
{
    protected static string $resource = FormDefinitionResource::class;

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeFill(array $data): array
    {
        return $this->record instanceof FormDefinition ? FormDefinitionResource::formState($this->record) : $data;
    }

    /** @param array<string, mixed> $data */
    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        FormDefinitionResource::saveForm((int) $record->getKey(), $data, fn () => $this->halt());

        return $record->refresh();
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
