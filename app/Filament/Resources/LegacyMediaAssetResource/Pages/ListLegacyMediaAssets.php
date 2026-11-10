<?php

declare(strict_types=1);

namespace App\Filament\Resources\LegacyMediaAssetResource\Pages;

use App\Filament\Resources\LegacyMediaAssetResource;
use Filament\Resources\Pages\ListRecords;

final class ListLegacyMediaAssets extends ListRecords
{
    protected static string $resource = LegacyMediaAssetResource::class;
}
