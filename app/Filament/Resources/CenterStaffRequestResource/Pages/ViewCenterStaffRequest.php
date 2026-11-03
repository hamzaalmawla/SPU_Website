<?php

declare(strict_types=1);

namespace App\Filament\Resources\CenterStaffRequestResource\Pages;

use App\Filament\Resources\CenterStaffRequestResource;
use App\Filament\Resources\StaffIntakeRequestResource\Pages\ViewStaffIntakeRequest;

class ViewCenterStaffRequest extends ViewStaffIntakeRequest
{
    protected static string $resource = CenterStaffRequestResource::class;
}
