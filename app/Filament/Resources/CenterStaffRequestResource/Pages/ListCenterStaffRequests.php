<?php

declare(strict_types=1);

namespace App\Filament\Resources\CenterStaffRequestResource\Pages;

use App\Filament\Resources\CenterStaffRequestResource;
use Filament\Resources\Pages\ListRecords;

class ListCenterStaffRequests extends ListRecords
{
    protected static string $resource = CenterStaffRequestResource::class;
}
