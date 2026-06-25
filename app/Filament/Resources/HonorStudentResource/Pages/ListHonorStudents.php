<?php

declare(strict_types=1);

namespace App\Filament\Resources\HonorStudentResource\Pages;

use App\Filament\Resources\HonorStudentResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListHonorStudents extends ListRecords
{
    protected static string $resource = HonorStudentResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\CreateAction::make()];
    }
}
