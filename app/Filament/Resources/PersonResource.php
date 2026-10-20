<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Contracts\Content\ProfileAdminServiceInterface;
use App\Contracts\Shared\SlugServiceInterface;
use App\Filament\Resources\Concerns\AuthorizesThroughPermissions;
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
use Illuminate\Support\Str;

class PersonResource extends Resource
{
    use AuthorizesThroughPermissions;

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

    protected static function permissionComponent(): string
    {
        return 'people';
    }

    public static function getModelLabel(): string
    {
        return __('admin_resources.person.model');
    }

    public static function getPluralModelLabel(): string
    {
        return __('admin_resources.person.plural_model');
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
            Section::make(__('admin_resources.person.where_this_person_appears'))->schema([
                Select::make('category')->nullable()->options([
                    'dean' => __('admin_resources.person.dean_shown_on_about_leadership'),
                    'rector' => __('admin_resources.person.rector_main_leadership_profile'),
                    'vice_president' => __('admin_resources.person.vice_president_leadership_profile'),
                    'council' => __('admin_resources.person.council_member_leadership_profile'),
                    'director' => __('admin_resources.person.director_profile_only'),
                ])->native(false)->helperText(__('admin_resources.person.legacy_use_appointments_below_instead')),
                Select::make('faculty_scope_slug')
                    ->label(__('admin_resources.person.faculty_scope'))
                    ->helperText(__('admin_resources.person.legacy_field_for_faculty_filtering'))
                    ->options(self::facultyOptions())
                    ->searchable()
                    ->native(false),
                TextInput::make('sort_order')
                    ->label(__('admin_resources.person.display_order'))
                    ->numeric()
                    ->default(fn (): int => self::nextSortOrder())
                    ->minValue(0)
                    ->step(10)
                    ->helperText(__('admin_resources.person.lower_numbers_appear_first_you_can_also_reorder_people_from')),
                Toggle::make('is_enabled')->label(__('admin_resources.person.visible_to_editors'))->default(true),
            ])->columns(2),
            Section::make(__('admin_resources.person.placements_cards'))->schema([
                Repeater::make('appointments')->schema([
                    Select::make('type')->required()->options([
                        'rector' => __('admin_resources.person.rector'),
                        'vice_president' => __('admin_resources.person.vice_president'),
                        'dean' => __('admin_resources.person.dean'),
                        'council' => __('admin_resources.person.council_member'),
                        'director' => __('admin_resources.person.director'),
                        'faculty_member' => __('admin_resources.person.faculty_member'),
                        'researcher' => __('admin_resources.person.researcher'),
                    ])->native(false),
                    Select::make('faculty_id')
                        ->label(__('admin_resources.person.faculty'))
                        ->options(fn (): array => self::facultyIdOptions())
                        ->searchable()
                        ->native(false)
                        ->nullable(),
                    TextInput::make('role_override')
                        ->label(__('admin_resources.person.role_override'))
                        ->helperText(__('admin_resources.person.e_g_dean_of_medicine_or_professor_of_surgery'))
                        ->nullable()
                        ->maxLength(255),
                    TextInput::make('sort_order')->numeric()->default(0)->minValue(0),
                    Toggle::make('is_enabled')->default(true),
                ])->columns(2)->defaultItems(0)->addActionLabel(__('admin_resources.person.add_placement')),
            ])->collapsible()->columnSpanFull(),
            Tabs::make('Profile text')->tabs([
                Tabs\Tab::make('Arabic')->label(__('admin_resources.person.arabic'))->schema([
                    Hidden::make('translations.ar.locale')->default('ar'),
                    TextInput::make('translations.ar.name')->label(__('admin_resources.person.arabic_name'))->required()->maxLength(255),
                    TextInput::make('translations.ar.role')->label(__('admin_resources.person.arabic_role'))->required()->maxLength(255),
                    Textarea::make('translations.ar.bio')->label(__('admin_resources.person.arabic_biography'))->rows(4)->columnSpanFull(),
                    Textarea::make('translations.ar.quote')->label(__('admin_resources.person.arabic_quote'))->rows(3)->columnSpanFull(),
                ])->columns(2),
                Tabs\Tab::make('English')->label(__('admin_resources.person.english'))->schema([
                    Hidden::make('translations.en.locale')->default('en'),
                    TextInput::make('translations.en.name')->label(__('admin_resources.person.english_name'))->required()->maxLength(255)->live(onBlur: true),
                    TextInput::make('translations.en.role')->label(__('admin_resources.person.english_role'))->required()->maxLength(255),
                    Textarea::make('translations.en.bio')->label(__('admin_resources.person.english_biography'))->rows(4)->columnSpanFull(),
                    Textarea::make('translations.en.quote')->label(__('admin_resources.person.english_quote'))->rows(3)->columnSpanFull(),
                ])->columns(2),
            ])->columnSpanFull(),
            Section::make(__('admin_resources.person.media'))->schema([
                MediaPicker::assetImage('photo_media_id', __('admin_resources.person.profile_photo')),
                MediaPicker::assetDocument('cv_media_id', 'CV Document'),
            ])->columns(2)->collapsible()->collapsed(),
            Section::make(__('admin_resources.person.contact_details'))->schema([
                TextInput::make('email')->email()->maxLength(255),
                TextInput::make('phone')->maxLength(255),
                TextInput::make('office_location')->maxLength(255),
                TextInput::make('orcid_url')->label(__('admin_resources.person.orcid_url'))->url()->maxLength(255),
                TextInput::make('scholar_url')->label(__('admin_resources.person.google_scholar_url'))->url()->maxLength(255),
                Hidden::make('profile_url'),
            ])->columns(2)->collapsible()->collapsed(),
            Section::make(__('admin_resources.person.advanced_url_and_titles'))->schema([
                Placeholder::make('computed_profile_url')
                    ->label(__('admin_resources.person.profile_url'))
                    ->content(fn (Get $get): string => self::profilePathFromForm($get)),
                TextInput::make('slug')
                    ->label(__('admin_resources.person.profile_url_slug'))
                    ->maxLength(255)
                    ->alphaDash()
                    ->unique(ignoreRecord: true)
                    ->helperText(__('admin_resources.person.leave_blank_and_it_will_be_generated_from_the_english_name')),
                TextInput::make('title')->maxLength(255)->placeholder(__('admin_resources.person.prof_dr_etc')),
                TextInput::make('position')->maxLength(255)->placeholder(__('admin_resources.person.rector_dean_etc')),
            ])->columns(2)->collapsible()->collapsed(),
            Section::make(__('admin_resources.person.social_links'))->schema([
                TextInput::make('social_links.linkedin')->label(__('admin_resources.person.linkedin_url'))->url()->maxLength(255),
                TextInput::make('social_links.scholar')->label(__('admin_resources.person.google_scholar_url'))->url()->maxLength(255),
                TextInput::make('social_links.orcid')->label(__('admin_resources.person.orcid_url'))->url()->maxLength(255),
                TextInput::make('social_links.researchgate')->label(__('admin_resources.person.researchgate_url'))->url()->maxLength(255),
                TextInput::make('social_links.twitter')->label(__('admin_resources.person.twitter_x_url'))->url()->maxLength(255),
            ])->columns(2)->collapsible()->collapsed(),
            Section::make(__('admin_resources.person.education'))->schema([
                Repeater::make('educations')->schema([
                    TextInput::make('id')->hidden(),
                    TextInput::make('sort_order')->numeric()->default(0),
                    Toggle::make('is_enabled')->default(true),
                    Repeater::make('translations')->schema([
                        Select::make('locale')->required()->options(['ar' => __('admin_resources.person.arabic'), 'en' => __('admin_resources.person.english')])->disableOptionsWhenSelectedInSiblingRepeaterItems(),
                        TextInput::make('degree')->required()->maxLength(255),
                        TextInput::make('institution')->maxLength(255),
                        TextInput::make('field_of_study')->maxLength(255),
                        TextInput::make('year_start')->numeric()->minValue(1900)->maxValue(2100),
                        TextInput::make('year_end')->numeric()->minValue(1900)->maxValue(2100)->gte('year_start'),
                        Textarea::make('description')->rows(2),
                    ])->columns(2)->default([['locale' => 'ar'], ['locale' => 'en']])->minItems(2)->maxItems(2)->columnSpanFull(),
                ])->columns(2)->defaultItems(0)->addActionLabel(__('admin_resources.person.add_education'))->columnSpanFull(),
            ])->collapsible()->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('sort_order')->label(__('admin_resources.person.order'))->sortable(),
            TextColumn::make('slug')->searchable()->sortable(),
            TextColumn::make('category')->badge()->sortable(),
            TextColumn::make('faculty_scope_slug')->label(__('admin_resources.person.faculty'))->badge()->sortable(),
            TextColumn::make('translations.name')
                ->label(__('admin_resources.person.names'))
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
                'rector' => __('admin_resources.person.rector'),
                'vice_president' => __('admin_resources.person.vice_president'),
                'dean' => __('admin_resources.person.dean'),
                'director' => __('admin_resources.person.director'),
                'council' => __('admin_resources.person.council_member'),
            ]),
            Tables\Filters\SelectFilter::make('faculty_scope_slug')->label(__('admin_resources.person.faculty'))->options(self::facultyOptions()),
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
            'medicine' => __('admin_resources.person.medicine'),
            'dentistry' => __('admin_resources.person.dentistry'),
            'pharmacy' => __('admin_resources.person.pharmacy'),
            'artificial-intelligence' => __('admin_resources.person.artificial_intelligence_engineering'),
            'building-construction-engineering' => __('admin_resources.person.building_and_construction_engineering'),
            'petroleum' => __('admin_resources.person.petroleum_engineering'),
            'business-administration' => __('admin_resources.person.business_administration'),
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

        return $slug !== '' ? self::profilePath($slug) : __('admin_resources.person.generated_after_entering_the_english_name');
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
