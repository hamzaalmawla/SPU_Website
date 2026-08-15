<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Filament\Concerns\ManagesFacultyHomepage;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Pages\Page;

class ManageDentistryFaculty extends Page implements HasForms
{
    use InteractsWithForms;
    use ManagesFacultyHomepage {
        ManagesFacultyHomepage::form insteadof InteractsWithForms;
    }

    protected static ?string $navigationIcon = 'heroicon-o-face-smile';

    protected static ?string $slug = 'manage-dentistry-faculty';

    protected static ?int $navigationSort = 3;

    protected static string $view = 'filament.pages.manage-faculty-homepage';

    public static function getNavigationLabel(): string
    {
        return __('admin.navigation.items.faculty_dentistry');
    }

    public function getTitle(): string
    {
        return __('admin.pages.manage_dentistry_faculty');
    }

    /** @return array<string, string> */
    protected function targetOptions(): array
    {
        return [
            'facilities.dentistry' => 'Homepage',
            'facilities.dentistry.overview' => 'Overview',
            'facilities.dentistry.departments' => 'Departments',
            'facilities.dentistry.study_plan' => 'Study Plan',
            'facilities.dentistry.labs' => 'Labs',
            'facilities.dentistry.projects' => 'Projects',
            'facilities.dentistry.research' => __('admin.cms.targets.facilities.research'),
            'facilities.dentistry.alumni' => 'Alumni',
            'facilities.dentistry.valedictorians' => 'Valedictorians',
            'facilities.dentistry.members' => __('admin.cms.targets.facilities.members'),
        ];
    }

    protected function defaultTargetKey(): string
    {
        return 'facilities.dentistry';
    }

    protected static function managedFacultyScope(): string
    {
        return 'dentistry';
    }
}
