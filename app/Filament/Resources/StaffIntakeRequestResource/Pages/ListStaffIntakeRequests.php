<?php

declare(strict_types=1);

namespace App\Filament\Resources\StaffIntakeRequestResource\Pages;

use App\Filament\Resources\StaffIntakeRequestResource;
use Filament\Resources\Pages\ListRecords;

class ListStaffIntakeRequests extends ListRecords
{
    protected static string $resource = StaffIntakeRequestResource::class;
}
