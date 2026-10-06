<?php

declare(strict_types=1);

namespace App\Filament\Resources\MediaAssetResource\Pages;

use App\Filament\Resources\MediaAssetResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListMediaAssets extends ListRecords
{
    protected static string $resource = MediaAssetResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('batchUpload')
                ->label('Batch Upload Images')
                ->icon('heroicon-o-photo')
                ->color('primary')
                ->url(MediaAssetResource::getUrl('batch-upload')),
            Actions\CreateAction::make(),
        ];
    }
}
