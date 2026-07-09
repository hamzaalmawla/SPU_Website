<?php

declare(strict_types=1);

namespace App\Filament\Resources\DynamicFormSubmissionResource\Pages;

use App\Filament\Resources\DynamicFormSubmissionResource;
use Filament\Resources\Pages\ListRecords;

class ListDynamicFormSubmissions extends ListRecords
{
    protected static string $resource = DynamicFormSubmissionResource::class;
}
