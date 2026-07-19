<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Filament\Concerns\ManagesFacultyHomepage;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Pages\Page;

class ManageArtificialIntelligenceFaculty extends Page implements HasForms
{
    use InteractsWithForms;
    use ManagesFacultyHomepage {
        ManagesFacultyHomepage::form insteadof InteractsWithForms;
    }

    protected static ?string $navigationIcon = 'heroicon-o-cpu-chip';

    protected static ?string $slug = 'manage-artificial-intelligence-faculty';

    protected static ?int $navigationSort = 5;

    protected static string $view = 'filament.pages.manage-faculty-homepage';

    public static function getNavigationLabel(): string
    {
        return __('admin.navigation.items.faculty_artificial_intelligence');
    }

    public function getTitle(): string
    {
        return __('admin.pages.manage_artificial_intelligence_faculty');
    }

    /** @return array<string, string> */
    protected function targetOptions(): array
    {
        return [
            'facilities.artificial-intelligence' => 'Homepage',
            'facilities.artificial-intelligence.overview' => 'Overview',
            'facilities.artificial-intelligence.departments' => 'Departments',
            'facilities.artificial-intelligence.study_plan' => 'Study Plan',
            'facilities.artificial-intelligence.labs' => 'Labs',
            'facilities.artificial-intelligence.projects' => 'Projects',
            'facilities.artificial-intelligence.research' => __('admin.cms.targets.facilities.research'),
            'facilities.artificial-intelligence.alumni' => 'Alumni',
            'facilities.artificial-intelligence.valedictorians' => 'Valedictorians',
        ];
    }

    protected function defaultTargetKey(): string
    {
        return 'facilities.artificial-intelligence';
    }

    protected static function managedFacultyScope(): string
    {
        return 'artificial-intelligence';
    }
}
