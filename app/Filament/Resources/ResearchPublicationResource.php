<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Contracts\Auth\AdminAccessServiceInterface;
use App\Contracts\Research\ResearchPublicationCmsServiceInterface;
use App\DTOs\Research\ResearcherDataDTO;
use App\Filament\Resources\ResearchPublicationResource\Pages;
use App\Filament\Support\MediaPicker;
use App\Models\Research\ResearchPublication;
use App\Models\User\User;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Tabs;
use Filament\Forms\Components\Tabs\Tab;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;

/**
 * The research publications on the website, imported and new, one page at a
 * time. Searching and filtering run in the database, so the list never loads
 * every publication at once.
 */
class ResearchPublicationResource extends Resource
{
    protected static ?string $model = ResearchPublication::class;

    protected static ?string $navigationIcon = 'heroicon-o-document-text';

    protected static ?int $navigationSort = 2;

    public static function getModelLabel(): string
    {
        return __('admin.research_publication.model');
    }

    public static function getPluralModelLabel(): string
    {
        return __('admin.research_publication.plural_model');
    }

    public static function getNavigationLabel(): string
    {
        return __('admin.research_publication.plural_model');
    }

    public static function getNavigationGroup(): ?string
    {
        return __('admin.navigation.groups.research');
    }

    public static function canAccess(): bool
    {
        return Gate::allows('viewAny', ResearchPublication::class);
    }

    public static function getRecordTitle(?Model $record): string
    {
        return $record instanceof ResearchPublication ? self::localizedTitle($record) : self::getModelLabel();
    }

