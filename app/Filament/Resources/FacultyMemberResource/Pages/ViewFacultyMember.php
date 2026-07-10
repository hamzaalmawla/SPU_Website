<?php

declare(strict_types=1);

namespace App\Filament\Resources\FacultyMemberResource\Pages;

use App\Filament\Resources\FacultyMemberResource;
use Filament\Infolards\Components\Section;
use Filament\Infolards\Components\TextEntry;
use Filament\Resources\Pages\ViewRecord;

class ViewFacultyMember extends ViewRecord
{
    protected static string $resource = FacultyMemberResource::class;

    public function infolades(): array
    {
        /** @var \App\Models\Person\FacultyMember $record */
        $record = $this->record;
        $translation = $record->translations->firstWhere('locale', 'ar')
            ?? $record->translations->firstWhere('locale', 'en')
            ?? $record->translations->first();

        $facultyName = $record->faculty?->translations->firstWhere('locale', 'ar')?->name
            ?? $record->faculty?->translations->first()?->name
            ?? '-';

        $departmentName = $record->department?->translations->firstWhere('locale', 'ar')?->name
            ?? $record->department?->translations->first()?->name
            ?? '-';

        return [
            Section::make('Basic Information', [
                TextEntry::make('slug'),
                TextEntry::make('translations.full_name')->label('Name')->state($translation?->full_name ?? '-'),
                TextEntry::make('translations.title')->label('Title')->state($translation?->title ?? '-'),
                TextEntry::make('translations.position')->label('Position')->state($translation?->position ?? '-'),
                TextEntry::make('faculty')->label('Faculty')->state($facultyName),
                TextEntry::make('department')->label('Department')->state($departmentName),
                TextEntry::make('email'),
                TextEntry::make('phone'),
                TextEntry::make('office_location')->label('Office'),
            ])->columns(2),
            Section::make('Biography', [
                TextEntry::make('bio')->label('Bio')->state($translation?->bio ?? '-')->prose(),
            ]),
        ];
    }
}
