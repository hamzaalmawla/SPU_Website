<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Contracts\Page\FacultyAdminWorkflowServiceInterface;
use App\Filament\Resources\HonorStudentResource\Pages;
use App\Models\Career\HonorStudent;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
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

class HonorStudentResource extends Resource
{
    protected static ?string $model = HonorStudent::class;

    protected static ?string $navigationIcon = 'heroicon-o-trophy';

    protected static ?string $navigationGroup = 'Facilities';

    protected static ?int $navigationSort = 7;

    public static function canAccess(): bool
    {
        return Gate::allows('manage-faculties');
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery()->with(['faculty', 'department', 'translations']);
        $user = auth()->user();

        if ($user?->role_slug === 'faculty_editor') {
            $query->whereHas('faculty', fn (Builder $facultyQuery): Builder => $facultyQuery->where('faculty_scope_slug', $user->faculty_scope_slug));
        }

        return $query;
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Section::make('Honor Student')->schema([
                Select::make('faculty_id')->searchable()->preload()->options(fn (): array => app(FacultyAdminWorkflowServiceInterface::class)->facultyOptionsForCurrentUser(auth()->id())),
                Select::make('department_id')->relationship('department', 'slug')->searchable()->preload(),
                TextInput::make('student_identifier')->maxLength(255),
                TextInput::make('academic_year')->required()->maxLength(255),
                TextInput::make('gpa')->numeric()->minValue(0)->maxValue(4),
                Select::make('photo_media_id')->label('Photo')->relationship('photoMedia', 'original_name')->searchable()->preload(),
                TextInput::make('sort_order')->numeric()->default(0),
                Toggle::make('is_enabled')->default(true),
            ])->columns(2),
            Repeater::make('translations')->relationship()->schema([
                Select::make('locale')->required()->options(['ar' => 'Arabic', 'en' => 'English']),
                TextInput::make('full_name')->required()->maxLength(255),
            ])->columns(2)->minItems(2)->maxItems(2)->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('translations.full_name')->label('Names')->listWithLineBreaks()->limit(40),
            TextColumn::make('faculty.public_slug')->label('Faculty')->sortable(),
            TextColumn::make('academic_year')->sortable(),
            TextColumn::make('gpa')->sortable(),
            IconColumn::make('is_enabled')->boolean(),
            TextColumn::make('sort_order')->sortable(),
        ])->actions([Tables\Actions\EditAction::make()])->bulkActions([]);
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ListHonorStudents::route('/'), 'create' => Pages\CreateHonorStudent::route('/create'), 'edit' => Pages\EditHonorStudent::route('/{record}/edit')];
    }
}
