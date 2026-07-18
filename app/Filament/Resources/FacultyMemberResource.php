<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Filament\Resources\FacultyMemberResource\Pages;
use App\Filament\Support\MediaPicker;
use App\Models\Person\FacultyMember;
use App\Models\User\User;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TagsInput;
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

class FacultyMemberResource extends Resource
{
    protected static ?string $model = FacultyMember::class;

    protected static ?string $navigationIcon = 'heroicon-o-academic-cap';

    protected static ?int $navigationSort = 2;

    protected static ?string $navigationLabel = 'Faculty Members';

    public static function canAccess(): bool
    {
        return Gate::allows('manage-faculties');
    }

    public static function getNavigationGroup(): ?string
    {
        return __('admin.navigation.groups.about');
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery()
            ->with(['translations', 'faculty.translations', 'department.translations']);

        $user = auth()->user();
        if ($user instanceof User && $user->role_slug === 'faculty_editor') {
            $scope = (string) $user->faculty_scope_slug;
            $query->whereHas('faculty', fn (Builder $facultyQuery): Builder => $facultyQuery
                ->where('faculty_scope_slug', $scope)
                ->orWhere('public_slug', $scope)
                ->orWhere('slug', $scope));
        }

        return $query;
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Section::make('Profile')->schema([
                TextInput::make('slug')->required()->maxLength(255)->alphaDash()->unique(ignoreRecord: true),
                Select::make('faculty_id')
                    ->label('Faculty')
                    ->relationship('faculty', 'id', modifyQueryUsing: fn (Builder $query): Builder => self::scopeFacultyOptions($query))
                    ->getOptionLabelFromRecordUsing(fn ($record) => $record->translations->firstWhere('locale', 'ar')?->name ?? $record->translations->first()?->name ?? '#'.$record->id)
                    ->searchable()
                    ->preload(),
                Select::make('department_id')
                    ->label('Department')
                    ->relationship('department', 'id', modifyQueryUsing: fn (Builder $query): Builder => self::scopeDepartmentOptions($query))
                    ->getOptionLabelFromRecordUsing(fn ($record) => $record->translations->firstWhere('locale', 'ar')?->name ?? $record->translations->first()?->name ?? '#'.$record->id)
                    ->searchable()
                    ->preload(),
                TextInput::make('email')->email()->maxLength(255),
                TextInput::make('phone')->maxLength(255),
                TextInput::make('office_location')->maxLength(255),
                MediaPicker::assetImage('photo_media_id', 'Profile Photo'),
                MediaPicker::assetDocument('cv_media_id', 'CV File'),
                TextInput::make('sort_order')->numeric()->default(0),
                Toggle::make('is_enabled')->default(true),
            ])->columns(2),
            Section::make('Social Links')->schema([
                TextInput::make('social_links.linkedin')->label('LinkedIn URL')->url()->maxLength(255),
                TextInput::make('social_links.scholar')->label('Google Scholar URL')->url()->maxLength(255),
                TextInput::make('social_links.orcid')->label('ORCID URL')->url()->maxLength(255),
                TextInput::make('social_links.researchgate')->label('ResearchGate URL')->url()->maxLength(255),
                TextInput::make('social_links.twitter')->label('Twitter/X URL')->url()->maxLength(255),
            ])->columns(2)->collapsible(),
            Repeater::make('translations')->schema([
                Select::make('locale')->required()->options(['ar' => 'Arabic', 'en' => 'English'])->disableOptionsWhenSelectedInSiblingRepeaterItems(),
                TextInput::make('full_name')->required()->maxLength(255),
                TextInput::make('title')->maxLength(255)->placeholder('Prof., Dr., etc.'),
                TextInput::make('position')->maxLength(255)->placeholder('Dean, Assistant Professor, etc.'),
                Textarea::make('bio')->rows(4),
                TagsInput::make('specializations')->separator(',')->columnSpanFull(),
            ])->columns(2)->default([['locale' => 'ar'], ['locale' => 'en']])->minItems(2)->maxItems(2)->columnSpanFull(),
            Section::make('Education')->schema([
                Repeater::make('educations')->schema([
                    TextInput::make('id')->hidden(),
                    TextInput::make('sort_order')->numeric()->default(0),
                    Toggle::make('is_enabled')->default(true),
                    Repeater::make('translations')->schema([
                        Select::make('locale')->required()->options(['ar' => 'Arabic', 'en' => 'English'])->disableOptionsWhenSelectedInSiblingRepeaterItems(),
                        TextInput::make('degree')->required()->maxLength(255),
                        TextInput::make('institution')->maxLength(255),
                        TextInput::make('field_of_study')->maxLength(255),
                        TextInput::make('year_start')->numeric()->minValue(1900)->maxValue(2100),
                        TextInput::make('year_end')->numeric()->minValue(1900)->maxValue(2100)->gte('year_start'),
                        Textarea::make('description')->rows(2),
                    ])->columns(2)->default([['locale' => 'ar'], ['locale' => 'en']])->minItems(2)->maxItems(2)->columnSpanFull(),
                ])->columns(2)->defaultItems(0)->addActionLabel('Add Education')->columnSpanFull(),
            ])->collapsible()->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('slug')->searchable()->sortable(),
            TextColumn::make('translations.full_name')->label('Name')->searchable()->limit(40),
            TextColumn::make('translations.title')->label('Title')->limit(20),
            TextColumn::make('translations.position')->label('Position')->limit(20),
            TextColumn::make('faculty.translations.name')->label('Faculty')->limit(30),
            IconColumn::make('is_enabled')->boolean(),
            TextColumn::make('publication_status')->badge()->sortable(),
            TextColumn::make('updated_at')->dateTime()->sortable(),
        ])->actions([
            Tables\Actions\EditAction::make(),
            Tables\Actions\ViewAction::make(),
        ])->bulkActions([]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListFacultyMembers::route('/'),
            'create' => Pages\CreateFacultyMember::route('/create'),
            'edit' => Pages\EditFacultyMember::route('/{record}/edit'),
            'view' => Pages\ViewFacultyMember::route('/{record}'),
        ];
    }

    private static function scopeFacultyOptions(Builder $query): Builder
    {
        $user = auth()->user();
        if (! $user instanceof User || $user->role_slug !== 'faculty_editor') {
            return $query;
        }

        $scope = (string) $user->faculty_scope_slug;

        return $query->where(function (Builder $scopeQuery) use ($scope): void {
            $scopeQuery->where('faculty_scope_slug', $scope)
                ->orWhere('public_slug', $scope)
                ->orWhere('slug', $scope);
        });
    }

    private static function scopeDepartmentOptions(Builder $query): Builder
    {
        $user = auth()->user();
        if (! $user instanceof User || $user->role_slug !== 'faculty_editor') {
            return $query;
        }

        $scope = (string) $user->faculty_scope_slug;

        return $query->whereHas('faculty', function (Builder $facultyQuery) use ($scope): void {
            $facultyQuery->where('faculty_scope_slug', $scope)
                ->orWhere('public_slug', $scope)
                ->orWhere('slug', $scope);
        });
    }
}
