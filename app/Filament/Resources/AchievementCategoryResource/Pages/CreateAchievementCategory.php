<?php

declare(strict_types=1);

namespace App\Filament\Resources\AchievementCategoryResource\Pages;

use App\Filament\Resources\AchievementCategoryResource;
use Filament\Resources\Pages\CreateRecord;

class CreateAchievementCategory extends CreateRecord
{
    protected static string $resource = AchievementCategoryResource::class;
}
