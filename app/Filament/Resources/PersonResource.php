<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Contracts\Content\ProfileAdminServiceInterface;
use App\Contracts\Shared\SlugServiceInterface;
use App\Filament\Resources\PersonResource\Pages;
use App\Filament\Support\MediaPicker;
use App\Models\Person\Person;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Tabs;
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

class PersonResource extends Resource
{
    protected static ?string $model = Person::class;

    protected static ?string $navigationIcon = 'heroicon-o-user-group';

    protected static ?int $navigationSort = 1;

    public static function getRecordTitle(?Model $record): string
    {
        if (! $record instanceof Person) {
            return self::getModelLabel();
        }

        $locale = app()->getLocale() === 'en' ? 'en' : 'ar';
        $name = $record->translations->firstWhere('locale', $locale)?->name;
        $arabicName = $record->translations->firstWhere('locale', 'ar')?->name;

        return filled($name) ? trim((string) $name) : (filled($arabicName) ? trim((string) $arabicName) : $record->slug);
    }

    public static function canAccess(): bool
    {
        return Gate::allows('manage-pages');
    }

    public static function getNavigationGroup(): ?string
    {
        return __('admin.navigation.groups.about');
    }

    public static function getNavigationLabel(): string
    {
        return __('admin.navigation.items.people');
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with(['translations', 'appointments.translations']);
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Section::make('Where this person appears')->schema([
                Select::make('category')->nullable()->options([
                    'dean' => 'Dean - shown on About Leadership',
                    'rector' => 'Rector - main leadership profile',
                    'vice_president' => 'Vice President - leadership profile',
                    'council' => 'Council Member - leadership profile',
                    'director' => 'Director - profile only',
                ])->native(false)->helperText('Legacy: use Appointments below instead.'),
                Select::make('faculty_scope_slug')
                    ->label('Faculty scope')
                    ->helperText('Legacy field for faculty filtering.')
                    ->options(self::facultyOptions())
                    ->searchable()
                    ->native(false),
                TextInput::make('sort_order')
                    ->label('Display order')
                    ->numeric()
                    ->default(fn (): int => self::nextSortOrder())
                    ->minValue(0)
                    ->step(10)
                    ->helperText('Lower numbers appear first. You can also reorder people from the list.'),
                Toggle::make('is_enabled')->label('Visible to editors')->default(true),
            ])->columns(2),
            Section::make('Placements (cards)')->schema([
                Repeater::make('appointments')->schema([
                    Select::make('type')->required()->options([
                        'rector' => 'Rector',
                        'vice_president' => 'Vice President',
                        'dean' => 'Dean',
                        'council' => 'Council Member',
                        'director' => 'Director',
                        'faculty_member' => 'Faculty Member',
                        'researcher' => 'Researcher',
                    ])->native(false),
                    Select::make('faculty_id')
                        ->label('Faculty')
                        ->options(fn (): array => self::facultyIdOptions())
                        ->searchable()
                        ->native(false)
                        ->nullable(),
                    TextInput::make('role_override')
                        ->label('Role override')
                        ->helperText('e.g., "Dean of Medicine" or "Professor of Surgery"')
                        ->nullable()
                        ->maxLength(255),
                    TextInput::make('sort_order')->numeric()->default(0)->minValue(0),
                    Toggle::make('is_enabled')->default(true),
                ])->columns(2)->defaultItems(0)->addActionLabel('Add Placement'),
            ])->collapsible()->columnSpanFull(),
            Tabs::make('Profile text')->tabs([
                Tabs\Tab::make('Arabic')->schema([
                    Hidden::make('translations.ar.locale')->default('ar'),
                    TextInput::make('translations.ar.name')->label('Arabic name')->required()->maxLength(255),
                    TextInput::make('translations.ar.role')->label('Arabic role')->required()->maxLength(255),
                    Textarea::make('translations.ar.bio')->label('Arabic biography')->rows(4)->columnSpanFull(),
                    Textarea::make('translations.ar.quote')->label('Arabic quote')->rows(3)->columnSpanFull(),
                ])->columns(2),
                Tabs\Tab::make('English')->schema([
                    Hidden::make('translations.en.locale')->default('en'),
                    TextInput::make('translations.en.name')->label('English name')->required()->maxLength(255)->live(onBlur: true),
                    TextInput::make('translations.en.role')->label('English role')->required()->maxLength(255),
                    Textarea::make('translations.en.bio')->label('English biography')->rows(4)->columnSpanFull(),
                    Textarea::make('translations.en.quote')->label('English quote')->rows(3)->columnSpanFull(),
                ])->columns(2),
            ])->columnSpanFull(),
            Section::make('Media')->schema([
                MediaPicker::assetImage('photo_media_id', 'Profile Photo'),
                MediaPicker::assetDocument('cv_media_id', 'CV Document'),
            ])->columns(2)->collapsible()->collapsed(),
            Section::make('Contact details')->schema([
                TextInput::make('email')->email()->maxLength(255),
                TextInput::make('phone')->maxLength(255),
                TextInput::make('office_location')->maxLength(255),
                TextInput::make('orcid_url')->label('ORCID URL')->url()->maxLength(255),
                TextInput::make('scholar_url')->label('Google Scholar URL')->url()->maxLength(255),
                Hidden::make('profile_url'),
            ])->columns(2)->collapsible()->collapsed(),
            Section::make('Advanced URL and titles')->schema([
                Placeholder::make('computed_profile_url')
                    ->label('Profile URL')
                    ->content(fn (Get $get): string => self::profilePathFromForm($get)),
                TextInput::make('slug')
                    ->label('Profile URL slug')
                    ->maxLength(255)
                    ->alphaDash()
                    ->unique(ignoreRecord: true)
                    ->helperText('Leave blank and it will be generated from the English name.'),
                TextInput::make('title')->maxLength(255)->placeholder('Prof., Dr., etc.'),
                TextInput::make('position')->maxLength(255)->placeholder('Rector, Dean, etc.'),
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
            TextColumn::make('category')->badge()->sortable(),
            TextColumn::make('faculty_scope_slug')->label('Faculty')->badge()->sortable(),
            TextColumn::make('translations.name')
                ->label('Names')
                ->searchable(query: fn (Builder $query, string $search): Builder => $query->whereHas(
                    'translations',
                    fn (Builder $q): Builder => $q->where('name', 'like', "%{$search}%")
                ))
                ->listWithLineBreaks()
                ->limit(40),
            IconColumn::make('is_enabled')->boolean(),
            TextColumn::make('publication_status')->badge()->sortable(),
            TextColumn::make('updated_at')->dateTime()->sortable(),
        ])->filters([
            Tables\Filters\SelectFilter::make('category')->options([
                'rector' => 'Rector',
                'vice_president' => 'Vice President',
                'dean' => 'Dean',
                'director' => 'Director',
                'council' => 'Council Member',
            ]),
            Tables\Filters\SelectFilter::make('faculty_scope_slug')->label('Faculty')->options(self::facultyOptions()),
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
            'index' => Pages\ListPeople::route('/'),
            'create' => Pages\CreatePerson::route('/create'),
            'edit' => Pages\EditPerson::route('/{record}/edit'),
            'view' => Pages\ViewPerson::route('/{record}'),
        ];
    }

    /** @return array<string, string> */
    private static function facultyOptions(): array
    {
        return [
            'medicine' => 'Medicine',
            'dentistry' => 'Dentistry',
            'pharmacy' => 'Pharmacy',
            'artificial-intelligence' => 'Artificial Intelligence Engineering',
            'building-construction-engineering' => 'Building and Construction Engineering',
            'petroleum' => 'Petroleum Engineering',
            'business-administration' => 'Business Administration',
        ];
    }

    /** @return array<int, string> */
    private static function facultyIdOptions(): array
    {
        return app(ProfileAdminServiceInterface::class)->facultyOptions((int) auth()->id());
    }

    /** @param array<string, mixed> $data @return array<string, mixed> */
    public static function preparePersonFormData(array $data, ?int $ignoreId = null): array
    {
        data_set($data, 'translations.ar.locale', 'ar');
        data_set($data, 'translations.en.locale', 'en');

        if (trim((string) ($data['slug'] ?? '')) === '') {
            $data['slug'] = self::generateSlug($data, $ignoreId);
        }

        $data['profile_url'] = self::profilePath((string) $data['slug']);

        if (! is_numeric($data['sort_order'] ?? null)) {
            $data['sort_order'] = self::nextSortOrder();
        }

        return $data;
    }

    /** @param array<string, mixed> $data */
    private static function generateSlug(array $data, ?int $ignoreId): string
    {
        $englishName = trim((string) data_get($data, 'translations.en.name', ''));

        return app(SlugServiceInterface::class)->generate($englishName !== '' ? $englishName : 'person', Person::class, 'en', $ignoreId);
    }

    private static function profilePathFromForm(Get $get): string
    {
        $slug = trim((string) $get('slug'));
        if ($slug === '') {
            $slug = Str::slug((string) $get('translations.en.name'));
        }

        return $slug !== '' ? self::profilePath($slug) : 'Generated after entering the English name';
    }

    private static function profilePath(string $slug): string
    {
        return '/about/profile/'.$slug;
    }

    private static function nextSortOrder(): int
    {
        return app(ProfileAdminServiceInterface::class)->nextPersonSortOrder();
    }
}
