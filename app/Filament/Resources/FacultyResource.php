<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Filament\Resources\FacultyResource\Pages;
use App\Models\Faculty\Faculty;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Gate;

class FacultyResource extends Resource
{
    protected static ?string $model = Faculty::class;

    protected static ?string $navigationIcon = 'heroicon-o-academic-cap';

    protected static ?string $navigationGroup = 'Faculties';

    protected static ?int $navigationSort = 1;

    public static function canAccess(): bool
    {
        return Gate::allows('manage-faculties');
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery()->with('translations');
        $user = auth()->user();

        if ($user?->role_slug === 'faculty_editor') {
            $query->where('faculty_scope_slug', $user->faculty_scope_slug);
        }

        return $query;
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Section::make('Faculty')->schema([
                TextInput::make('slug')->required()->alphaDash()->maxLength(255),
                TextInput::make('public_slug')->required()->alphaDash()->maxLength(255),
                TextInput::make('faculty_scope_slug')->maxLength(255),
                TextInput::make('accent_color')->maxLength(20),
                TextInput::make('hero_image')->maxLength(255),
                TextInput::make('logo_image')->maxLength(255),
                TextInput::make('sort_order')->numeric()->default(0),
                Toggle::make('is_enabled')->default(true),
            ])->columns(2),
            Repeater::make('translations')->relationship()->schema([
                Select::make('locale')->required()->options(['ar' => 'Arabic', 'en' => 'English']),
                TextInput::make('name')->required()->maxLength(255),
                TextInput::make('catalog_title')->maxLength(255),
                TextInput::make('years_label')->maxLength(255),
                Textarea::make('short_description')->rows(3),
                Textarea::make('description')->rows(5)->columnSpanFull(),
            ])->columns(2)->minItems(2)->maxItems(2)->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('public_slug')->searchable()->sortable(),
            TextColumn::make('translations.name')->label('Names')->listWithLineBreaks()->limit(40),
            TextColumn::make('faculty_scope_slug')->toggleable(),
            IconColumn::make('is_enabled')->boolean(),
            TextColumn::make('updated_at')->dateTime()->sortable(),
        ])->actions([Tables\Actions\EditAction::make()])->bulkActions([]);
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ListFaculties::route('/'), 'create' => Pages\CreateFaculty::route('/create'), 'edit' => Pages\EditFaculty::route('/{record}/edit')];
    }
}
