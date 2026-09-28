<?php

declare(strict_types=1);

namespace App\Filament\Resources\AchievementCategoryResource\Pages;

use App\Filament\Resources\AchievementCategoryResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListAchievementCategories extends ListRecords
{
    protected static string $resource = AchievementCategoryResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\CreateAction::make()];
    }
}
