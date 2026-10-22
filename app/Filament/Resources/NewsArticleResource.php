<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Contracts\Auth\AdminAccessServiceInterface;
use App\Contracts\News\NewsArticleCmsServiceInterface;
use App\Contracts\News\NewsArticleEventCmsServiceInterface;
use App\Filament\Resources\NewsArticleResource\Pages;
use App\Filament\Support\FormPicker;
use App\Filament\Support\GrantedFacultySelect;
use App\Filament\Support\MediaPicker;
use App\Models\News\NewsArticle;
use App\Models\News\NewsCategory;
use App\Models\User\User;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Group;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Tabs;
use Filament\Forms\Components\Tabs\Tab;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\TimePicker;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;

class NewsArticleResource extends Resource
{
    private const EVENT_TYPES = ['events', 'society_events'];

    /** @var array<int, string>|null */
    private static ?array $editorialTypes = null;

    protected static ?string $model = NewsArticle::class;

    protected static ?string $navigationIcon = 'heroicon-o-newspaper';

    protected static ?int $navigationSort = 2;

    protected static ?string $recordTitleAttribute = 'slug';

    public static function getModelLabel(): string
    {
        return __('admin.news_article.model');
    }

    public static function getPluralModelLabel(): string
    {
        return __('admin.news_article.plural_model');
    }

    public static function getRecordTitle(?Model $record): string
    {
        return $record instanceof NewsArticle ? self::localizedTitle($record) : self::getModelLabel();
    }

    public static function canAccess(): bool
    {
        return Gate::allows('viewAny', NewsArticle::class);
    }

    public static function getNavigationGroup(): ?string
    {
        return __('admin.navigation.groups.news');
    }

    public static function getNavigationLabel(): string
    {
        return __('admin.navigation.items.news_articles');
    }

