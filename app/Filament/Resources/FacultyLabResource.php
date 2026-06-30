<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Contracts\Page\FacultyAdminWorkflowServiceInterface;
use App\Filament\Resources\FacultyLabResource\Pages;
use App\Models\Faculty\FacultyLab;
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

class FacultyLabResource extends Resource
{
    protected static ?string $model = FacultyLab::class;

    protected static ?string $navigationIcon = 'heroicon-o-beaker';

    protected static ?int $navigationSort = 4;

    public static function canAccess(): bool
    {
        return Gate::allows('manage-faculties');
    }

    public static function getNavigationGroup(): ?string
    {
        return __('admin.navigation.groups.facilities');
    }

    public static function getNavigationLabel(): string
    {
        return __('admin.navigation.items.faculty_labs');
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery()->with(['faculty', 'translations']);
        $user = auth()->user();

        if ($user?->role_slug === 'faculty_editor') {
            $query->whereHas('faculty', fn (Builder $facultyQuery): Builder => $facultyQuery->where('faculty_scope_slug', $user->faculty_scope_slug));
        }

        return $query;
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Section::make('Lab')->schema([
                Select::make('faculty_id')->required()->searchable()->preload()->options(fn (): array => app(FacultyAdminWorkflowServiceInterface::class)->facultyOptionsForCurrentUser(auth()->id())),
                TextInput::make('slug')->required()->alphaDash()->maxLength(255),
                TextInput::make('image')->maxLength(255),
                TextInput::make('sort_order')->numeric()->default(0),
                Toggle::make('is_enabled')->default(true),
            ])->columns(2),
            Repeater::make('translations')->relationship()->schema([
                Select::make('locale')->required()->options(['ar' => 'Arabic', 'en' => 'English']),
                TextInput::make('title')->required()->maxLength(255),
                TextInput::make('department')->maxLength(255),
                TextInput::make('instructor')->maxLength(255),
                Textarea::make('description')->rows(3)->columnSpanFull(),
            ])->columns(2)->minItems(2)->maxItems(2)->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('faculty.public_slug')->label('Faculty')->sortable(),
            TextColumn::make('slug')->searchable()->sortable(),
            TextColumn::make('translations.title')->label('Titles')->listWithLineBreaks()->limit(40),
            IconColumn::make('is_enabled')->boolean(),
            TextColumn::make('sort_order')->sortable(),
        ])->actions([Tables\Actions\EditAction::make()])->bulkActions([]);
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ListFacultyLabs::route('/'), 'create' => Pages\CreateFacultyLab::route('/create'), 'edit' => Pages\EditFacultyLab::route('/{record}/edit')];
    }
}
