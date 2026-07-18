<?php

declare(strict_types=1);

namespace App\Filament\Resources\PersonResource\Pages;

use App\Filament\Resources\Concerns\InteractsWithAboutEntityCmsWorkflow;
use App\Filament\Resources\PersonResource;
use Filament\Resources\Pages\EditRecord;

class EditPerson extends EditRecord
{
    use InteractsWithAboutEntityCmsWorkflow;

    protected static string $resource = PersonResource::class;

    protected function entityType(): string
    {
        return 'person';
    }
}