    public static function getEloquentQuery(): Builder
    {
        return self::scopeQueryToCurrentUser(parent::getEloquentQuery())
            ->with(['translations', 'category.translations', 'coverMedia']);
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Tabs::make('news_editor')
                ->tabs([
                    Tab::make(__('admin.news_article.tabs.content'))
                        ->icon('heroicon-o-pencil-square')
                        ->schema([
                            Section::make(__('admin.news_article.sections.basics'))
                                ->description(__('admin.news_article.help.basics'))
                                ->schema([
                                    Radio::make('news_category_id')
                                        ->label(__('admin.news_article.fields.category'))
                                        ->options(fn (mixed $state): array => self::editorialTypeOptions(is_numeric($state) ? (int) $state : null))
                                        ->required()
                                        ->live()
                                        ->inline(),
                                    MediaPicker::assetImage('cover_media_id', __('admin.news_article.fields.cover_image')),
                                ])
                                ->columns(2),
                            Section::make(__('admin.news_article.sections.languages'))
                                ->description(__('admin.news_article.help.languages'))
                                ->schema([
                                    Repeater::make('translations')
                                        ->hiddenLabel()
                                        ->itemLabel(fn (array $state): string => __('admin.news_article.translation_item', [
                                            'language' => __('admin.locales.'.($state['locale'] ?? 'ar')),
                                        ]))
                                        ->schema([
                                            Hidden::make('locale')->required(),
                                            TextInput::make('title')
                                                ->label(__('admin.news_article.fields.title'))
                                                ->required()
                                                ->maxLength(255)
                                                ->extraInputAttributes(fn (Get $get): array => ['dir' => $get('locale') === 'en' ? 'ltr' : 'rtl']),
                                            Textarea::make('excerpt')
                                                ->label(__('admin.news_article.fields.summary'))
                                                ->rows(3)
                                                ->columnSpanFull()
                                                ->extraInputAttributes(fn (Get $get): array => ['dir' => $get('locale') === 'en' ? 'ltr' : 'rtl']),
                                            RichEditor::make('body')
                                                ->label(__('admin.news_article.fields.body'))
                                                ->required()
                                                ->toolbarButtons(['attachFiles', 'blockquote', 'bold', 'bulletList', 'codeBlock', 'h2', 'h3', 'highlight', 'italic', 'link', 'orderedList', 'redo', 'strike', 'underline', 'undo'])
                                                ->columnSpanFull()
                                                ->extraAttributes(fn (Get $get): array => ['dir' => $get('locale') === 'en' ? 'ltr' : 'rtl']),
                                        ])
                                        ->columns(2)
                                        ->minItems(2)
                                        ->maxItems(2)
                                        ->deletable(false)
                                        ->default([
                                            ['locale' => 'ar'],
                                            ['locale' => 'en'],
                                        ])
                                        ->reorderable(false)
                                        ->collapsible()
                                        ->columnSpanFull(),
                                ]),
                        ]),
                    Tab::make(__('admin.news_article.tabs.event'))
                        ->icon('heroicon-o-calendar-days')
                        ->visible(fn (Get $get): bool => self::eventType($get('news_category_id')) !== null)
                        ->schema([
                            Group::make(self::eventSchema())->statePath('event'),
                        ]),
                    Tab::make(__('admin.news_article.tabs.publishing'))
                        ->icon('heroicon-o-paper-airplane')
                        ->schema([
                            Section::make(__('admin.news_article.sections.visibility'))
                                ->description(fn (string $operation): string => __($operation === 'create' ? 'admin.news_article.help.published_on_create' : 'admin.news_article.help.private_draft'))
                                ->schema([
                                    Toggle::make('is_enabled')
                                        ->label(__('admin.news_article.fields.enabled'))
                                        ->helperText(__('admin.news_article.help.enabled'))
                                        ->default(true),
                                    Toggle::make('is_featured')
                                        ->label(__('admin.news_article.fields.featured'))
                                        ->helperText(__('admin.news_article.help.featured'))
                                        ->default(false),
                                ])
                                ->columns(2),
                        ]),
                    Tab::make(__('admin.news_article.tabs.attachments'))
                        ->icon('heroicon-o-paper-clip')
                        ->schema([
                            Repeater::make('attachments')
                                ->label(__('admin.news_article.sections.attachments'))
                                ->schema([
                                    Hidden::make('id'),
                                    MediaPicker::assetAny('media_asset_id', __('admin.news_article.fields.media')),
                                    Radio::make('kind')
                                        ->label(__('admin.news_article.fields.attachment_type'))
                                        ->required()
                                        ->options([
                                            'image' => __('admin.news_article.attachment_types.image'),
                                            'file' => __('admin.news_article.attachment_types.file'),
                                            'video' => __('admin.news_article.attachment_types.video'),
                                        ])
                                        ->inline()
                                        ->default('file'),
                                    TextInput::make('label_ar')->label(__('admin.news_article.fields.attachment_label_ar'))->maxLength(255),
                                    TextInput::make('label_en')->label(__('admin.news_article.fields.attachment_label_en'))->maxLength(255),
                                    TextInput::make('legacy_path')->maxLength(255)->hidden(),
                                    Hidden::make('legacy_source_table'),
                                    Hidden::make('legacy_source_id'),
                                    TextInput::make('sort_order')->numeric()->default(0)->hidden(),
                                ])
                                ->columns(2)
                                ->collapsible()
                                ->columnSpanFull(),
                        ]),
                    Tab::make(__('admin.news_article.tabs.advanced'))
                        ->icon('heroicon-o-cog-6-tooth')
                        ->schema([
                            Section::make(__('admin.news_article.sections.technical'))
                                ->collapsed()
                                ->schema([
                                    TextInput::make('slug')
                                        ->label(__('admin.news_article.fields.slug'))
                                        ->maxLength(80)
                                        ->alphaDash()
                                        ->unique(ignoreRecord: true)
                                        ->helperText(__('admin.news_article.help.slug')),
                                    TextInput::make('sort_order')
                                        ->label(__('admin.news_article.fields.sort_order'))
                                        ->numeric()
                                        ->default(0),
                                    TextInput::make('faculty_scope_slug')
                                        ->label(__('admin.news_article.fields.faculty_scope'))
                                        ->maxLength(255)
                                        ->alphaDash()
                                        ->visible(fn (): bool => auth()->user() instanceof User && ! auth()->user()->hasFacultyLimit()),
                                    GrantedFacultySelect::make('faculty_scope_slug', __('admin.news_article.fields.faculty_scope')),
                                ])
                                ->columns(2),
                            Section::make(__('admin.news_article.sections.seo'))
                                ->collapsed()
                                ->schema([
                                    Repeater::make('seoMeta')
                                        ->hiddenLabel()
                                        ->schema([
                                            Select::make('locale')
                                                ->required()
                                                ->native(false)
                                                ->options([
                                                    'ar' => __('admin.locales.ar'),
                                                    'en' => __('admin.locales.en'),
                                                ]),
                                            TextInput::make('meta_title')->label(__('admin.news_article.fields.meta_title'))->maxLength(255),
                                            Textarea::make('meta_description')->label(__('admin.news_article.fields.meta_description'))->rows(2),
                                            TextInput::make('og_title')->label(__('admin.news_article.fields.og_title'))->maxLength(255),
                                            Textarea::make('og_description')->label(__('admin.news_article.fields.og_description'))->rows(2),
                                            MediaPicker::assetImage('og_image_media_id', __('admin.news_article.fields.og_image')),
                                            MediaPicker::image('og_image_url', __('admin.news_article.fields.legacy_og_image')),
                                            TextInput::make('robots')->label(__('admin.news_article.fields.robots'))->default('index,follow')->maxLength(255),
                                        ])
                                        ->columns(2)
                                        ->defaultItems(0)
                                        ->maxItems(2)
                                        ->reorderable(false)
                                        ->collapsible()
                                        ->columnSpanFull(),
                                ]),
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
                    ->label(__('admin.news_article.table.title'))
                    ->getStateUsing(fn (NewsArticle $record): string => self::localizedTitle($record))
                    ->description(fn (NewsArticle $record): string => self::usesArabicFallback($record) ? __('admin.news_article.table.arabic_fallback') : '')
                    ->limit(70)
                    ->searchable(query: fn (Builder $query, string $search): Builder => $query->whereHas(
                        'translations',
                        fn (Builder $translationQuery): Builder => $translationQuery
                            ->where('title', 'like', "%{$search}%")
                            ->orWhere('excerpt', 'like', "%{$search}%")
                    )),
                TextColumn::make('category_name')
                    ->label(__('admin.news_article.table.category'))
                    ->getStateUsing(fn (NewsArticle $record): string => self::editorialTypeLabel($record->category))
                    ->badge(),
                TextColumn::make('status')
                    ->label(__('admin.news_article.table.status'))
                    ->formatStateUsing(fn (string $state): string => __('admin.news_article.statuses.'.$state))
                    ->badge()->color(fn (string $state): string => match ($state) {
                        'published' => 'success',
                        'scheduled' => 'warning',
                        'archived' => 'gray',
                        default => 'gray',
                    }),
                IconColumn::make('is_enabled')->label(__('admin.news_article.table.enabled'))->boolean(),
                IconColumn::make('is_featured')->label(__('admin.news_article.table.featured'))->boolean(),
                TextColumn::make('scheduled_at')
                    ->label(__('admin.news_article.table.scheduled_at'))
                    ->dateTime(__('admin.formats.date_time'))
                    ->placeholder(__('admin.news_article.table.not_scheduled'))
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->sortable(),
                TextColumn::make('updated_at')->label(__('admin.news_article.table.updated_at'))->dateTime(__('admin.formats.date_time'))->sortable(),
            ])
            ->filters([
                SelectFilter::make('category_type')
                    ->label(__('admin.news_article.filters.type'))
                    ->options(['news' => __('admin.news_article.types.news'), 'announcement' => __('admin.news_article.types.announcement'), 'event' => __('admin.news_article.types.events')])
                    ->query(fn (Builder $query, array $data): Builder => filled($data['value'] ?? null)
                        ? $query->whereHas('category', fn (Builder $categoryQuery): Builder => $categoryQuery->where('type', $data['value']))
                        : $query),
                SelectFilter::make('status')->label(__('admin.news_article.filters.status'))->options(self::translatedStatuses()),
                TernaryFilter::make('is_enabled')->label(__('admin.news_article.filters.enabled')),
                TernaryFilter::make('is_featured')->label(__('admin.news_article.filters.featured')),
            ])
            ->actions([Tables\Actions\EditAction::make()->label(__('admin.actions.edit'))])
            ->bulkActions([])
            ->emptyStateHeading(__('admin.news_article.empty.heading'))
            ->emptyStateDescription(__('admin.news_article.empty.description'))
            ->emptyStateIcon('heroicon-o-newspaper')
            ->defaultSort('updated_at', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListNewsArticles::route('/'),
            'create' => Pages\CreateNewsArticle::route('/create'),
            'edit' => Pages\EditNewsArticle::route('/{record}/edit'),
        ];
    }

    private static function scopeQueryToCurrentUser(Builder $query): Builder
    {
        $user = auth()->user();

        if (! $user instanceof User || ! $user->hasFacultyLimit()) {
            return $query;
        }

        $scopes = app(AdminAccessServiceInterface::class)->facultyScopeValues($user);

        return $scopes === [] ? $query->whereRaw('1 = 0') : $query->whereIn('faculty_scope_slug', $scopes);
    }

    /** @return array<string, string> */
    private static function translatedStatuses(): array
    {
        return [
            'draft' => __('admin.news_article.statuses.draft'),
            'published' => __('admin.news_article.statuses.published'),
            'scheduled' => __('admin.news_article.statuses.scheduled'),
            'archived' => __('admin.news_article.statuses.archived'),
        ];
    }

    private static function localizedTitle(NewsArticle $article): string
    {
        $locale = app()->getLocale() === 'en' ? 'en' : 'ar';
        $title = $article->translations->firstWhere('locale', $locale)?->title;
        $arabicTitle = $article->translations->firstWhere('locale', 'ar')?->title;

        return filled($title) ? trim((string) $title) : (filled($arabicTitle) ? trim((string) $arabicTitle) : $article->slug);
    }

    private static function usesArabicFallback(NewsArticle $article): bool
    {
        return app()->getLocale() === 'en'
            && blank($article->translations->firstWhere('locale', 'en')?->title)
            && filled($article->translations->firstWhere('locale', 'ar')?->title);
    }

    private static function editorialTypeLabel(mixed $category): string
    {
        if (! $category instanceof NewsCategory || ! in_array($category->type, ['news', 'announcement', 'event'], true)) {
            return __('admin.news_article.table.no_category');
        }

        $type = match ($category->slug) {
            'agreements' => 'agreements',
            'society-events' => 'society_events',
            'events' => 'events',
            default => $category->type,
        };

        return __('admin.news_article.types.'.$type);
    }

    /**
     * Events and Society's Events need the events permission; an article
     * already in one of them keeps its choice listed.
     *
     * @return array<int, string>
     */
    private static function editorialTypeOptions(?int $current = null): array
    {
        $options = [];
        $canManageEvents = Gate::allows('events.update');

        foreach (self::editorialTypes() as $categoryId => $type) {
            if (in_array($type, self::EVENT_TYPES, true) && ! $canManageEvents && $categoryId !== $current) {
                continue;
            }
            $options[$categoryId] = __('admin.news_article.types.'.$type);
        }

        return $options;
    }

    /** @return array<int, string> */
    private static function editorialTypes(): array
    {
        return self::$editorialTypes ??= app(NewsArticleCmsServiceInterface::class)->editorialTypeOptions();
    }

    /** "events", "society_events", or null for a category without event details. */
    private static function eventType(mixed $categoryId): ?string
    {
        $type = is_numeric($categoryId) ? (self::editorialTypes()[(int) $categoryId] ?? null) : null;

        return in_array($type, self::EVENT_TYPES, true) ? $type : null;
    }

    /** @return array<int, \Filament\Forms\Components\Component> */
    private static function eventSchema(): array
    {
        $isEvents = fn (Get $get): bool => self::eventType($get('../news_category_id')) === 'events';
        $needsRegistration = fn (Get $get): bool => $isEvents($get) && (bool) $get('requires_registration');

        return [
            Section::make(__('admin.news_article.event.sections.type'))
                ->description(__('admin.news_article.event.help.type'))
                ->schema([
                    Radio::make('kind')
                        ->label(__('admin.news_article.event.fields.kind'))
                        ->options(self::translatedOptions(NewsArticleEventCmsServiceInterface::KINDS, 'kinds'))
                        ->default('event')
                        ->required($isEvents)
                        ->visible($isEvents)
                        ->inline(),
                    Toggle::make('requires_registration')
                        ->label(__('admin.news_article.event.fields.requires_registration'))
                        ->helperText(__('admin.news_article.event.help.requires_registration'))
                        ->visible($isEvents)
                        ->live(),
                    Toggle::make('show_on_calendar')
                        ->label(__('admin.news_article.event.fields.show_on_calendar'))
                        ->helperText(__('admin.news_article.event.help.show_on_calendar'))
                        ->live(),
                    Radio::make('language')
                        ->label(__('admin.news_article.event.fields.language'))
                        ->options(self::translatedOptions(NewsArticleEventCmsServiceInterface::LANGUAGES, 'languages'))
                        ->inline(),
                    Toggle::make('has_certificate')
                        ->label(__('admin.news_article.event.fields.has_certificate'))
                        ->live(),
                    Textarea::make('certificate_note_ar')
                        ->label(__('admin.news_article.event.fields.certificate_note_ar'))
                        ->rows(2)
                        ->visible(fn (Get $get): bool => (bool) $get('has_certificate'))
                        ->extraInputAttributes(['dir' => 'rtl']),
                    Textarea::make('certificate_note_en')
                        ->label(__('admin.news_article.event.fields.certificate_note_en'))
                        ->rows(2)
                        ->visible(fn (Get $get): bool => (bool) $get('has_certificate'))
                        ->extraInputAttributes(['dir' => 'ltr']),
                ])
                ->columns(2),
            Section::make(__('admin.news_article.event.sections.sessions'))
                ->description(__('admin.news_article.event.help.sessions'))
                ->schema([
                    Repeater::make('sessions')
                        ->hiddenLabel()
                        ->itemLabel(fn (array $state): ?string => filled($state['date'] ?? null) ? (string) $state['date'] : null)
                        ->schema([
                            DatePicker::make('date')
                                ->label(__('admin.news_article.event.fields.session_date'))
                                ->native(false)
                                ->displayFormat('d/m/Y')
                                ->required(),
                            TimePicker::make('starts_at')
                                ->label(__('admin.news_article.event.fields.session_starts_at'))
                                ->seconds(false)
                                ->required(),
                            TimePicker::make('ends_at')
                                ->label(__('admin.news_article.event.fields.session_ends_at'))
                                ->seconds(false)
                                ->after('starts_at'),
                            TextInput::make('title_ar')->label(__('admin.news_article.event.fields.session_title_ar'))->maxLength(255)->extraInputAttributes(['dir' => 'rtl']),
                            TextInput::make('title_en')->label(__('admin.news_article.event.fields.session_title_en'))->maxLength(255)->extraInputAttributes(['dir' => 'ltr']),
                            TextInput::make('location_ar')
                                ->label(__('admin.news_article.event.fields.session_location_ar'))
                                ->helperText(__('admin.news_article.event.help.session_location'))
                                ->maxLength(255)
                                ->extraInputAttributes(['dir' => 'rtl']),
                            TextInput::make('location_en')->label(__('admin.news_article.event.fields.session_location_en'))->maxLength(255)->extraInputAttributes(['dir' => 'ltr']),
                            Repeater::make('speakers')
                                ->label(__('admin.news_article.event.fields.session_speakers'))
                                ->helperText(__('admin.news_article.event.help.session_speakers'))
                                ->schema(self::speakerSchema())
                                ->itemLabel(fn (array $state): ?string => self::speakerItemLabel($state))
                                ->defaultItems(0)
                                ->collapsible()
                                ->columns(2)
                                ->columnSpanFull(),
                        ])
                        // Society's Events off the calendar are plain articles and may have no date.
                        ->minItems(fn (Get $get): int => $isEvents($get) || (bool) $get('show_on_calendar') ? 1 : 0)
                        ->defaultItems(1)
                        ->collapsible()
                        ->columns(3)
                        ->columnSpanFull(),
                ]),
            Section::make(__('admin.news_article.event.sections.place'))
                ->schema([
                    TextInput::make('location_ar')->label(__('admin.news_article.event.fields.location_ar'))->maxLength(255)->extraInputAttributes(['dir' => 'rtl']),
                    TextInput::make('location_en')->label(__('admin.news_article.event.fields.location_en'))->maxLength(255)->extraInputAttributes(['dir' => 'ltr']),
                    TextInput::make('map_url')->label(__('admin.news_article.event.fields.map_url'))->url()->maxLength(255),
                    TextInput::make('online_url')->label(__('admin.news_article.event.fields.online_url'))->url()->maxLength(255),
                ])
                ->columns(2),
            Section::make(__('admin.news_article.event.sections.audience'))
                ->schema([
                    CheckboxList::make('audience')
                        ->label(__('admin.news_article.event.fields.audience'))
                        ->options(self::translatedOptions(NewsArticleEventCmsServiceInterface::AUDIENCES, 'audiences'))
                        ->columns(4)
                        ->columnSpanFull(),
                    TextInput::make('organizer_ar')->label(__('admin.news_article.event.fields.organizer_ar'))->maxLength(255)->extraInputAttributes(['dir' => 'rtl']),
                    TextInput::make('organizer_en')->label(__('admin.news_article.event.fields.organizer_en'))->maxLength(255)->extraInputAttributes(['dir' => 'ltr']),
                    Textarea::make('audience_note_ar')->label(__('admin.news_article.event.fields.audience_note_ar'))->rows(2)->extraInputAttributes(['dir' => 'rtl']),
                    Textarea::make('audience_note_en')->label(__('admin.news_article.event.fields.audience_note_en'))->rows(2)->extraInputAttributes(['dir' => 'ltr']),
                ])
                ->columns(2),
            Section::make(__('admin.news_article.event.sections.registration'))
                ->description(__('admin.news_article.event.help.registration'))
                ->visible($needsRegistration)
                ->schema([
                    FormPicker::make('formId')
                        ->label(__('admin.news_article.event.fields.form'))
                        ->required($needsRegistration),
                    TextInput::make('capacity')
                        ->label(__('admin.news_article.event.fields.capacity'))
                        ->helperText(__('admin.news_article.event.help.capacity'))
                        ->numeric()
                        ->integer()
                        ->minValue(1),
                    TextInput::make('prior_registrations')
                        ->label(__('admin.news_article.event.fields.prior_registrations'))
                        ->helperText(__('admin.news_article.event.help.prior_registrations'))
                        ->numeric()
                        ->integer()
                        ->minValue(0)
                        ->default(0),
                    DateTimePicker::make('registration_closes_at')
                        ->label(__('admin.news_article.event.fields.registration_closes_at'))
                        ->helperText(__('admin.news_article.event.help.registration_closes_at'))
                        ->timezone((string) config('app.admin_timezone', 'Asia/Damascus'))
                        ->seconds(false)
                        ->native(false),
                ])
                ->columns(2),
            Section::make(__('admin.news_article.event.sections.speakers'))
                ->description(__('admin.news_article.event.help.speakers'))
                ->schema([
                    Repeater::make('speakers')
                        ->hiddenLabel()
                        ->schema(self::speakerSchema())
                        ->itemLabel(fn (array $state): ?string => self::speakerItemLabel($state))
                        ->defaultItems(0)
                        ->collapsible()
                        ->columns(2)
                        ->columnSpanFull(),
                ]),
            Section::make(__('admin.news_article.event.sections.invitation'))
                ->schema([
                    MediaPicker::assetImage('invitation_media_id', __('admin.news_article.event.fields.invitation')),
                    TextInput::make('contact_email')->label(__('admin.news_article.event.fields.contact_email'))->email()->maxLength(255),
                    TextInput::make('contact_phone')->label(__('admin.news_article.event.fields.contact_phone'))->tel()->maxLength(50),
                ])
                ->columns(2),
            Section::make(__('admin.news_article.event.sections.results'))
                ->description(__('admin.news_article.event.help.results'))
                ->collapsed()
                ->schema([
                    Textarea::make('results_ar')->label(__('admin.news_article.event.fields.results_ar'))->rows(4)->extraInputAttributes(['dir' => 'rtl']),
                    Textarea::make('results_en')->label(__('admin.news_article.event.fields.results_en'))->rows(4)->extraInputAttributes(['dir' => 'ltr']),
                ])
                ->columns(2),
        ];
    }

    /** @return array<int, \Filament\Forms\Components\Component> */
    private static function speakerSchema(): array
    {
        $isExternal = fn (Get $get): bool => $get('source') !== 'university';

        return [
            Radio::make('source')
                ->label(__('admin.news_article.event.fields.speaker_source'))
                ->options([
                    'university' => __('admin.news_article.event.speaker_sources.university'),
                    'external' => __('admin.news_article.event.speaker_sources.external'),
                ])
                ->default('university')
                ->required()
                ->live()
                ->inline()
                ->columnSpanFull(),
            Select::make('profile')
                ->label(__('admin.news_article.event.fields.speaker_profile'))
                ->helperText(__('admin.news_article.event.help.speaker_profile'))
                ->options(fn (?string $state): array => app(NewsArticleEventCmsServiceInterface::class)
                    ->speakerProfileOptions(app()->getLocale() === 'en' ? 'en' : 'ar', $state))
                ->searchable()
                ->native(false)
                ->visible(fn (Get $get): bool => ! $isExternal($get))
                ->required(fn (Get $get): bool => ! $isExternal($get))
                ->columnSpanFull(),
            TextInput::make('name_ar')->label(__('admin.news_article.event.fields.speaker_name_ar'))->maxLength(255)->visible($isExternal)->required($isExternal)->extraInputAttributes(['dir' => 'rtl']),
            TextInput::make('name_en')->label(__('admin.news_article.event.fields.speaker_name_en'))->maxLength(255)->visible($isExternal)->required($isExternal)->extraInputAttributes(['dir' => 'ltr']),
            TextInput::make('title_ar')->label(__('admin.news_article.event.fields.speaker_title_ar'))->maxLength(255)->visible($isExternal)->extraInputAttributes(['dir' => 'rtl']),
            TextInput::make('title_en')->label(__('admin.news_article.event.fields.speaker_title_en'))->maxLength(255)->visible($isExternal)->extraInputAttributes(['dir' => 'ltr']),
            TextInput::make('organization_ar')->label(__('admin.news_article.event.fields.speaker_organization_ar'))->maxLength(255)->visible($isExternal)->extraInputAttributes(['dir' => 'rtl']),
            TextInput::make('organization_en')->label(__('admin.news_article.event.fields.speaker_organization_en'))->maxLength(255)->visible($isExternal)->extraInputAttributes(['dir' => 'ltr']),
            Textarea::make('bio_ar')->label(__('admin.news_article.event.fields.speaker_bio_ar'))->rows(2)->visible($isExternal)->extraInputAttributes(['dir' => 'rtl']),
            Textarea::make('bio_en')->label(__('admin.news_article.event.fields.speaker_bio_en'))->rows(2)->visible($isExternal)->extraInputAttributes(['dir' => 'ltr']),
            MediaPicker::assetImage('photo_media_id', __('admin.news_article.event.fields.speaker_photo'))->visible($isExternal),
        ];
    }

    /** @param array<string, mixed> $state */
    private static function speakerItemLabel(array $state): ?string
    {
        if (($state['source'] ?? null) === 'university') {
            return null;
        }
        $name = $state[app()->getLocale() === 'en' ? 'name_en' : 'name_ar'] ?? null;

        return filled($name) ? (string) $name : null;
    }

    /**
     * @param  array<int, string>  $values
     * @return array<string, string>
     */
    private static function translatedOptions(array $values, string $group): array
    {
        $options = [];
        foreach ($values as $value) {
            $options[$value] = __('admin.news_article.event.'.$group.'.'.$value);
        }

        return $options;
    }
}
