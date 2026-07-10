<?php

declare(strict_types=1);

namespace App\Filament\Resources\PersonResource\Pages;

use App\Filament\Resources\PersonResource;
use Filament\Infolards\Components\Section;
use Filament\Infolards\Components\TextEntry;
use Filament\Resources\Pages\ViewRecord;

class ViewPerson extends ViewRecord
{
    protected static string $resource = PersonResource::class;

    public function infolades(): array
    {
        /** @var \App\Models\Person\Person $record */
        $record = $this->record;
        $translation = $record->translations->firstWhere('locale', 'ar')
            ?? $record->translations->firstWhere('locale', 'en')
            ?? $record->translations->first();

        return [
            Section::make('Basic Information', [
                TextEntry::make('slug'),
                TextEntry::make('category'),
                TextEntry::make('title'),
                TextEntry::make('position'),
                TextEntry::make('translations.name')->label('Name')->state($translation?->name ?? '-'),
                TextEntry::make('translations.role')->label('Role')->state($translation?->role ?? '-'),
                TextEntry::make('email'),
                TextEntry::make('phone'),
                TextEntry::make('office_location')->label('Office'),
                TextEntry::make('faculty_scope_slug')->label('Faculty Scope'),
            ])->columns(2),
            Section::make('Biography', [
                TextEntry::make('bio')->label('Bio')->state($translation?->bio ?? '-')->prose(),
                TextEntry::make('quote')->label('Quote')->state($translation?->quote ?? '-')->prose(),
            ]),
        ];
    }
}
