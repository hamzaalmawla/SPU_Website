<?php

declare(strict_types=1);

namespace App\Filament\Resources\DirectorateResource\Pages;

use App\Filament\Resources\Concerns\InteractsWithAboutEntityCmsWorkflow;
use App\Filament\Resources\DirectorateResource;
use Filament\Resources\Pages\EditRecord;

class EditDirectorate extends EditRecord
{
    use InteractsWithAboutEntityCmsWorkflow;

    protected static string $resource = DirectorateResource::class;

    protected function entityType(): string
    {
        return 'directorate';
    }
}
