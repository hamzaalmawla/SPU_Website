<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Contracts\Auth\AdminAccessServiceInterface;
use App\Contracts\Cms\CmsWorkflowServiceInterface;
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
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
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
        return Gate::allows('viewAny', FacultyMember::class);
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
            ->with(['translations', 'faculty.translations', 'department.translations', 'leadershipRoles']);

        $user = auth()->user();
        if ($user instanceof User && $user->hasFacultyLimit()) {
            self::whereFacultyScope($query, app(AdminAccessServiceInterface::class)->facultyScopeValues($user));
        }

        return $query;
    }

    /**
     * Limits a member query to the given faculties, matched by any of their slugs.
     *
     * @param  list<string>|string  $scopes
     */
    public static function whereFacultyScope(Builder $query, array|string $scopes): Builder
    {
        $scopes = (array) $scopes;

        return $query->whereHas('faculty', fn (Builder $facultyQuery): Builder => $facultyQuery
            ->whereIn('faculty_scope_slug', $scopes)
            ->orWhereIn('public_slug', $scopes)
            ->orWhereIn('slug', $scopes));
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            // The fields a faculty editor needs to add someone. Everything
            // else stays available below, collapsed, for technical staff.
            Section::make(__('admin_resources.faculty_member.basic_details'))
                ->description(__('admin_resources.faculty_member.basic_details_description'))
                ->schema([
                    Select::make('faculty_id')
                        ->label(__('admin_resources.faculty_member.faculty'))
                        ->options(fn (): array => app(ProfileAdminServiceInterface::class)->facultyOptions((int) auth()->id()))
                        ->searchable()
                        ->preload()
                        ->native(false)
                        ->default(fn (mixed $livewire): ?int => self::contextFacultyId($livewire))
                        // Empty for a university leader who belongs to no faculty.
                        ->required(fn (): bool => auth()->user()?->hasFacultyLimit() ?? true)
                        ->helperText(fn (mixed $livewire): ?string => self::contextFacultyId($livewire) !== null ? __('admin_resources.faculty_member.faculty_set_automatically') : __('admin_resources.faculty_member.faculty_optional_for_leaders'))
                        ->disabled(fn (mixed $livewire): bool => self::contextFacultyId($livewire) !== null)
                        ->dehydrated()
                        ->live()
                        ->columnSpanFull(),
                    Hidden::make('translations.ar.locale')->default('ar'),
                    Hidden::make('translations.en.locale')->default('en'),
                    TextInput::make('translations.ar.full_name')->label(__('admin_resources.faculty_member.name_arabic'))->required()->maxLength(255),
                    TextInput::make('translations.en.full_name')->label(__('admin_resources.faculty_member.name_english'))->required()->maxLength(255)->live(onBlur: true),
                    TextInput::make('email')->label(__('admin_resources.faculty_member.email'))->email()->maxLength(255),
                    TextInput::make('social_links.linkedin')->label(__('admin_resources.faculty_member.linkedin_url'))->url()->maxLength(255)->placeholder('https://www.linkedin.com/in/...'),
                    TextInput::make('social_links.orcid')->label(__('admin_resources.faculty_member.orcid_url'))->url()->maxLength(255)->placeholder('https://orcid.org/0000-0000-0000-0000'),
                    TextInput::make('social_links.scopus')->label(__('admin_resources.faculty_member.scopus_url'))->url()->maxLength(255)->placeholder('https://www.scopus.com/authid/detail.uri?authorId=...'),
                    TextInput::make('social_links.researchgate')->label(__('admin_resources.faculty_member.researchgate_url'))->url()->maxLength(255)->placeholder('https://www.researchgate.net/profile/...'),
                    TextInput::make('social_links.scholar')->label(__('admin_resources.faculty_member.google_scholar_url'))->url()->maxLength(255)->placeholder('https://scholar.google.com/citations?user=...'),
                    MediaPicker::assetImage('photo_media_id', __('admin_resources.faculty_member.photo')),
                    MediaPicker::assetDocument('cv_media_id', __('admin_resources.faculty_member.cv')),
                ])->columns(2),
            Section::make(__('admin_resources.faculty_member.additional_details'))
                ->description(__('admin_resources.faculty_member.additional_details_description'))
                ->schema([
                    Section::make(__('admin_resources.faculty_member.where_this_staff_member_appears'))->schema([
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
                        // Optional: only faculties whose study plan has programs offer it.
                        Select::make('specialization_id')
                            ->label(__('admin_resources.faculty_member.specialization'))
                            ->helperText(__('admin_resources.faculty_member.specialization_help'))
                            ->options(fn (Get $get): array => self::specializationOptions($get('faculty_id')))
                            ->hidden(fn (Get $get): bool => self::specializationOptions($get('faculty_id')) === [])
                            ->native(false),
                        TextInput::make('sort_order')
                            ->label(__('admin_resources.faculty_member.display_order'))
                            ->numeric()
                            ->default(fn (): int => self::nextSortOrder())
                            ->minValue(0)
                            ->helperText(__('admin_resources.faculty_member.lower_numbers_appear_first_you_can_also_reorder_staff_from_t')),
                        Toggle::make('is_enabled')->label(__('admin_resources.faculty_member.eligible_for_public_display_after_publication'))->default(true),
                    ])->columns(2),
                    Tabs::make(__('admin_resources.faculty_member.profile_text'))->tabs([
                        Tabs\Tab::make('Arabic')->label(__('admin_resources.faculty_member.arabic'))->schema([
                            TextInput::make('translations.ar.title')->label(__('admin_resources.faculty_member.arabic_title'))->maxLength(255)->placeholder('أ.د، د، م.'),
                            TextInput::make('translations.ar.position')->label(__('admin_resources.faculty_member.arabic_position'))->maxLength(255)->placeholder('عميد، أستاذ مساعد، ...'),
                            Textarea::make('translations.ar.bio')->label(__('admin_resources.faculty_member.arabic_biography'))->rows(4)->columnSpanFull(),
                            TagsInput::make('translations.ar.specializations')->label(__('admin_resources.faculty_member.arabic_specializations'))->separator(',')->columnSpanFull(),
                        ])->columns(2),
                        Tabs\Tab::make('English')->label(__('admin_resources.faculty_member.english'))->schema([
                            TextInput::make('translations.en.title')->label(__('admin_resources.faculty_member.english_title'))->maxLength(255)->placeholder(__('admin_resources.faculty_member.prof_dr_eng')),
                            TextInput::make('translations.en.position')->label(__('admin_resources.faculty_member.english_position'))->maxLength(255)->placeholder(__('admin_resources.faculty_member.dean_assistant_professor')),
                            Textarea::make('translations.en.bio')->label(__('admin_resources.faculty_member.english_biography'))->rows(4)->columnSpanFull(),
                            TagsInput::make('translations.en.specializations')->label(__('admin_resources.faculty_member.english_specializations'))->separator(',')->columnSpanFull(),
                        ])->columns(2),
                    ])->columnSpanFull(),
                    Section::make(__('admin_resources.faculty_member.contact_details'))->schema([
                        TextInput::make('phone')->label(__('admin_resources.faculty_member.phone'))->maxLength(255),
                        TextInput::make('office_location')->label(__('admin_resources.faculty_member.office'))->maxLength(255),
                    ])->columns(2),
                    Section::make(__('admin_resources.faculty_member.other_links'))->schema([
                        TextInput::make('social_links.twitter')->label(__('admin_resources.faculty_member.twitter_x_url'))->url()->maxLength(255),
                    ])->columns(2),
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
                    ])->columns(2),
                    Section::make(__('admin_resources.faculty_member.education'))->schema([
                        Repeater::make('educations')->hiddenLabel()->schema([
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
                    ]),
                ])
                ->collapsible()
                ->collapsed()
                ->columnSpanFull(),
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
            TextColumn::make('leadershipRoles.category')
                ->label(__('admin_resources.faculty_member.leadership_role'))
                ->badge()
                ->placeholder('-'),
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
            Tables\Actions\Action::make('delete')
                ->label(__('admin_resources.faculty_member.delete'))
                ->icon('heroicon-m-trash')
                ->color('danger')
                ->requiresConfirmation()
                ->modalHeading(__('admin_resources.faculty_member.delete_heading'))
                ->modalDescription(__('admin_resources.faculty_member.delete_description'))
                ->visible(fn (FacultyMember $record): bool => Gate::allows('delete', $record))
                ->action(fn (FacultyMember $record) => self::deleteMembers([(int) $record->getKey()])),
        ])->bulkActions([
            Tables\Actions\BulkAction::make('publishSelected')
                ->label(__('admin_resources.faculty_member.publish_selected'))
                ->icon('heroicon-m-paper-airplane')
                ->color('success')
                ->requiresConfirmation()
                ->modalHeading(__('admin_resources.faculty_member.publish_heading'))
                ->modalDescription(fn (Collection $records): string => __('admin_resources.faculty_member.publish_description', ['count' => $records->count()]))
                ->visible(fn (): bool => Gate::allows('faculty_members.publish'))
                ->deselectRecordsAfterCompletion()
                ->action(fn (Collection $records) => self::publishMembers($records)),
            Tables\Actions\BulkAction::make('deleteSelected')
                ->label(__('admin_resources.faculty_member.delete_selected'))
                ->icon('heroicon-m-trash')
                ->color('danger')
                ->requiresConfirmation()
                ->modalHeading(__('admin_resources.faculty_member.delete_heading'))
                ->modalDescription(__('admin_resources.faculty_member.delete_description'))
                ->deselectRecordsAfterCompletion()
                ->action(fn (Collection $records) => self::deleteMembers($records->map(fn (FacultyMember $record): int => (int) $record->getKey())->all())),
        ])
            ->defaultSort('sort_order')
            ->reorderable('sort_order');
    }

    /** @param array<int, int> $ids */
    private static function deleteMembers(array $ids): void
    {
        try {
            $count = app(ProfileAdminServiceInterface::class)->deleteFacultyMembers($ids, (int) auth()->id());
        } catch (AuthorizationException) {
            Notification::make()->title(__('admin_resources.faculty_member.delete_failed'))->danger()->send();

            return;
        }

        Notification::make()->title(__('admin_resources.faculty_member.deleted_count', ['count' => $count]))->success()->send();
    }

    /**
     * Publishes the pending draft of each selected member. Members without one
     * stay as they are; members whose draft is not ready are named with why.
     *
     * @param  Collection<int, FacultyMember>  $records
     */
    private static function publishMembers(Collection $records): void
    {
        $members = $records->keyBy(fn (FacultyMember $record): string => 'entity.faculty-member.'.$record->getKey());

        try {
            $result = app(CmsWorkflowServiceInterface::class)->publishMany($members->keys()->all(), (int) auth()->id());
        } catch (AuthorizationException) {
            Notification::make()->title(__('admin_resources.faculty_member.publish_denied'))->danger()->send();

            return;
        }

        $lines = [];
        if ($result->unchanged !== []) {
            $lines[] = __('admin_resources.faculty_member.publish_unchanged', ['count' => count($result->unchanged)]);
        }
        foreach ($result->failed as $targetKey => $errors) {
            $lines[] = self::getRecordTitle($members->get($targetKey)).': '.implode(' ', $errors);
        }

        $notification = Notification::make()
            ->title(__('admin_resources.faculty_member.published_count', ['count' => count($result->published)]))
            ->body($lines !== [] ? implode("\n", $lines) : null);

        ($result->failed !== [] ? $notification->warning()->persistent() : $notification->success())->send();
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

    /**
     * The faculty a new member belongs to without anyone choosing it: the
     * faculty page the form was opened from (for a user with a faculty limit, only when
     * it is one of their faculties), or the limit's only faculty. Null
     * when the editor has to pick one.
     */
    public static function contextFacultyId(mixed $livewire = null): ?int
    {
        $user = auth()->user();
        if (! $user instanceof User) {
            return null;
        }

        $scope = $user->hasFacultyLimit()
            ? (string) app(AdminAccessServiceInterface::class)->facultyForWrite($user, self::contextScope($livewire))
            : self::contextScope($livewire);

        return $scope !== ''
            ? app(ProfileAdminServiceInterface::class)->facultyIdForScope($scope, (int) $user->getKey())
            : null;
    }

    /** The faculty scope a page was opened with (`?faculty_scope=`), kept across Livewire requests. */
    public static function contextScope(mixed $livewire = null): string
    {
        $scope = is_object($livewire) && property_exists($livewire, 'facultyScope') ? $livewire->facultyScope : null;

        return is_string($scope) ? trim($scope) : '';
    }

    /** @return array<string, string> */
    private static function specializationOptions(mixed $facultyId): array
    {
        return app(ProfileAdminServiceInterface::class)->specializationOptions(is_numeric($facultyId) ? (int) $facultyId : null);
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
