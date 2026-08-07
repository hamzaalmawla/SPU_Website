<?php

declare(strict_types=1);

namespace App\Filament\Resources\AboutPageResource\Pages;

use App\Contracts\Page\AboutNavigationCardServiceInterface;
use App\Filament\Resources\AboutPageResource;
use App\Models\Page\AboutPage;
use Filament\Resources\Pages\CreateRecord;

class CreateAboutPage extends CreateRecord
{
    protected static string $resource = AboutPageResource::class;

    protected function handleRecordCreation(array $data): AboutPage
    {
        $record = parent::handleRecordCreation($data);

        $this->autoAddToNavigation($record);

        return $record;
    }

    private function autoAddToNavigation(AboutPage $page): void
    {
        $slug = (string) $page->slug;

        if ($slug === 'about') {
            return;
        }

        $targetKey = 'about.' . $slug;
        $service = app(AboutNavigationCardServiceInterface::class);
        $service->autoCreateForTarget($targetKey);
    }
}
