<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Filament\Concerns\ManagesFacultyHomepage;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Pages\Page;

class ManagePetroleumFaculty extends Page implements HasForms
{
    use InteractsWithForms;
    use ManagesFacultyHomepage {
        ManagesFacultyHomepage::form insteadof InteractsWithForms;
    }

    protected static ?string $navigationIcon = 'heroicon-o-fire';

    protected static ?string $slug = 'manage-petroleum-faculty';

    protected static ?int $navigationSort = 7;

    protected static string $view = 'filament.pages.manage-faculty-homepage';

    public static function getNavigationLabel(): string
    {
        return __('admin.navigation.items.faculty_petroleum');
    }

    public function getTitle(): string
    {
        return __('admin.pages.manage_petroleum_faculty');
    }

    /** @return array<string, string> */
    protected function targetOptions(): array
    {
        return [
            'facilities.petroleum' => 'Homepage',
            'facilities.petroleum.overview' => 'Overview',
            'facilities.petroleum.departments' => 'Departments',
            'facilities.petroleum.study_plan' => 'Study Plan',
            'facilities.petroleum.labs' => 'Labs',
            'facilities.petroleum.projects' => 'Projects',
            'facilities.petroleum.research' => __('admin.cms.targets.facilities.research'),
            'facilities.petroleum.alumni' => 'Alumni',
            'facilities.petroleum.valedictorians' => 'Valedictorians',
        ];
    }

    protected function defaultTargetKey(): string
    {
        return 'facilities.petroleum';
    }

    protected static function managedFacultyScope(): string
    {
        return 'petroleum';
    }
}
