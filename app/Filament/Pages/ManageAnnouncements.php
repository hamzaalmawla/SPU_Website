<?php

declare(strict_types=1);

namespace App\Filament\Pages;

final class ManageAnnouncements extends ManageNews
{
    protected static ?string $navigationIcon = 'heroicon-o-megaphone';

    protected static ?string $slug = 'manage-announcements';

    protected static ?int $navigationSort = 4;

    public static function getNavigationLabel(): string
    {
        return __('admin.navigation.items.announcements');
    }

    public function getTitle(): string
    {
        return __('admin.pages.manage_announcements');
    }

    protected function defaultNewsTargetKey(): string
    {
        return 'news.announcements';
    }

    protected function showsTargetSelector(): bool
    {
        return false;
    }
}
