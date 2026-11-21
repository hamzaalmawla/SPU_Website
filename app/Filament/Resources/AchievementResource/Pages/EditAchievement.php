<?php

declare(strict_types=1);

namespace App\Filament\Resources\AchievementResource\Pages;

use App\Contracts\Achievement\AchievementServiceInterface;
use App\Contracts\Shared\CacheServiceInterface;
use App\Filament\Resources\AchievementResource;
use Filament\Resources\Pages\EditRecord;

class EditAchievement extends EditRecord
{
    protected static string $resource = AchievementResource::class;

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        return app(AchievementServiceInterface::class)->preparePublishingData($data);
    }

    protected function afterSave(): void
    {
        app(CacheServiceInterface::class)->flushTags(['public-pages', 'homepage', 'achievements']);
    }
}
