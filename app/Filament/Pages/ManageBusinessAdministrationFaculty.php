<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Filament\Concerns\ManagesFacultyHomepage;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Pages\Page;

class ManageBusinessAdministrationFaculty extends Page implements HasForms
{
    use InteractsWithForms;
    use ManagesFacultyHomepage {
        ManagesFacultyHomepage::form insteadof InteractsWithForms;
    }

    protected static ?string $navigationIcon = 'heroicon-o-briefcase';

    protected static ?string $slug = 'manage-business-administration-faculty';

    protected static ?int $navigationSort = 8;

    protected static string $view = 'filament.pages.manage-faculty-homepage';

    public static function getNavigationLabel(): string
    {
        return __('admin.navigation.items.faculty_business_administration');
    }

    public function getTitle(): string
    {
        return __('admin.pages.manage_business_administration_faculty');
    }

    /** @return array<string, string> */
    protected function targetOptions(): array
    {
        return [
            'facilities.business-administration' => 'Homepage',
            'facilities.business-administration.overview' => 'Overview',
            'facilities.business-administration.departments' => 'Departments',
            'facilities.business-administration.study_plan' => 'Study Plan',
            'facilities.business-administration.labs' => 'Labs',
            'facilities.business-administration.projects' => 'Projects',
            'facilities.business-administration.research' => __('admin.cms.targets.facilities.research'),
            'facilities.business-administration.alumni' => 'Alumni',
            'facilities.business-administration.valedictorians' => 'Valedictorians',
            'facilities.business-administration.members' => __('admin.cms.targets.facilities.members'),
        ];
    }

    protected function defaultTargetKey(): string
    {
        return 'facilities.business-administration';
    }

    protected static function managedFacultyScope(): string
    {
        return 'business-administration';
    }
}
