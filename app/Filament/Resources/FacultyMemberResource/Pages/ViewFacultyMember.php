<?php

declare(strict_types=1);

namespace App\Filament\Resources\FacultyMemberResource\Pages;

use App\Filament\Resources\FacultyMemberResource;
use App\Models\Person\FacultyMember;
use Filament\Infolists\Components\Section;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Infolist;
use Filament\Resources\Pages\ViewRecord;

class ViewFacultyMember extends ViewRecord
{
    protected static string $resource = FacultyMemberResource::class;

    public function infolist(Infolist $infolist): Infolist
    {
        /** @var FacultyMember $record */
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

        return $infolist->schema([
            Section::make('Basic Information')->schema([
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
            Section::make('Biography')->schema([
                TextEntry::make('bio')->label('Bio')->state($translation?->bio ?? '-')->prose(),
            ]),
        ]);
    }
}
