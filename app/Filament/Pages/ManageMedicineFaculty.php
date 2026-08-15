<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Filament\Concerns\ManagesFacultyHomepage;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Pages\Page;

class ManageMedicineFaculty extends Page implements HasForms
{
    use InteractsWithForms;
    use ManagesFacultyHomepage {
        ManagesFacultyHomepage::form insteadof InteractsWithForms;
    }

    protected static ?string $navigationIcon = 'heroicon-o-academic-cap';

    protected static ?string $slug = 'manage-medicine-faculty';

    protected static ?int $navigationSort = 2;

    protected static string $view = 'filament.pages.manage-faculty-homepage';

    public static function getNavigationLabel(): string
    {
        return __('admin.navigation.items.faculty_medicine');
    }

    public function getTitle(): string
    {
        return __('admin.pages.manage_medicine_faculty');
    }

    /** @return array<string, string> */
    protected function targetOptions(): array
    {
        return [
            'facilities.medicine' => 'Homepage',
            'facilities.medicine.overview' => 'Overview',
            'facilities.medicine.departments' => 'Departments',
            'facilities.medicine.study_plan' => 'Study Plan',
            'facilities.medicine.labs' => 'Labs',
            'facilities.medicine.projects' => 'Projects',
            'facilities.medicine.research' => __('admin.cms.targets.facilities.research'),
            'facilities.medicine.alumni' => 'Alumni',
            'facilities.medicine.valedictorians' => 'Valedictorians',
            'facilities.medicine.members' => __('admin.cms.targets.facilities.members'),
        ];
    }

    protected function defaultTargetKey(): string
    {
        return 'facilities.medicine';
    }

    protected static function managedFacultyScope(): string
    {
        return 'medicine';
    }
}
