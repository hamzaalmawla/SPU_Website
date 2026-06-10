<?php

declare(strict_types=1);

namespace App\Filament\Resources\DirectorateResource\Pages;

use App\Filament\Resources\DirectorateResource;
use Filament\Resources\Pages\CreateRecord;

class CreateDirectorate extends CreateRecord
{
    protected static string $resource = DirectorateResource::class;
}
