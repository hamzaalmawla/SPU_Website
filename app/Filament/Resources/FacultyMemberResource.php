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
use Filament\Tables\Filters\SelectFilter;
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

    public static function getModelLabel(): string
    {
        return __('admin_resources.faculty_member.model');
    }

    public static function getPluralModelLabel(): string
    {
        return __('admin_resources.faculty_member.plural_model');
    }

    public static function getNavigationLabel(): string
    {
        return __('admin_resources.faculty_member.plural_model');
    }

    public static function getNavigationGroup(): ?string
    {
        return __('admin.navigation.groups.facilities');
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

        $contextScope = request()->query('faculty_scope');
        if (is_string($contextScope) && $contextScope !== '') {
            $query->whereHas('faculty', fn (Builder $facultyQuery): Builder => $facultyQuery
                ->where('faculty_scope_slug', $contextScope)
                ->orWhere('public_slug', $contextScope)
                ->orWhere('slug', $contextScope));
        }

        return $query;
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Section::make(__('admin_resources.faculty_member.canonical_person_profile'))->schema([
                Placeholder::make('canonical_person_status')
                    ->hiddenLabel()
                    ->content(fn (?FacultyMember $record): string => $record?->canonicalPerson
                        ? 'Linked to the canonical People profile: '.$record->canonicalPerson->slug
                        : 'Legacy placement record. Run the unified-person sync before publishing profile changes.'),
            ])->visibleOn('edit'),
            Section::make(__('admin_resources.faculty_member.where_this_staff_member_appears'))->schema([
                Select::make('faculty_id')
                    ->label(__('admin_resources.faculty_member.faculty'))
                    ->relationship('faculty', 'id', modifyQueryUsing: fn (Builder $query): Builder => self::scopeFacultyOptions($query))
                    ->getOptionLabelFromRecordUsing(fn ($record) => $record->translations->firstWhere('locale', 'ar')?->name ?? $record->translations->first()?->name ?? '#'.$record->id)
                    ->searchable()
                    ->preload()
                    ->native(false)
                    ->default(fn (): ?int => self::contextFacultyId())
                    ->required()
                    ->disabled(fn (): bool => auth()->user()?->role_slug === 'faculty_editor')
                    ->dehydrated()
                    ->live(),
                Select::make('department_id')
                    ->label(__('admin_resources.faculty_member.department'))
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
                    ->label(__('admin_resources.faculty_member.display_order'))
                    ->numeric()
                    ->default(fn (): int => self::nextSortOrder())
                    ->minValue(0)
                    ->step(10)
                    ->helperText(__('admin_resources.faculty_member.lower_numbers_appear_first_you_can_also_reorder_staff_from_t')),
                Toggle::make('is_enabled')->label(__('admin_resources.faculty_member.eligible_for_public_display_after_publication'))->default(true),
                MediaPicker::assetImage('photo_media_id', __('admin_resources.faculty_member.profile_photo')),
                MediaPicker::assetDocument('cv_media_id', 'CV File'),
            ])->columns(2),
            Tabs::make('Profile text')->tabs([
                Tabs\Tab::make('Arabic')->label(__('admin_resources.faculty_member.arabic'))->schema([
                    Hidden::make('translations.ar.locale')->default('ar'),
                    TextInput::make('translations.ar.full_name')->label(__('admin_resources.faculty_member.arabic_full_name'))->required()->maxLength(255),
                    TextInput::make('translations.ar.title')->label(__('admin_resources.faculty_member.arabic_title'))->maxLength(255)->placeholder('أ.د، د، م.'),
                    TextInput::make('translations.ar.position')->label(__('admin_resources.faculty_member.arabic_position'))->maxLength(255)->placeholder('عميد، أستاذ مساعد، ...'),
                    Textarea::make('translations.ar.bio')->label(__('admin_resources.faculty_member.arabic_biography'))->rows(4)->columnSpanFull(),
                    TagsInput::make('translations.ar.specializations')->label(__('admin_resources.faculty_member.arabic_specializations'))->separator(',')->columnSpanFull(),
                ])->columns(2),
                Tabs\Tab::make('English')->label(__('admin_resources.faculty_member.english'))->schema([
                    Hidden::make('translations.en.locale')->default('en'),
                    TextInput::make('translations.en.full_name')->label(__('admin_resources.faculty_member.english_full_name'))->required()->maxLength(255)->live(onBlur: true),
                    TextInput::make('translations.en.title')->label(__('admin_resources.faculty_member.english_title'))->maxLength(255)->placeholder(__('admin_resources.faculty_member.prof_dr_eng')),
                    TextInput::make('translations.en.position')->label(__('admin_resources.faculty_member.english_position'))->maxLength(255)->placeholder(__('admin_resources.faculty_member.dean_assistant_professor')),
                    Textarea::make('translations.en.bio')->label(__('admin_resources.faculty_member.english_biography'))->rows(4)->columnSpanFull(),
                    TagsInput::make('translations.en.specializations')->label(__('admin_resources.faculty_member.english_specializations'))->separator(',')->columnSpanFull(),
                ])->columns(2),
            ])->columnSpanFull(),
            Section::make(__('admin_resources.faculty_member.contact_details'))->schema([
                TextInput::make('email')->email()->maxLength(255),
                TextInput::make('phone')->maxLength(255),
                TextInput::make('office_location')->maxLength(255),
            ])->columns(2)->collapsible()->collapsed(),
            Section::make(__('admin_resources.faculty_member.advanced_url'))->schema([
                Placeholder::make('computed_profile_url')
                    ->label(__('admin_resources.faculty_member.profile_url'))
                    ->content(fn (Get $get): string => self::profilePathFromForm($get)),
                TextInput::make('slug')
                    ->label(__('admin_resources.faculty_member.profile_url_slug'))
                    ->maxLength(255)
                    ->alphaDash()
                    ->unique(ignoreRecord: true)
                    ->helperText(__('admin_resources.faculty_member.leave_blank_and_it_will_be_generated_from_the_english_name')),
            ])->columns(2)->collapsible()->collapsed(),
            Section::make(__('admin_resources.faculty_member.social_links'))->schema([
                TextInput::make('social_links.linkedin')->label(__('admin_resources.faculty_member.linkedin_url'))->url()->maxLength(255),
                TextInput::make('social_links.scholar')->label(__('admin_resources.faculty_member.google_scholar_url'))->url()->maxLength(255),
                TextInput::make('social_links.orcid')->label(__('admin_resources.faculty_member.orcid_url'))->url()->maxLength(255),
                TextInput::make('social_links.researchgate')->label(__('admin_resources.faculty_member.researchgate_url'))->url()->maxLength(255),
                TextInput::make('social_links.twitter')->label(__('admin_resources.faculty_member.twitter_x_url'))->url()->maxLength(255),
            ])->columns(2)->collapsible()->collapsed(),
            Section::make(__('admin_resources.faculty_member.education'))->schema([
                Repeater::make('educations')->schema([
                    TextInput::make('id')->hidden(),
                    TextInput::make('sort_order')->numeric()->default(0),
                    Toggle::make('is_enabled')->default(true),
                    Repeater::make('translations')->schema([
                        Select::make('locale')->required()->options(['ar' => __('admin_resources.faculty_member.arabic'), 'en' => __('admin_resources.faculty_member.english')])->disableOptionsWhenSelectedInSiblingRepeaterItems(),
                        TextInput::make('degree')->required()->maxLength(255),
                        TextInput::make('institution')->maxLength(255),
                        TextInput::make('field_of_study')->maxLength(255),
                        TextInput::make('year_start')->numeric()->minValue(1900)->maxValue(2100),
                        TextInput::make('year_end')->numeric()->minValue(1900)->maxValue(2100)->gte('year_start'),
                        Textarea::make('description')->rows(2),
                    ])->columns(2)->default([['locale' => 'ar'], ['locale' => 'en']])->minItems(2)->maxItems(2)->columnSpanFull(),
                ])->columns(2)->defaultItems(0)->addActionLabel(__('admin_resources.faculty_member.add_education'))->columnSpanFull(),
            ])->collapsible()->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('sort_order')->label(__('admin_resources.faculty_member.order'))->sortable(),
            TextColumn::make('slug')->searchable()->sortable(),
            TextColumn::make('translations.full_name')
                ->label(__('admin_resources.faculty_member.name'))
                ->searchable(query: fn (Builder $query, string $search): Builder => $query->whereHas(
                    'translations',
                    fn (Builder $q): Builder => $q->where('full_name', 'like', "%{$search}%")
                ))
                ->limit(40),
            TextColumn::make('translations.title')->label(__('admin_resources.faculty_member.title'))->limit(20),
            TextColumn::make('translations.position')->label(__('admin_resources.faculty_member.position'))->limit(20),
            TextColumn::make('canonicalPerson.slug')
                ->label(__('admin_resources.faculty_member.canonical_profile'))
                ->placeholder(__('admin_resources.faculty_member.not_linked'))
                ->url(fn (FacultyMember $record): ?string => $record->person_id !== null && Gate::allows('manage-pages')
                    ? PersonResource::getUrl('edit', ['record' => $record->person_id])
                    : null),
            TextColumn::make('faculty.translations.name')->label(__('admin_resources.faculty_member.faculty'))->limit(30),
            IconColumn::make('is_enabled')->boolean(),
            TextColumn::make('publication_status')->badge()->sortable(),
            TextColumn::make('updated_at')->dateTime()->sortable(),
        ])->filters([
            SelectFilter::make('faculty_id')
                ->label(__('admin_resources.faculty_member.faculty'))
                ->options(fn (): array => app(ProfileAdminServiceInterface::class)->facultyOptions((int) auth()->id())),
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

    private static function contextFacultyId(): ?int
    {
        $scope = request()->query('faculty_scope');
        if (! is_string($scope) || $scope === '' || ! is_numeric(auth()->id())) {
            $user = auth()->user();
            $scope = $user instanceof User && $user->role_slug === 'faculty_editor'
                ? (string) $user->faculty_scope_slug
                : '';
        }

        return $scope !== ''
            ? app(ProfileAdminServiceInterface::class)->facultyIdForScope($scope, (int) auth()->id())
            : null;
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

        return $slug !== '' ? self::profilePath($slug) : __('admin_resources.faculty_member.generated_after_entering_the_english_name');
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
