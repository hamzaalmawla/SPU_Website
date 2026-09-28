<?php

declare(strict_types=1);

namespace App\Filament\Resources\AchievementCategoryResource\Pages;

use App\Filament\Resources\AchievementCategoryResource;
use Filament\Resources\Pages\EditRecord;

class EditAchievementCategory extends EditRecord
{
    protected static string $resource = AchievementCategoryResource::class;
}