    public static function getEloquentQuery(): Builder
    {
        return self::scopeQueryToCurrentUser(parent::getEloquentQuery())
            ->with(['translations', 'faculty.translations', 'facultyMember.faculty.translations']);
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Tabs::make('research_publication_editor')
                ->tabs([
                    Tab::make(__('admin.research_publication.tabs.content'))
                        ->icon('heroicon-o-pencil-square')
                        ->schema([
                            Placeholder::make('status_notice')
                                ->hiddenLabel()
                                ->content(fn (?ResearchPublication $record): string => $record instanceof ResearchPublication
                                    ? __('admin.research_publication.help.status', ['status' => __('admin.research_publication.statuses.'.self::status($record))])
                                    : __('admin.research_publication.help.new'))
                                ->columnSpanFull(),
                            Repeater::make('translations')
                                ->hiddenLabel()
                                ->itemLabel(fn (array $state): string => __('admin.locales.'.($state['locale'] ?? 'ar')))
                                ->schema([
                                    Hidden::make('locale')->required(),
                                    TextInput::make('title')
                                        ->label(__('admin.research_publication.fields.title'))
                                        ->required()
                                        ->maxLength(255)
                                        ->columnSpanFull()
                                        ->extraInputAttributes(fn (Get $get): array => ['dir' => self::direction($get('locale'))]),
                                    TextInput::make('authors')
                                        ->label(__('admin.research_publication.fields.authors'))
                                        ->helperText(__('admin.research_publication.help.authors_text'))
                                        ->maxLength(1000)
                                        ->extraInputAttributes(fn (Get $get): array => ['dir' => self::direction($get('locale'))]),
                                    TextInput::make('publisher')
                                        ->label(__('admin.research_publication.fields.publisher'))
                                        ->maxLength(255)
                                        ->extraInputAttributes(fn (Get $get): array => ['dir' => self::direction($get('locale'))]),
                                    Textarea::make('excerpt')
                                        ->label(__('admin.research_publication.fields.summary'))
                                        ->helperText(__('admin.research_publication.help.summary'))
                                        ->rows(3)
                                        ->columnSpanFull()
                                        ->extraInputAttributes(fn (Get $get): array => ['dir' => self::direction($get('locale'))]),
                                    Textarea::make('abstract')
                                        ->label(__('admin.research_publication.fields.abstract'))
                                        ->rows(8)
                                        ->columnSpanFull()
                                        ->extraInputAttributes(fn (Get $get): array => ['dir' => self::direction($get('locale'))]),
                                    TextInput::make('citation')
                                        ->label(__('admin.research_publication.fields.citation'))
                                        ->maxLength(1000)
                                        ->columnSpanFull(),
                                    TagsInput::make('keywords')
                                        ->label(__('admin.research_publication.fields.keywords'))
                                        ->columnSpanFull(),
                                ])
                                ->columns(2)
                                ->minItems(2)
                                ->maxItems(2)
                                ->deletable(false)
                                ->addable(false)
                                ->reorderable(false)
                                ->default([['locale' => 'ar'], ['locale' => 'en']])
                                ->collapsible()
                                ->columnSpanFull(),
                        ]),
                    Tab::make(__('admin.research_publication.tabs.details'))
                        ->icon('heroicon-o-information-circle')
                        ->schema([
                            Section::make(__('admin.research_publication.sections.classification'))
                                ->schema([
                                    TextInput::make('research_number')
                                        ->label(__('admin.research_publication.fields.research_number'))
                                        ->helperText(__('admin.research_publication.help.research_number'))
                                        ->maxLength(30),
                                    Select::make('publication_type')
                                        ->label(__('admin.research_publication.fields.publication_type'))
                                        ->options(self::codeOptions('publication_types', ResearchPublication::PUBLICATION_TYPES))
                                        ->native(false),
                                    Select::make('study_type')
                                        ->label(__('admin.research_publication.fields.study_type'))
                                        ->options(self::codeOptions('study_types', ResearchPublication::STUDY_TYPES))
                                        ->native(false),
                                    TextInput::make('issn')
                                        ->label(__('admin.research_publication.fields.issn'))
                                        ->maxLength(30),
                                    Textarea::make('notes')
                                        ->label(__('admin.research_publication.fields.notes'))
                                        ->helperText(__('admin.research_publication.help.notes'))
                                        ->rows(2)
                                        ->columnSpanFull(),
                                ])
                                ->columns(2),
                            Section::make(__('admin.research_publication.sections.researchers'))
                                ->description(__('admin.research_publication.help.researchers'))
                                ->schema([
                                    Repeater::make('researchers')
                                        ->hiddenLabel()
                                        ->schema([
                                            Select::make('researcher_id')
                                                ->label(__('admin.research_publication.fields.researcher_person'))
                                                ->required()
                                                ->searchable()
                                                ->native(false)
                                                ->options(fn (): array => [])
                                                ->getSearchResultsUsing(fn (string $search): array => app(ResearchPublicationCmsServiceInterface::class)->researcherOptions($search))
                                                ->getOptionLabelUsing(fn (mixed $value): ?string => is_numeric($value) ? (app(ResearchPublicationCmsServiceInterface::class)->researcherOptions(null, (int) $value)[(int) $value] ?? null) : null)
                                                ->createOptionForm([
                                                    TextInput::make('full_name')->label(__('admin.research_publication.fields.full_name'))->required()->maxLength(200),
                                                    TextInput::make('email')->label(__('admin.research_publication.fields.email'))->email()->maxLength(150),
                                                    TextInput::make('affiliation')->label(__('admin.research_publication.fields.affiliation'))->maxLength(255),
                                                    Select::make('faculty_member_id')
                                                        ->label(__('admin.research_publication.fields.researcher_faculty_member'))
                                                        ->helperText(__('admin.research_publication.help.researcher_faculty_member'))
                                                        ->searchable()
                                                        ->native(false)
                                                        ->options(fn (): array => [])
                                                        ->getSearchResultsUsing(fn (string $search): array => self::facultyMemberOptions($search)),
                                                ])
                                                ->createOptionUsing(function (array $data): int {
                                                    $user = auth()->user();
                                                    abort_unless($user instanceof User, 403);

                                                    return app(ResearchPublicationCmsServiceInterface::class)->createResearcher(new ResearcherDataDTO(
                                                        fullName: (string) ($data['full_name'] ?? ''),
                                                        email: is_string($data['email'] ?? null) ? $data['email'] : null,
                                                        affiliation: is_string($data['affiliation'] ?? null) ? $data['affiliation'] : null,
                                                        facultyMemberId: is_numeric($data['faculty_member_id'] ?? null) ? (int) $data['faculty_member_id'] : null,
                                                    ), (int) $user->getKey());
                                                })
                                                ->columnSpan(2),
                                            Select::make('role')
                                                ->label(__('admin.research_publication.fields.role'))
                                                ->options(self::codeOptions('roles', ResearchPublication::RESEARCHER_ROLES))
                                                ->default('co_author')
                                                ->required()
                                                ->native(false),
                                            TextInput::make('affiliation_snapshot')
                                                ->label(__('admin.research_publication.fields.affiliation'))
                                                ->maxLength(255),
                                        ])
                                        ->columns(4)
                                        ->defaultItems(0)
                                        ->reorderable()
                                        ->addActionLabel(__('admin.research_publication.fields.researcher_person').' +')
                                        ->columnSpanFull(),
                                ]),
                            Section::make(__('admin.research_publication.sections.attribution'))
                                ->schema([
                                    Select::make('faculty_id')
                                        ->label(__('admin.research_publication.fields.faculty'))
                                        ->helperText(__('admin.research_publication.help.faculty'))
                                        ->native(false)
                                        ->options(fn (): array => app(ResearchPublicationCmsServiceInterface::class)->facultyOptions()),
                                    Select::make('faculty_member_id')
                                        ->label(__('admin.research_publication.fields.researcher'))
                                        ->helperText(__('admin.research_publication.help.researcher'))
                                        ->searchable()
                                        ->native(false)
                                        ->preload(false)
                                        ->required(fn (): bool => auth()->user() instanceof User && auth()->user()->hasFacultyLimit())
                                        ->options(fn (): array => [])
                                        ->getSearchResultsUsing(fn (string $search): array => self::facultyMemberOptions($search))
                                        ->getOptionLabelUsing(fn (mixed $value): ?string => is_numeric($value) ? (self::facultyMemberOptions(null, (int) $value)[(int) $value] ?? null) : null),
                                    TextInput::make('publication_year')
                                        ->label(__('admin.research_publication.fields.year'))
                                        ->numeric()
                                        ->minValue(1950)
                                        ->maxValue((int) date('Y') + 1),
                                    DatePicker::make('published_at')
                                        ->label(__('admin.research_publication.fields.published_at'))
                                        ->helperText(__('admin.research_publication.help.published_at'))
                                        ->native(false),
                                    TextInput::make('doi')
                                        ->label(__('admin.research_publication.fields.doi'))
                                        ->maxLength(255),
                                    TextInput::make('journal_rank')
                                        ->label(__('admin.research_publication.fields.journal_rank'))
                                        ->maxLength(32),
                                    TextInput::make('external_url')
                                        ->label(__('admin.research_publication.fields.external_url'))
                                        ->helperText(__('admin.research_publication.help.external_url'))
                                        ->url()
                                        ->maxLength(255),
                                ])
                                ->columns(2),
                            Section::make(__('admin.research_publication.sections.media'))
                                ->schema([
                                    MediaPicker::assetImage('image_media_id', __('admin.research_publication.fields.image')),
                                    MediaPicker::assetDocument('file_media_id', __('admin.research_publication.fields.file')),
                                ])
                                ->columns(2),
                        ]),
                ])
                ->persistTabInQueryString('editor')
                ->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('localized_title')
                    ->label(__('admin.research_publication.table.title'))
                    ->getStateUsing(fn (ResearchPublication $record): string => self::localizedTitle($record))
                    ->description(fn (ResearchPublication $record): string => self::localizedAuthors($record))
                    ->limit(80)
                    ->wrap()
                    ->searchable(query: fn (Builder $query, string $search): Builder => $query->where(
                        fn (Builder $searchQuery): Builder => $searchQuery
                            ->whereHas('translations', fn (Builder $translationQuery): Builder => $translationQuery
                                ->where('title', 'like', '%'.$search.'%')
                                ->orWhere('authors', 'like', '%'.$search.'%'))
                            ->orWhere('doi', 'like', '%'.$search.'%')
                    )),
                TextColumn::make('faculty_name')
                    ->label(__('admin.research_publication.table.faculty'))
                    ->getStateUsing(fn (ResearchPublication $record): string => self::facultyName($record))
                    ->placeholder(__('admin.research_publication.table.no_faculty')),
                TextColumn::make('publication_year')
                    ->label(__('admin.research_publication.table.year'))
                    ->sortable()
                    ->placeholder('—'),
                TextColumn::make('status')
                    ->label(__('admin.research_publication.table.status'))
                    ->getStateUsing(fn (ResearchPublication $record): string => self::status($record))
                    ->formatStateUsing(fn (string $state): string => __('admin.research_publication.statuses.'.$state))
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'published' => 'success',
                        'needs_review' => 'warning',
                        default => 'gray',
                    }),
                TextColumn::make('updated_at')
                    ->label(__('admin.research_publication.table.updated_at'))
                    ->dateTime(__('admin.formats.date_time'))
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label(__('admin.research_publication.filters.status'))
                    ->options([
                        'published' => __('admin.research_publication.statuses.published'),
                        'needs_review' => __('admin.research_publication.statuses.needs_review'),
                        'hidden' => __('admin.research_publication.statuses.hidden'),
                    ])
                    ->query(fn (Builder $query, array $data): Builder => match ($data['value'] ?? null) {
                        'published' => $query->where('is_enabled', true),
                        'needs_review' => $query->where('is_enabled', false)->where('extraction_status', 'duplicate_review'),
                        'hidden' => $query->where('is_enabled', false)->where(fn (Builder $statusQuery): Builder => $statusQuery
                            ->whereNull('extraction_status')
                            ->orWhere('extraction_status', '!=', 'duplicate_review')),
                        default => $query,
                    }),
                SelectFilter::make('faculty')
                    ->label(__('admin.research_publication.filters.faculty'))
                    ->options(fn (): array => app(ResearchPublicationCmsServiceInterface::class)->facultyFilterOptions())
                    ->query(fn (Builder $query, array $data): Builder => filled($data['value'] ?? null)
                        ? $query->whereEffectiveFaculty(fn (Builder $facultyQuery): Builder => $facultyQuery->where('public_slug', $data['value']))
                        : $query),
                SelectFilter::make('publication_year')
                    ->label(__('admin.research_publication.filters.year'))
                    ->options(fn (): array => app(ResearchPublicationCmsServiceInterface::class)->yearFilterOptions()),
            ])
            ->actions([
                Tables\Actions\EditAction::make()->label(__('admin.actions.edit')),
                Tables\Actions\Action::make('delete_publication')
                    ->label(__('admin.research_publication.actions.delete'))
                    ->icon('heroicon-o-trash')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalDescription(__('admin.research_publication.help.delete'))
                    ->visible(fn (ResearchPublication $record): bool => Gate::allows('delete', $record))
                    ->action(function (ResearchPublication $record): void {
                        $user = auth()->user();
                        abort_unless($user instanceof User, 403);
                        app(ResearchPublicationCmsServiceInterface::class)->deletePublication('entity.research-publication.'.$record->getKey(), (int) $user->getKey());
                        Notification::make()->title(__('admin.research_publication.notifications.deleted'))->success()->send();
                    }),
            ])
            ->bulkActions([])
            ->paginated([25, 50, 100])
            ->defaultPaginationPageOption(25)
            ->emptyStateHeading(__('admin.research_publication.empty.heading'))
            ->emptyStateIcon('heroicon-o-document-text')
            ->defaultSort(fn (Builder $query): Builder => $query->orderByDesc('publication_year')->orderByDesc('id'));
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListResearchPublications::route('/'),
            'create' => Pages\CreateResearchPublication::route('/create'),
            'edit' => Pages\EditResearchPublication::route('/{record}/edit'),
        ];
    }

    /** published, needs_review or hidden */
    public static function status(ResearchPublication $publication): string
    {
        if ((bool) $publication->is_enabled) {
            return 'published';
        }

        return $publication->extraction_status === 'duplicate_review' ? 'needs_review' : 'hidden';
    }

    private static function scopeQueryToCurrentUser(Builder $query): Builder
    {
        $user = auth()->user();

        if (! $user instanceof User || ! $user->hasFacultyLimit()) {
            return $query;
        }

        $scopes = app(AdminAccessServiceInterface::class)->facultyScopeValues($user);

        return $scopes === []
            ? $query->whereRaw('1 = 0')
            : $query->whereEffectiveFaculty(fn (Builder $facultyQuery): Builder => $facultyQuery->where(
                fn (Builder $scopeQuery): Builder => $scopeQuery
                    ->whereIn('faculty_scope_slug', $scopes)
                    ->orWhereIn('public_slug', $scopes)
                    ->orWhereIn('slug', $scopes)
            ));
    }

    /** @return array<int, string> */
    private static function facultyMemberOptions(?string $search, ?int $selectedId = null): array
    {
        $user = auth()->user();

        return $user instanceof User
            ? app(ResearchPublicationCmsServiceInterface::class)->facultyMemberOptions((int) $user->getKey(), $search, $selectedId)
            : [];
    }

    private static function localizedTitle(ResearchPublication $publication): string
    {
        $locale = app()->getLocale() === 'en' ? 'en' : 'ar';
        $title = $publication->translations->firstWhere('locale', $locale)?->title
            ?: $publication->translations->firstWhere('locale', $locale === 'en' ? 'ar' : 'en')?->title;

        return filled($title) ? trim((string) $title) : '#'.$publication->getKey();
    }

    private static function localizedAuthors(ResearchPublication $publication): string
    {
        $locale = app()->getLocale() === 'en' ? 'en' : 'ar';
        $authors = $publication->translations->firstWhere('locale', $locale)?->authors
            ?: $publication->translations->firstWhere('locale', $locale === 'en' ? 'ar' : 'en')?->authors;

        return mb_strimwidth(trim(strip_tags((string) $authors)), 0, 120, '…');
    }

    private static function facultyName(ResearchPublication $publication): string
    {
        $locale = app()->getLocale() === 'en' ? 'en' : 'ar';

        return (string) ($publication->effectiveFaculty()?->translations->firstWhere('locale', $locale)?->name ?? '');
    }

    /**
     * @param  list<string>  $codes
     * @return array<string, string>
     */
    private static function codeOptions(string $group, array $codes): array
    {
        return collect($codes)->mapWithKeys(fn (string $code): array => [$code => __('research.'.$group.'.'.$code)])->all();
    }

    private static function direction(mixed $locale): string
    {
        return $locale === 'en' ? 'ltr' : 'rtl';
    }
}
