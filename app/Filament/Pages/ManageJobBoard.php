<?php

declare(strict_types=1);

namespace App\Filament\Pages;

final class ManageJobBoard extends ManageCampusLife
{
    protected static ?string $navigationIcon = 'heroicon-o-briefcase';

    protected static ?string $slug = 'manage-job-board';

    protected static ?int $navigationSort = 2;

    public static function canAccess(): bool
    {
        return self::canAccessCmsArea('job_board');
    }

    public static function getNavigationLabel(): string
    {
        return __('admin.navigation.items.job_board');
    }

    public static function getNavigationGroup(): ?string
    {
        return __('admin.navigation.groups.job_board');
    }

    public function getTitle(): string
    {
        return __('admin.pages.manage_job_board');
    }

    protected function defaultCampusLifeTargetKey(): string
    {
        return 'campus_life.jobs';
    }

    protected function showsTargetSelector(): bool
    {
        return false;
    }

    protected function publishAbility(): string
    {
        return 'job_board.publish';
    }
}
