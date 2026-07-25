<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Models\User\User;

final class ManageJobBoard extends ManageCampusLife
{
    protected static ?string $navigationIcon = 'heroicon-o-briefcase';

    protected static ?string $slug = 'manage-job-board';

    protected static ?int $navigationSort = 2;

    public static function canAccess(): bool
    {
        $user = auth()->user();

        return parent::canAccess()
            && $user instanceof User
            && $user->role_slug !== 'faculty_editor';
    }

    public static function getNavigationLabel(): string
    {
        return __('admin.navigation.items.job_board');
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
}
