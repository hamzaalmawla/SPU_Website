<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Filament\Concerns\ManagesFacultyHomepage;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Pages\Page;

class ManagePharmacyFaculty extends Page implements HasForms
{
    use InteractsWithForms;
    use ManagesFacultyHomepage {
        ManagesFacultyHomepage::form insteadof InteractsWithForms;
    }

    protected static ?string $navigationIcon = 'heroicon-o-beaker';

    protected static ?string $slug = 'manage-pharmacy-faculty';

    protected static ?int $navigationSort = 4;

    protected static string $view = 'filament.pages.manage-faculty-homepage';

    public static function getNavigationLabel(): string
    {
        return __('admin.navigation.items.faculty_pharmacy');
    }

    public function getTitle(): string
    {
        return __('admin.pages.manage_pharmacy_faculty');
    }

    /** @return array<string, string> */
    protected function targetOptions(): array
    {
        return [
            'facilities.pharmacy' => 'Homepage',
            'facilities.pharmacy.overview' => 'Overview',
            'facilities.pharmacy.departments' => 'Departments',
            'facilities.pharmacy.study_plan' => 'Study Plan',
            'facilities.pharmacy.labs' => 'Labs',
            'facilities.pharmacy.projects' => 'Projects',
            'facilities.pharmacy.research' => __('admin.cms.targets.facilities.research'),
            'facilities.pharmacy.alumni' => 'Alumni',
            'facilities.pharmacy.valedictorians' => 'Valedictorians',
            'facilities.pharmacy.training' => 'Training',
        ];
    }

    protected function defaultTargetKey(): string
    {
        return 'facilities.pharmacy';
    }

    protected static function managedFacultyScope(): string
    {
        return 'pharmacy';
    }
}
