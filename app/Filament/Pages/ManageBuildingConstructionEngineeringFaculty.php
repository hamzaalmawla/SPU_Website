<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Filament\Concerns\ManagesFacultyHomepage;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Pages\Page;

class ManageBuildingConstructionEngineeringFaculty extends Page implements HasForms
{
    use InteractsWithForms;
    use ManagesFacultyHomepage {
        ManagesFacultyHomepage::form insteadof InteractsWithForms;
    }

    protected static ?string $navigationIcon = 'heroicon-o-building-office-2';

    protected static ?string $slug = 'manage-building-construction-engineering-faculty';

    protected static ?int $navigationSort = 6;

    protected static string $view = 'filament.pages.manage-faculty-homepage';

    public static function getNavigationLabel(): string
    {
        return __('admin.navigation.items.faculty_building_construction_engineering');
    }

    public function getTitle(): string
    {
        return __('admin.pages.manage_building_construction_engineering_faculty');
    }

    /** @return array<string, string> */
    protected function targetOptions(): array
    {
        return [
            'facilities.building-construction-engineering' => 'Homepage',
            'facilities.building-construction-engineering.overview' => 'Overview',
            'facilities.building-construction-engineering.departments' => 'Departments',
            'facilities.building-construction-engineering.study_plan' => 'Study Plan',
            'facilities.building-construction-engineering.labs' => 'Labs',
            'facilities.building-construction-engineering.projects' => 'Projects',
            'facilities.building-construction-engineering.research' => __('admin.cms.targets.facilities.research'),
            'facilities.building-construction-engineering.alumni' => 'Alumni',
            'facilities.building-construction-engineering.valedictorians' => 'Valedictorians',
        ];
    }

    protected function defaultTargetKey(): string
    {
        return 'facilities.building-construction-engineering';
    }

    protected static function managedFacultyScope(): string
    {
        return 'building-construction-engineering';
    }
}
