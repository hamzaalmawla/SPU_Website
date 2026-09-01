<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Contracts\Content\ProfileAdminServiceInterface;
use App\Contracts\Shared\SlugServiceInterface;
use App\Filament\Resources\FacultyMemberResource\Pages;
use App\Filament\Support\MediaPicker;
use App\Models\Person\FacultyMember;
use App\Models\User\User;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Tabs;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;

class FacultyMemberResource extends Resource
{
    protected static ?string $model = FacultyMember::class;

    protected static ?string $navigationIcon = 'heroicon-o-academic-cap';

    protected static ?int $navigationSort = 2;

    protected static ?string $navigationLabel = 'Faculty Members';

    public static function getRecordTitle(?Model $record): string
    {
        if (! $record instanceof FacultyMember) {
            return self::getModelLabel();
        }

        $locale = app()->getLocale() === 'en' ? 'en' : 'ar';
        $name = $record->translations->firstWhere('locale', $locale)?->full_name;
        $arabicName = $record->translations->firstWhere('locale', 'ar')?->full_name;

        return filled($name) ? trim((string) $name) : (filled($arabicName) ? trim((string) $arabicName) : $record->slug);
    }

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
            ->with(['translations', 'faculty.translations', 'department.translations', 'canonicalPerson']);

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
            Section::make('Canonical person profile')->schema([
                Placeholder::make('canonical_person_status')
                    ->hiddenLabel()
                    ->content(fn (?FacultyMember $record): string => $record?->canonicalPerson
                        ? 'Linked to the canonical People profile: '.$record->canonicalPerson->slug
                        : 'Legacy placement record. Run the unified-person sync before publishing profile changes.'),
            ])->visibleOn('edit'),
            Section::make('Where this staff member appears')->schema([
                Select::make('faculty_id')
                    ->label('Faculty')
                    ->relationship('faculty', 'id', modifyQueryUsing: fn (Builder $query): Builder => self::scopeFacultyOptions($query))
                    ->getOptionLabelFromRecordUsing(fn ($record) => $record->translations->firstWhere('locale', 'ar')?->name ?? $record->translations->first()?->name ?? '#'.$record->id)
                    ->searchable()
                    ->preload()
                    ->native(false)
                    ->live(),
                Select::make('department_id')
                    ->label('Department')
                    ->options(function (Get $get): array {
                        $facultyId = $get('faculty_id');

                        return app(ProfileAdminServiceInterface::class)->departmentOptions(
                            is_numeric($facultyId) ? (int) $facultyId : null,
                            (int) auth()->id(),
                        );
                    })
                    ->searchable()
                    ->preload()
                    ->native(false),
                TextInput::make('sort_order')
                    ->label('Display order')
                    ->numeric()
                    ->default(fn (): int => self::nextSortOrder())
                    ->minValue(0)
                    ->step(10)
                    ->helperText('Lower numbers appear first. You can also reorder staff from the list.'),
                Toggle::make('is_enabled')->label('Visible to editors')->default(true),
                MediaPicker::assetImage('photo_media_id', 'Profile Photo'),
                MediaPicker::assetDocument('cv_media_id', 'CV File'),
            ])->columns(2),
            Tabs::make('Profile text')->tabs([
                Tabs\Tab::make('Arabic')->schema([
                    Hidden::make('translations.ar.locale')->default('ar'),
                    TextInput::make('translations.ar.full_name')->label('Arabic full name')->required()->maxLength(255),
                    TextInput::make('translations.ar.title')->label('Arabic title')->maxLength(255)->placeholder('أ.د، د، م.'),
                    TextInput::make('translations.ar.position')->label('Arabic position')->maxLength(255)->placeholder('عميد، أستاذ مساعد، ...'),
                    Textarea::make('translations.ar.bio')->label('Arabic biography')->rows(4)->columnSpanFull(),
                    TagsInput::make('translations.ar.specializations')->label('Arabic specializations')->separator(',')->columnSpanFull(),
                ])->columns(2),
                Tabs\Tab::make('English')->schema([
                    Hidden::make('translations.en.locale')->default('en'),
                    TextInput::make('translations.en.full_name')->label('English full name')->required()->maxLength(255)->live(onBlur: true),
                    TextInput::make('translations.en.title')->label('English title')->maxLength(255)->placeholder('Prof., Dr., Eng.'),
                    TextInput::make('translations.en.position')->label('English position')->maxLength(255)->placeholder('Dean, Assistant Professor, ...'),
                    Textarea::make('translations.en.bio')->label('English biography')->rows(4)->columnSpanFull(),
                    TagsInput::make('translations.en.specializations')->label('English specializations')->separator(',')->columnSpanFull(),
                ])->columns(2),
            ])->columnSpanFull(),
            Section::make('Contact details')->schema([
                TextInput::make('email')->email()->maxLength(255),
                TextInput::make('phone')->maxLength(255),
                TextInput::make('office_location')->maxLength(255),
            ])->columns(2)->collapsible()->collapsed(),
            Section::make('Advanced URL')->schema([
                Placeholder::make('computed_profile_url')
                    ->label('Profile URL')
                    ->content(fn (Get $get): string => self::profilePathFromForm($get)),
                TextInput::make('slug')
                    ->label('Profile URL slug')
                    ->maxLength(255)
                    ->alphaDash()
                    ->unique(ignoreRecord: true)
                    ->helperText('Leave blank and it will be generated from the English name.'),
            ])->columns(2)->collapsible()->collapsed(),
            Section::make('Social Links')->schema([
                TextInput::make('social_links.linkedin')->label('LinkedIn URL')->url()->maxLength(255),
                TextInput::make('social_links.scholar')->label('Google Scholar URL')->url()->maxLength(255),
                TextInput::make('social_links.orcid')->label('ORCID URL')->url()->maxLength(255),
                TextInput::make('social_links.researchgate')->label('ResearchGate URL')->url()->maxLength(255),
                TextInput::make('social_links.twitter')->label('Twitter/X URL')->url()->maxLength(255),
            ])->columns(2)->collapsible()->collapsed(),
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
            TextColumn::make('sort_order')->label('Order')->sortable(),
            TextColumn::make('slug')->searchable()->sortable(),
            TextColumn::make('translations.full_name')
                ->label('Name')
                ->searchable(query: fn (Builder $query, string $search): Builder => $query->whereHas(
                    'translations',
                    fn (Builder $q): Builder => $q->where('full_name', 'like', "%{$search}%")
                ))
                ->limit(40),
            TextColumn::make('translations.title')->label('Title')->limit(20),
            TextColumn::make('translations.position')->label('Position')->limit(20),
            TextColumn::make('canonicalPerson.slug')
                ->label('Canonical profile')
                ->placeholder('Not linked')
                ->url(fn (FacultyMember $record): ?string => $record->person_id !== null && Gate::allows('manage-pages')
                    ? PersonResource::getUrl('edit', ['record' => $record->person_id])
                    : null),
            TextColumn::make('faculty.translations.name')->label('Faculty')->limit(30),
            IconColumn::make('is_enabled')->boolean(),
            TextColumn::make('publication_status')->badge()->sortable(),
            TextColumn::make('updated_at')->dateTime()->sortable(),
        ])->actions([
            Tables\Actions\EditAction::make(),
            Tables\Actions\ViewAction::make(),
        ])->bulkActions([])
            ->defaultSort('sort_order')
            ->reorderable('sort_order');
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

    /** @param array<string, mixed> $data @return array<string, mixed> */
    public static function prepareFacultyMemberFormData(array $data, ?int $ignoreId = null): array
    {
        data_set($data, 'translations.ar.locale', 'ar');
        data_set($data, 'translations.en.locale', 'en');

        if (trim((string) ($data['slug'] ?? '')) === '') {
            $data['slug'] = self::generateSlug($data, $ignoreId);
        }

        if (! is_numeric($data['sort_order'] ?? null)) {
            $data['sort_order'] = self::nextSortOrder();
        }

        return $data;
    }

    /** @param array<string, mixed> $data */
    private static function generateSlug(array $data, ?int $ignoreId): string
    {
        $englishName = trim((string) data_get($data, 'translations.en.full_name', ''));

        return app(SlugServiceInterface::class)->generate($englishName !== '' ? $englishName : 'faculty-member', FacultyMember::class, 'en', $ignoreId);
    }

    private static function profilePathFromForm(Get $get): string
    {
        $slug = trim((string) $get('slug'));
        if ($slug === '') {
            $slug = Str::slug((string) $get('translations.en.full_name'));
        }

        return $slug !== '' ? self::profilePath($slug) : 'Generated after entering the English name';
    }

    private static function profilePath(string $slug): string
    {
        return '/about/profile/'.$slug;
    }

    private static function nextSortOrder(): int
    {
        return app(ProfileAdminServiceInterface::class)->nextFacultyMemberSortOrder();
    }
}
