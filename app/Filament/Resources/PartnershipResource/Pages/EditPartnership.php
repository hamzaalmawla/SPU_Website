<?php

declare(strict_types=1);

namespace App\Filament\Resources\PartnershipResource\Pages;

use App\Filament\Resources\Concerns\InteractsWithAboutEntityCmsWorkflow;
use App\Filament\Resources\PartnershipResource;
use Filament\Resources\Pages\EditRecord;

class EditPartnership extends EditRecord
{
    use InteractsWithAboutEntityCmsWorkflow;

    protected static string $resource = PartnershipResource::class;

    protected function entityType(): string
    {
        return 'partnership';
    }
}
