<?php

declare(strict_types=1);

namespace App\Filament\Pages;

final class ManageEvents extends ManageNews
{
    protected static ?string $navigationIcon = 'heroicon-o-calendar-days';

    protected static ?string $slug = 'manage-events';

    protected static ?int $navigationSort = 5;

    public static function getNavigationLabel(): string
    {
        return __('admin.navigation.items.events');
    }

    public function getTitle(): string
    {
        return __('admin.pages.manage_events');
    }

    protected function defaultNewsTargetKey(): string
    {
        return 'news.events';
    }

    protected function showsTargetSelector(): bool
    {
        return false;
    }
}
