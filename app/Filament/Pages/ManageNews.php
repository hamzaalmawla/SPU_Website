<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Contracts\Cms\CmsTargetRegistryInterface;
use App\Contracts\Cms\CmsWorkflowServiceInterface;
use App\Contracts\News\NewsServiceInterface;
use App\DTOs\Cms\CmsTargetDTO;
use App\Exceptions\ConflictException;
use App\Filament\Resources\NewsArticleResource;
use App\Filament\Support\MediaPicker;
use App\Models\User\User;
use Filament\Actions\Action;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Tabs;
use Filament\Forms\Components\Tabs\Tab;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ManageNews extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-newspaper';

    protected static ?string $slug = 'manage-news';

    protected static ?int $navigationSort = 1;

    protected static string $view = 'filament.pages.manage-news';

    /** @var array<string, mixed> */
    public ?array $data = [];

    public ?int $draftVersion = null;

    public ?string $activeTargetKey = null;

    private NewsServiceInterface $newsService;

    private CmsTargetRegistryInterface $targetRegistry;

    private CmsWorkflowServiceInterface $cmsWorkflowService;

    public function boot(
        NewsServiceInterface $newsService,
        CmsTargetRegistryInterface $targetRegistry,
        CmsWorkflowServiceInterface $cmsWorkflowService,
    ): void {
        $this->newsService = $newsService;
        $this->targetRegistry = $targetRegistry;
        $this->cmsWorkflowService = $cmsWorkflowService;
    }

    public static function canAccess(): bool
    {
        return Gate::allows('manage-pages');
    }

    public static function getNavigationGroup(): ?string
    {
        return __('admin.navigation.groups.news');
    }

    public static function getNavigationLabel(): string
    {
        return __('admin.navigation.items.news');
    }

    public function getTitle(): string
    {
        return __('admin.pages.manage_news');
    }

    public function mount(): void
    {
        $requestedTarget = request()->query('target', $this->defaultNewsTargetKey());
        $targetKey = is_string($requestedTarget) && array_key_exists($requestedTarget, $this->targetOptions())
            ? $requestedTarget
            : $this->defaultNewsTargetKey();

        if (! $this->showsTargetSelector()) {
            $targetKey = $this->defaultNewsTargetKey();
        }

        $this->loadTarget($targetKey);
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make(__('admin.editorial_workspace.choose_page'))->schema([
                    Select::make('target_key')
                        ->label(__('admin.editorial_workspace.page'))
                        ->options($this->targetOptions())
                        ->required()
                        ->live()
                        ->afterStateUpdated(fn (?string $state): mixed => is_string($state) && $state !== '' ? $this->loadTarget($state) : null),
                ])->visible(fn (): bool => $this->showsTargetSelector()),
                Section::make(__('admin.editorial_workspace.events.heading'))
                    ->description(__('admin.editorial_workspace.events.description'))
                    ->schema($this->eventsWorkspaceFields())
                    ->visible(fn (): bool => $this->targetKeyForSchema() === 'news.events'),
                Tabs::make('news_locales')
                    ->tabs([
                        Tab::make(__('admin.locales.ar'))->extraAttributes(['dir' => 'rtl'])->schema($this->payloadFields('ar')),
                        Tab::make(__('admin.locales.en'))->extraAttributes(['dir' => 'ltr'])->schema($this->payloadFields('en')),
                    ])
                    ->visible(fn (): bool => $this->targetKeyForSchema() !== 'news.events')
                    ->persistTabInQueryString('locale')
                    ->columnSpanFull(),
            ])
            ->statePath('data');
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('save')->label(__('admin.editorial_workspace.actions.save_draft'))->icon('heroicon-o-check')->color('gray')->action(function (): void {
                $this->save();
            }),
            Action::make('preview_ar')->label(__('admin.editorial_workspace.actions.preview_ar'))->icon('heroicon-o-eye')->color('info')->action(function (): void {
                $this->openPreview('ar');
            }),
            Action::make('preview_en')->label(__('admin.editorial_workspace.actions.preview_en'))->icon('heroicon-o-eye')->color('info')->action(function (): void {
                $this->openPreview('en');
            }),
            Action::make('publish')->label(__('admin.editorial_workspace.actions.publish'))->icon('heroicon-o-paper-airplane')->color('success')->requiresConfirmation()
                ->visible(fn (): bool => Gate::allows('publish-content'))->action(function (): void {
                    $this->publish();
                }),
            Action::make('schedule')
                ->label(__('admin.editorial_workspace.actions.schedule'))
                ->icon('heroicon-o-clock')
                ->color('warning')
                ->form([
                    DateTimePicker::make('publish_at')->label(__('admin.editorial_workspace.publish_at'))->required()->minDate(now())->native(false),
                ])
                ->visible(fn (): bool => Gate::allows('publish-content'))
                ->action(fn (array $data) => $this->schedule((string) $data['publish_at'])),
            Action::make('unpublish')->label(__('admin.editorial_workspace.actions.unpublish'))->icon('heroicon-o-x-circle')->color('danger')->requiresConfirmation()
                ->visible(fn (): bool => Gate::allows('publish-content'))->action(function (): void {
                    $this->unpublish();
                }),
        ];
    }

    protected function defaultNewsTargetKey(): string
    {
        return 'news.index';
    }

    protected function showsTargetSelector(): bool
    {
        return true;
    }

    public function loadTarget(string $targetKey): void
    {
        $this->assertNewsTarget($targetKey);
        $this->activeTargetKey = $targetKey;

        if (! in_array($targetKey, ['news.index', 'news.articles', 'news.announcements', 'news.events', 'news.gallery'], true)) {
            $this->draftVersion = $this->cmsWorkflowService->latestEditableDraftVersion($targetKey, (int) auth()->id());
            $this->form->fill([
                'target_key' => $targetKey,
                'ar_index' => [],
                'en_index' => [],
            ]);

            return;
        }

        $draftPayload = $this->cmsWorkflowService->latestEditableDraftPayload($targetKey, (int) auth()->id());
        $payload = is_array($draftPayload) ? $draftPayload : $this->newsService->getEditablePayload($targetKey);
        $this->draftVersion = $this->cmsWorkflowService->latestEditableDraftVersion($targetKey, (int) auth()->id());

        if ($targetKey === 'news.articles') {
            $this->form->fill([
                'target_key' => $targetKey,
                'ar_articles' => is_array($payload['translations']['ar'] ?? null) ? $payload['translations']['ar'] : [],
                'en_articles' => is_array($payload['translations']['en'] ?? null) ? $payload['translations']['en'] : [],
            ]);

            return;
        }

        if ($targetKey === 'news.announcements') {
            $this->form->fill([
                'target_key' => $targetKey,
                'ar_target' => is_array($payload['translations']['ar'] ?? null) ? $payload['translations']['ar'] : [],
                'en_target' => is_array($payload['translations']['en'] ?? null) ? $payload['translations']['en'] : [],
            ]);

            return;
        }

        if ($targetKey === 'news.events') {
            $arEvents = is_array($payload['translations']['ar'] ?? null) ? $payload['translations']['ar'] : [];
            $enEvents = is_array($payload['translations']['en'] ?? null) ? $payload['translations']['en'] : [];
            $this->form->fill([
                'target_key' => $targetKey,
                'ar_events' => $arEvents,
                'en_events' => $enEvents,
                'events_workspace' => $this->eventsWorkspaceFromTranslations($arEvents, $enEvents),
            ]);

            return;
        }

        if ($targetKey === 'news.gallery') {
            $this->form->fill([
                'target_key' => $targetKey,
                'ar_gallery' => is_array($payload['translations']['ar'] ?? null) ? $payload['translations']['ar'] : [],
                'en_gallery' => is_array($payload['translations']['en'] ?? null) ? $payload['translations']['en'] : [],
            ]);

            return;
        }

        $this->form->fill([
            'target_key' => $targetKey,
            'ar_index' => is_array($payload['translations']['ar'] ?? null) ? $payload['translations']['ar'] : [],
            'en_index' => is_array($payload['translations']['en'] ?? null) ? $payload['translations']['en'] : [],
        ]);
    }

    public function save(): void
    {
        $this->validateEventDates();

        /** @var User $user */
        $user = auth()->user();

        try {
            $draft = $this->cmsWorkflowService->saveDraft($this->currentTargetKey(), $this->payloadFromForm($this->currentFormData()), (int) $user->id, $this->draftVersion);
            $this->draftVersion = $draft->version;

            Notification::make()->title(__('admin.editorial_workspace.notifications.draft_saved'))->success()->send();
        } catch (ConflictException $e) {
            $this->draftVersion = $e->currentVersion;
            Notification::make()->title(__('admin.editorial_workspace.notifications.conflict'))->body(__('admin.editorial_workspace.notifications.conflict_description'))->danger()->persistent()->send();
        } catch (\Throwable $e) {
            report($e);
            Notification::make()->title(__('admin.editorial_workspace.notifications.save_failed'))->body(__('admin.editorial_workspace.notifications.safe_error'))->danger()->send();
        }
    }

    public function openPreview(string $locale): void
    {
        if (! in_array($locale, ['ar', 'en'], true)) {
            Notification::make()->title(__('admin.editorial_workspace.notifications.preview_failed'))->body(__('admin.editorial_workspace.notifications.invalid_preview_locale'))->danger()->send();

            return;
        }

        /** @var User $user */
        $user = auth()->user();

        try {
            $targetKey = $this->currentTargetKey();
            $draft = $this->cmsWorkflowService->saveDraft($targetKey, $this->payloadFromForm($this->currentFormData()), (int) $user->id, $this->draftVersion);
            $this->draftVersion = $draft->version;
            $preview = $this->cmsWorkflowService->preview($targetKey, $locale, (int) $user->id);

            $this->redirect($preview->previewUrl);
        } catch (ConflictException $e) {
            $this->draftVersion = $e->currentVersion;
            Notification::make()->title(__('admin.editorial_workspace.notifications.conflict'))->body(__('admin.editorial_workspace.notifications.conflict_description'))->danger()->persistent()->send();
        } catch (\Throwable $e) {
            report($e);
            Notification::make()->title(__('admin.editorial_workspace.notifications.preview_failed'))->body(__('admin.editorial_workspace.notifications.safe_error'))->danger()->send();
        }
    }

    public function publish(): void
    {
        /** @var User $user */
        $user = auth()->user();

        try {
            $targetKey = $this->currentTargetKey();
            $draft = $this->cmsWorkflowService->saveDraft($targetKey, $this->payloadFromForm($this->form->getState()), (int) $user->id, $this->draftVersion);
            $this->draftVersion = $draft->version;
            $this->cmsWorkflowService->publish($targetKey, (int) $user->id);

            Notification::make()->title(__('admin.editorial_workspace.notifications.published'))->success()->send();
        } catch (ValidationException $e) {
            Notification::make()->title(__('admin.editorial_workspace.notifications.publish_failed'))->body($this->formatValidationErrors($e->errors()))->danger()->persistent()->send();
        } catch (\Throwable $e) {
            report($e);
            Notification::make()->title(__('admin.editorial_workspace.notifications.publish_failed'))->body(__('admin.editorial_workspace.notifications.safe_error'))->danger()->send();
        }
    }

    public function schedule(string $publishAt): void
    {
        /** @var User $user */
        $user = auth()->user();

        try {
            $targetKey = $this->currentTargetKey();
            $draft = $this->cmsWorkflowService->saveDraft($targetKey, $this->payloadFromForm($this->form->getState()), (int) $user->id, $this->draftVersion);
            $this->draftVersion = $draft->version;
            $this->cmsWorkflowService->schedule($targetKey, new \DateTimeImmutable($publishAt), (int) $user->id);

            Notification::make()->title(__('admin.editorial_workspace.notifications.scheduled'))->success()->send();
        } catch (ValidationException $e) {
            Notification::make()->title(__('admin.editorial_workspace.notifications.schedule_failed'))->body($this->formatValidationErrors($e->errors()))->danger()->persistent()->send();
        } catch (\Throwable $e) {
            report($e);
            Notification::make()->title(__('admin.editorial_workspace.notifications.schedule_failed'))->body(__('admin.editorial_workspace.notifications.safe_error'))->danger()->send();
        }
    }

    public function unpublish(): void
    {
        /** @var User $user */
        $user = auth()->user();
        try {
            $result = $this->cmsWorkflowService->unpublish($this->currentTargetKey(), (int) $user->id);
            $notification = Notification::make()->title($result
                ? __('admin.editorial_workspace.notifications.unpublished')
                : __('admin.editorial_workspace.notifications.nothing_published'));

            ($result ? $notification->success() : $notification->warning())->send();
        } catch (\Throwable $e) {
            report($e);
            Notification::make()->title(__('admin.editorial_workspace.notifications.unpublish_failed'))->body(__('admin.editorial_workspace.notifications.safe_error'))->danger()->send();
        }
    }

    /** @return array<string, string> */
    private function targetOptions(): array
    {
        $options = $this->targetRegistry->forArea('news')
            ->mapWithKeys(fn (CmsTargetDTO $target): array => [$target->key => __($target->labelKey)])
            ->all();

        if (! $this->showsTargetSelector()) {
            $targetKey = $this->defaultNewsTargetKey();

            return isset($options[$targetKey]) ? [$targetKey => $options[$targetKey]] : [];
        }

        return array_diff_key($options, array_flip(['news.article', 'news.announcements', 'news.events']));
    }

    private function currentTargetKey(): string
    {
        $targetKey = (string) ($this->data['target_key'] ?? 'news.index');
        $this->assertNewsTarget($targetKey);

        return $targetKey;
    }

    private function assertNewsTarget(string $targetKey): void
    {
        $target = $this->targetRegistry->find($targetKey);

        if (! $target instanceof CmsTargetDTO || $target->area !== 'news') {
            throw new \InvalidArgumentException('Unsupported news target.');
        }
    }

    /** @param array<string, mixed> $state */
    private function payloadFromForm(array $state): array
    {
        if (($state['target_key'] ?? null) === 'news.index') {
            $arabic = is_array($state['ar_index'] ?? null) ? $this->normalizeIndexPayload($state['ar_index']) : [];
            $english = is_array($state['en_index'] ?? null) ? $this->normalizeIndexPayload($state['en_index']) : [];

            return [
                'translations' => [
                    'ar' => $arabic,
                    'en' => $english,
                ],
            ];
        }

        if (($state['target_key'] ?? null) === 'news.announcements') {
            return [
                'translations' => [
                    'ar' => is_array($state['ar_target'] ?? null) ? $this->normalizeAnnouncementsPayload($state['ar_target']) : [],
                    'en' => is_array($state['en_target'] ?? null) ? $this->normalizeAnnouncementsPayload($state['en_target']) : [],
                ],
            ];
        }

        if (($state['target_key'] ?? null) === 'news.articles') {
            return [
                'translations' => [
                    'ar' => is_array($state['ar_articles'] ?? null) ? $state['ar_articles'] : [],
                    'en' => is_array($state['en_articles'] ?? null) ? $state['en_articles'] : [],
                ],
            ];
        }

        if (($state['target_key'] ?? null) === 'news.events') {
            $workspace = is_array($state['events_workspace'] ?? null) ? $state['events_workspace'] : [];

            return [
                'translations' => [
                    'ar' => $this->normalizeEventsPayload($this->eventsTranslationFromWorkspace($workspace, 'ar')),
                    'en' => $this->normalizeEventsPayload($this->eventsTranslationFromWorkspace($workspace, 'en')),
                ],
            ];
        }

        if (($state['target_key'] ?? null) === 'news.gallery') {
            return [
                'translations' => [
                    'ar' => is_array($state['ar_gallery'] ?? null) ? $this->normalizeGalleryPayload($state['ar_gallery']) : [],
                    'en' => is_array($state['en_gallery'] ?? null) ? $this->normalizeGalleryPayload($state['en_gallery']) : [],
                ],
            ];
        }

        throw new \InvalidArgumentException('This news target form will be structured next. Select the News landing page for now.');
    }

    /** @return array<string, mixed> */
    private function currentFormData(): array
    {
        return is_array($this->data) ? $this->data : [];
    }

    /** @return array<int, Section> */
    private function payloadFields(string $locale): array
    {
        $announcementPrefix = $locale.'_target';
        $eventsPrefix = $locale.'_events';
        $galleryPrefix = $locale.'_gallery';
        $indexPrefix = $locale.'_index';
        $articlesPrefix = $locale.'_articles';

        return [
            Section::make(__('admin.editorial_workspace.news_shell.articles'))
                ->schema([
                    TextInput::make($articlesPrefix.'.title')->label(__('admin.editorial_workspace.news_shell.page_title'))->required()->maxLength(160),
                    Textarea::make($articlesPrefix.'.summary')->label(__('admin.editorial_workspace.news_shell.page_summary'))->required()->rows(2)->columnSpanFull(),
                    MediaPicker::image($articlesPrefix.'.heroImage', __('admin.editorial_workspace.news_shell.hero_image'), true),
                    TextInput::make($articlesPrefix.'.allLabel')->label(__('admin.editorial_workspace.news_shell.all_articles'))->required()->maxLength(120),
                    TextInput::make($articlesPrefix.'.searchLabel')->label(__('admin.editorial_workspace.news_shell.search_label'))->required()->maxLength(120),
                    TextInput::make($articlesPrefix.'.searchPlaceholder')->label(__('admin.editorial_workspace.news_shell.search_placeholder'))->required()->maxLength(180),
                    TextInput::make($articlesPrefix.'.searchAction')->label(__('admin.editorial_workspace.news_shell.search_action'))->required()->maxLength(80),
                    TextInput::make($articlesPrefix.'.readMoreLabel')->label(__('admin.editorial_workspace.news_shell.read_more'))->required()->maxLength(80),
                    Textarea::make($articlesPrefix.'.emptyLabel')->label(__('admin.editorial_workspace.news_shell.empty_state'))->required()->rows(2),
                    TextInput::make($articlesPrefix.'.previousLabel')->label(__('admin.editorial_workspace.news_shell.previous_page'))->required()->maxLength(120),
                    TextInput::make($articlesPrefix.'.nextLabel')->label(__('admin.editorial_workspace.news_shell.next_page'))->required()->maxLength(120),
                    TextInput::make($articlesPrefix.'.seoTitle')->label(__('admin.editorial_workspace.news_shell.seo_title'))->required()->maxLength(180),
                    Textarea::make($articlesPrefix.'.seoDescription')->label(__('admin.editorial_workspace.news_shell.seo_description'))->required()->rows(2),
                    MediaPicker::image($articlesPrefix.'.seoImage', __('admin.editorial_workspace.news_shell.seo_image'), true),
                ])
                ->columns(2)
                ->visible(fn (): bool => $this->targetKeyForSchema() === 'news.articles'),
            Section::make(__('admin.editorial_workspace.announcements.page_intro'))
                ->schema([
                    TextInput::make($announcementPrefix.'.pageTitle')->label(__('admin.editorial_workspace.fields.page_title'))->required()->maxLength(160),
                    Textarea::make($announcementPrefix.'.pageDescription')->label(__('admin.editorial_workspace.fields.page_summary'))->required()->rows(2)->columnSpanFull(),
                    MediaPicker::image($announcementPrefix.'.heroImage', __('admin.editorial_workspace.fields.hero_image'), true),
                ])
                ->columns(2)
                ->visible(fn (): bool => $this->targetKeyForSchema() === 'news.announcements'),
            Section::make(__('admin.editorial_workspace.announcements.interface_text'))
                ->schema([
                    TextInput::make($announcementPrefix.'.featuredLabel')->label(__('admin.editorial_workspace.fields.featured_label'))->required()->maxLength(120),
                    TextInput::make($announcementPrefix.'.allCategoriesLabel')->label(__('admin.editorial_workspace.fields.all_categories_label'))->required()->maxLength(120),
                    TextInput::make($announcementPrefix.'.readMoreLabel')->label(__('admin.editorial_workspace.fields.read_more_label'))->required()->maxLength(120),
                    TextInput::make($announcementPrefix.'.downloadLabel')->label(__('admin.editorial_workspace.fields.download_label'))->required()->maxLength(120),
                    Textarea::make($announcementPrefix.'.emptyState')->label(__('admin.editorial_workspace.fields.empty_state'))->required()->rows(2)->columnSpanFull(),
                ])
                ->columns(2)
                ->collapsed()
                ->visible(fn (): bool => $this->targetKeyForSchema() === 'news.announcements'),
            Section::make('Events Catalog')
                ->schema([
                    TextInput::make($eventsPrefix.'.title')->label('Page Title')->required()->maxLength(160),
                    Textarea::make($eventsPrefix.'.summary')->label('Page Summary')->required()->rows(2)->columnSpanFull(),
                    MediaPicker::image($eventsPrefix.'.heroImage', 'Hero Image', true),
                    TextInput::make($eventsPrefix.'.calendarTitle')->label('Calendar Title')->required()->maxLength(160),
                    TextInput::make($eventsPrefix.'.upcomingTitle')->label('Upcoming Title')->required()->maxLength(160),
                    TextInput::make($eventsPrefix.'.pastTitle')->label('Past Title')->required()->maxLength(160),
                    TextInput::make($eventsPrefix.'.allCategoriesLabel')->label('All Categories Label')->required()->maxLength(120),
                    TextInput::make($eventsPrefix.'.registerLabel')->label('Register Label')->required()->maxLength(120),
                    TextInput::make($eventsPrefix.'.detailsLabel')->label('Details Label')->required()->maxLength(120),
                    TextInput::make($eventsPrefix.'.freeLabel')->label('Free Label')->required()->maxLength(80),
                    TextInput::make($eventsPrefix.'.spotsLeftLabel')->label('Spots Left Label')->required()->maxLength(120),
                    Textarea::make($eventsPrefix.'.emptyLabel')->label('Empty State')->required()->rows(2),
                    TextInput::make($eventsPrefix.'.registrationTitle')->label('Registration Title')->required()->maxLength(160),
                    Textarea::make($eventsPrefix.'.registrationInfo')->label('Registration Information')->required()->rows(2),
                    TextInput::make($eventsPrefix.'.notFoundTitle')->label('Not Found Title')->required()->maxLength(160),
                    Textarea::make($eventsPrefix.'.notFoundText')->label('Not Found Text')->required()->rows(2),
                    TextInput::make($eventsPrefix.'.backLabel')->label('Back Label')->required()->maxLength(120),
                    TextInput::make($eventsPrefix.'.highlightsLabel')->label('Highlights Label')->required()->maxLength(120),
                    TextInput::make($eventsPrefix.'.speakersLabel')->label('Speakers Label')->required()->maxLength(120),
                    TextInput::make($eventsPrefix.'.resultsLabel')->label('Results Label')->required()->maxLength(120),
                    TextInput::make($eventsPrefix.'.galleryLabel')->label('Gallery Label')->required()->maxLength(120),
                    Repeater::make($eventsPrefix.'.categories')->label('Categories')->schema([
                        TextInput::make('id')->label(__('admin.editorial_workspace.news_shell.identifier'))->required()->maxLength(80),
                        TextInput::make('label')->required()->maxLength(120),
                    ])->columns(2)->reorderable()->collapsible()->columnSpanFull(),
                    Repeater::make($eventsPrefix.'.upcoming')->label('Upcoming Events')->schema($this->eventFields(false))->reorderable()->collapsible()->columnSpanFull(),
                    Repeater::make($eventsPrefix.'.past')->label('Past Events')->schema($this->eventFields(true))->reorderable()->collapsible()->columnSpanFull(),
                ])
                ->columns(2)
                ->visible(fn (): bool => $this->targetKeyForSchema() === 'news.events'),
            Section::make(__('admin.editorial_workspace.news_shell.media_gallery'))
                ->schema([
                    TextInput::make($galleryPrefix.'.title')->label(__('admin.editorial_workspace.news_shell.page_title'))->required()->maxLength(160),
                    Textarea::make($galleryPrefix.'.summary')->label(__('admin.editorial_workspace.news_shell.page_summary'))->required()->rows(2)->columnSpanFull(),
                    MediaPicker::image($galleryPrefix.'.heroImage', __('admin.editorial_workspace.news_shell.hero_image'), true),
                    TextInput::make($galleryPrefix.'.allLabel')->label(__('admin.editorial_workspace.news_shell.all_images'))->required()->maxLength(120),
                    TextInput::make($galleryPrefix.'.latestLabel')->label(__('admin.editorial_workspace.news_shell.latest'))->required()->maxLength(120),
                    Textarea::make($galleryPrefix.'.emptyLabel')->label(__('admin.editorial_workspace.news_shell.empty_state'))->required()->rows(2),
                    TextInput::make($galleryPrefix.'.openLabel')->label(__('admin.editorial_workspace.news_shell.open_image'))->required()->maxLength(120),
                    TextInput::make($galleryPrefix.'.closeLabel')->label(__('admin.editorial_workspace.news_shell.close_viewer'))->required()->maxLength(120),
                    TextInput::make($galleryPrefix.'.previousLabel')->label(__('admin.editorial_workspace.news_shell.previous_image'))->required()->maxLength(120),
                    TextInput::make($galleryPrefix.'.nextLabel')->label(__('admin.editorial_workspace.news_shell.next_image'))->required()->maxLength(120),
                    Repeater::make($galleryPrefix.'.categories')->label(__('admin.editorial_workspace.news_shell.gallery_categories'))->schema([
                        TextInput::make('id')->label(__('admin.editorial_workspace.news_shell.identifier'))->required()->maxLength(80),
                        TextInput::make('label')->label(__('admin.editorial_workspace.news_shell.category_label'))->required()->maxLength(120),
                    ])->columns(2)->reorderable()->collapsible()->columnSpanFull(),
                    Repeater::make($galleryPrefix.'.items')->label(__('admin.editorial_workspace.news_shell.gallery_images'))->schema([
                        TextInput::make('id')->label(__('admin.editorial_workspace.news_shell.identifier'))->required()->maxLength(80),
                        MediaPicker::assetImage('mediaId', __('admin.editorial_workspace.news_shell.gallery_image'), true),
                        TextInput::make('categoryId')->label(__('admin.editorial_workspace.news_shell.category_id'))->required()->maxLength(80),
                        TextInput::make('categoryLabel')->label(__('admin.editorial_workspace.news_shell.category_label'))->required()->maxLength(120),
                        TextInput::make('dateLabel')->label(__('admin.editorial_workspace.news_shell.date_label'))->required()->maxLength(120),
                        Select::make('featured')->label(__('admin.editorial_workspace.news_shell.featured'))->options([
                            '0' => __('admin.editorial_workspace.news_shell.no'),
                            '1' => __('admin.editorial_workspace.news_shell.yes'),
                        ])->required(),
                    ])->columns(2)->reorderable()->collapsible()->columnSpanFull(),
                ])
                ->columns(2)
                ->visible(fn (): bool => $this->targetKeyForSchema() === 'news.gallery'),
            Section::make(__('admin.editorial_workspace.news_shell.hero'))->schema([
                TextInput::make($indexPrefix.'.pageTitle')->label(__('admin.editorial_workspace.news_shell.page_title'))->required()->maxLength(160),
                TextInput::make($indexPrefix.'.heroTitle')->label(__('admin.editorial_workspace.news_shell.hero_title'))->required()->maxLength(160),
                Textarea::make($indexPrefix.'.pageDescription')->label(__('admin.editorial_workspace.news_shell.description'))->required()->rows(2)->columnSpanFull(),
                MediaPicker::image($indexPrefix.'.heroImage', __('admin.editorial_workspace.news_shell.hero_image'), true),
                Repeater::make($indexPrefix.'.heroLinks')
                    ->label(__('admin.editorial_workspace.news_shell.hero_links'))
                    ->schema([
                        TextInput::make('id')->required()->maxLength(80),
                        TextInput::make('label')->label(__('admin.editorial_workspace.news_shell.link_label'))->required()->maxLength(120),
                    ])
                    ->columns(2)
                    ->defaultItems(0)
                    ->reorderable()
                    ->collapsible()
                    ->columnSpanFull(),
            ])->columns(2)->visible(fn (): bool => $this->targetKeyForSchema() === 'news.index'),

            Section::make(__('admin.editorial_workspace.news_shell.sections'))->schema([
                TextInput::make($indexPrefix.'.lastNewsTitle')->label(__('admin.editorial_workspace.news_shell.last_news_title'))->required()->maxLength(160),
                TextInput::make($indexPrefix.'.lastNewsViewAllLabel')->label(__('admin.editorial_workspace.news_shell.last_news_view_all'))->required()->maxLength(160),
                TextInput::make($indexPrefix.'.announcementsTitle')->label(__('admin.editorial_workspace.news_shell.announcements_title'))->required()->maxLength(160),
                TextInput::make($indexPrefix.'.announcementsViewAllLabel')->label(__('admin.editorial_workspace.news_shell.announcements_view_all'))->required()->maxLength(160),
                TextInput::make($indexPrefix.'.eventsTitle')->label(__('admin.editorial_workspace.news_shell.events_title'))->required()->maxLength(160),
                TextInput::make($indexPrefix.'.eventsViewAllLabel')->label(__('admin.editorial_workspace.news_shell.events_view_all'))->required()->maxLength(160),
                TextInput::make($indexPrefix.'.exploreMoreTitle')->label(__('admin.editorial_workspace.news_shell.explore_more_title'))->required()->maxLength(160),
            ])->columns(2)->visible(fn (): bool => $this->targetKeyForSchema() === 'news.index'),

            Section::make(__('admin.editorial_workspace.news_shell.cards_labels'))->schema([
                TextInput::make($indexPrefix.'.archiveTitle')->label(__('admin.editorial_workspace.news_shell.archive_title'))->required()->maxLength(160),
                TextInput::make($indexPrefix.'.archiveCta')->label(__('admin.editorial_workspace.news_shell.archive_cta'))->required()->maxLength(120),
                TextInput::make($indexPrefix.'.announcementsCardTitle')->label(__('admin.editorial_workspace.news_shell.announcements_card_title'))->required()->maxLength(160),
                TextInput::make($indexPrefix.'.announcementsCardCta')->label(__('admin.editorial_workspace.news_shell.announcements_card_cta'))->required()->maxLength(120),
                TextInput::make($indexPrefix.'.readMoreLabel')->label(__('admin.editorial_workspace.news_shell.read_more'))->required()->maxLength(80),
                TextInput::make($indexPrefix.'.viewDetailsLabel')->label(__('admin.editorial_workspace.news_shell.view_details'))->required()->maxLength(80),
                TextInput::make($indexPrefix.'.newLabel')->label(__('admin.editorial_workspace.news_shell.new_badge'))->required()->maxLength(80),
                TextInput::make($indexPrefix.'.newsFallbackCategory')->label(__('admin.editorial_workspace.news_shell.news_fallback_category'))->required()->maxLength(120),
                TextInput::make($indexPrefix.'.universityNewsFallbackCategory')->label(__('admin.editorial_workspace.news_shell.university_news_fallback_category'))->required()->maxLength(120),
                Textarea::make($indexPrefix.'.emptyAnnouncements')->label(__('admin.editorial_workspace.news_shell.empty_announcements'))->required()->rows(2)->columnSpanFull(),
            ])->columns(2)->visible(fn (): bool => $this->targetKeyForSchema() === 'news.index'),
        ];
    }

    /** @return array<int, mixed> */
    private function eventsWorkspaceFields(): array
    {
        return [
            Hidden::make('events_workspace.ar_meta'),
            Hidden::make('events_workspace.en_meta'),
            Repeater::make('events_workspace.upcoming')
                ->label(__('admin.editorial_workspace.events.upcoming'))
                ->addActionLabel(__('admin.editorial_workspace.events.add_upcoming'))
                ->schema($this->eventWorkspaceSchema(false))
                ->itemLabel(fn (array $state): string => (string) ($state[app()->getLocale() === 'ar' ? 'title_ar' : 'title_en'] ?? $state['title_ar'] ?? $state['title_en'] ?? ''))
                ->reorderable()
                ->collapsible()
                ->collapsed()
                ->columnSpanFull(),
            Repeater::make('events_workspace.past')
                ->label(__('admin.editorial_workspace.events.past'))
                ->addActionLabel(__('admin.editorial_workspace.events.add_past'))
                ->schema($this->eventWorkspaceSchema(true))
                ->itemLabel(fn (array $state): string => (string) ($state[app()->getLocale() === 'ar' ? 'title_ar' : 'title_en'] ?? $state['title_ar'] ?? $state['title_en'] ?? ''))
                ->reorderable()
                ->collapsible()
                ->collapsed()
                ->columnSpanFull(),
            Section::make(__('admin.editorial_workspace.events.categories'))->collapsed()->schema([
                Repeater::make('events_workspace.categories')
                    ->hiddenLabel()
                    ->addActionLabel(__('admin.editorial_workspace.events.add_category'))
                    ->schema([
                        Hidden::make('id')->default(fn (): string => 'category-'.Str::lower(Str::random(8))),
                        TextInput::make('label_ar')->label(__('admin.editorial_workspace.fields.label_ar'))->required()->maxLength(120),
                        TextInput::make('label_en')->label(__('admin.editorial_workspace.fields.label_en'))->required()->maxLength(120),
                    ])
                    ->columns(2)
                    ->reorderable()
                    ->collapsible(),
            ]),
            Section::make(__('admin.editorial_workspace.events.page_intro'))->collapsed()->schema([
                Tabs::make('event_page_locales')->tabs([
                    Tab::make(__('admin.locales.ar'))->extraAttributes(['dir' => 'rtl'])->schema([
                        TextInput::make('events_workspace.page_ar.title')->label(__('admin.editorial_workspace.fields.page_title'))->required()->maxLength(160),
                        Textarea::make('events_workspace.page_ar.summary')->label(__('admin.editorial_workspace.fields.page_summary'))->required()->rows(2)->columnSpanFull(),
                        MediaPicker::image('events_workspace.page_ar.heroImage', __('admin.editorial_workspace.fields.hero_image'), true),
                    ]),
                    Tab::make(__('admin.locales.en'))->extraAttributes(['dir' => 'ltr'])->schema([
                        TextInput::make('events_workspace.page_en.title')->label(__('admin.editorial_workspace.fields.page_title'))->required()->maxLength(160),
                        Textarea::make('events_workspace.page_en.summary')->label(__('admin.editorial_workspace.fields.page_summary'))->required()->rows(2)->columnSpanFull(),
                        MediaPicker::image('events_workspace.page_en.heroImage', __('admin.editorial_workspace.fields.hero_image'), true),
                    ]),
                ]),
            ]),
        ];
    }

    /** @return array<int, mixed> */
    private function eventWorkspaceSchema(bool $past): array
    {
        $fields = [
            Hidden::make('id')->default(fn (): string => 'event-'.Str::lower(Str::random(10))),
            Section::make(__('admin.editorial_workspace.events.sections.schedule'))->schema([
                DateTimePicker::make('startsAt')->label(__('admin.editorial_workspace.fields.starts_at'))->required()->seconds(false),
                DateTimePicker::make('endsAt')
                    ->label(__('admin.editorial_workspace.fields.ends_at'))
                    ->helperText(__('admin.editorial_workspace.fields.ends_at_help'))
                    ->afterOrEqual('startsAt')
                    ->validationMessages(['after_or_equal' => __('admin.editorial_workspace.validation.ends_after_start')])
                    ->seconds(false),
                Radio::make('categoryId')
                    ->label(__('admin.editorial_workspace.fields.category'))
                    ->options(fn (): array => $this->eventCategoryOptions())
                    ->required()
                    ->inline()
                    ->columnSpanFull(),
                MediaPicker::image('image', __('admin.editorial_workspace.fields.event_image'), true),
            ])->columns(2),
            Section::make(__('admin.editorial_workspace.events.sections.arabic'))->schema($this->localizedEventWorkspaceFields('ar', $past))->columns(2)->extraAttributes(['dir' => 'rtl']),
            Section::make(__('admin.editorial_workspace.events.sections.english'))->schema($this->localizedEventWorkspaceFields('en', $past))->columns(2)->extraAttributes(['dir' => 'ltr']),
        ];

        if (! $past) {
            array_splice($fields, 1, 0, [
                Section::make(__('admin.editorial_workspace.events.sections.registration'))->schema([
                    Radio::make('formId')->label(__('admin.editorial_workspace.fields.registration_form'))->options([
                        'conference-registration' => __('admin.editorial_workspace.events.forms.conference'),
                        'activity-registration' => __('admin.editorial_workspace.events.forms.activity'),
                    ])->required()->inline(),
                    TextInput::make('capacity')->label(__('admin.editorial_workspace.fields.capacity'))->numeric()->minValue(0)->required(),
                    Hidden::make('registered')->default(0),
                    Toggle::make('featured')->label(__('admin.editorial_workspace.fields.featured')),
                ])->columns(2),
            ]);
        } else {
            $fields[] = Section::make(__('admin.editorial_workspace.events.sections.advanced'))->collapsed()->schema([
                TagsInput::make('gallery')->label(__('admin.editorial_workspace.fields.gallery'))->columnSpanFull(),
            ]);
        }

        return $fields;
    }

    /** @return array<int, mixed> */
    private function localizedEventWorkspaceFields(string $locale, bool $past): array
    {
        $fields = [
            TextInput::make('title_'.$locale)->label(__('admin.editorial_workspace.fields.event_title'))->required()->maxLength(200)->columnSpanFull(),
            Textarea::make('summary_'.$locale)->label(__('admin.editorial_workspace.fields.summary'))->required()->rows(2)->columnSpanFull(),
            TextInput::make('date_label_'.$locale)->label(__('admin.editorial_workspace.fields.date_label'))->required()->maxLength(120),
            TextInput::make('time_label_'.$locale)->label(__('admin.editorial_workspace.fields.time_label'))->required()->maxLength(120),
            TextInput::make('location_'.$locale)->label(__('admin.editorial_workspace.fields.location'))->required()->maxLength(200)->columnSpanFull(),
        ];

        if ($past) {
            $fields[] = TextInput::make('participants_'.$locale)->label(__('admin.editorial_workspace.fields.participants'))->maxLength(120);
            $fields[] = TagsInput::make('highlights_'.$locale)->label(__('admin.editorial_workspace.fields.highlights'))->columnSpanFull();
            $fields[] = Repeater::make('speakers_'.$locale)->label(__('admin.editorial_workspace.fields.speakers'))->schema([
                TextInput::make('name')->label(__('admin.editorial_workspace.fields.speaker_name'))->required()->maxLength(160),
                TextInput::make('title')->label(__('admin.editorial_workspace.fields.speaker_title'))->required()->maxLength(200),
            ])->columns(2)->collapsible()->columnSpanFull();
            $fields[] = Textarea::make('results_'.$locale)->label(__('admin.editorial_workspace.fields.results'))->rows(2)->columnSpanFull();
        }

        return $fields;
    }

    /** @param array<string, mixed> $ar @param array<string, mixed> $en @return array<string, mixed> */
    private function eventsWorkspaceFromTranslations(array $ar, array $en): array
    {
        $arCategories = collect($this->listOfArrays($ar['categories'] ?? []))->keyBy(fn (array $category): string => (string) ($category['id'] ?? ''));
        $enCategories = collect($this->listOfArrays($en['categories'] ?? []))->keyBy(fn (array $category): string => (string) ($category['id'] ?? ''));
        $categoryIds = $arCategories->keys()->merge($enCategories->keys())->filter()->unique()->values();
        $categories = $categoryIds->map(fn (string $id): array => [
            'id' => $id,
            'label_ar' => (string) (($arCategories->get($id)['label'] ?? null) ?: $id),
            'label_en' => (string) (($enCategories->get($id)['label'] ?? null) ?: $id),
        ])->all();

        $workspace = [
            'page_ar' => $this->eventPageWorkspaceFields($ar),
            'page_en' => $this->eventPageWorkspaceFields($en),
            'categories' => $categories,
            'upcoming' => $this->pairedEvents($ar['upcoming'] ?? [], $en['upcoming'] ?? [], false),
            'past' => $this->pairedEvents($ar['past'] ?? [], $en['past'] ?? [], true),
        ];

        unset($ar['categories'], $ar['upcoming'], $ar['past'], $en['categories'], $en['upcoming'], $en['past']);
        $workspace['ar_meta'] = $ar;
        $workspace['en_meta'] = $en;

        return $workspace;
    }

    /** @param array<string, mixed> $payload @return array<string, string> */
    private function eventPageWorkspaceFields(array $payload): array
    {
        return [
            'title' => (string) ($payload['title'] ?? ''),
            'summary' => (string) ($payload['summary'] ?? ''),
            'heroImage' => (string) ($payload['heroImage'] ?? ''),
        ];
    }

    /** @return array<int, array<string, mixed>> */
    private function pairedEvents(mixed $arRecords, mixed $enRecords, bool $past): array
    {
        $arEvents = collect($this->listOfArrays($arRecords))->keyBy(fn (array $event): string => (string) ($event['id'] ?? ''));
        $enEvents = collect($this->listOfArrays($enRecords))->keyBy(fn (array $event): string => (string) ($event['id'] ?? ''));

        return $arEvents->keys()->merge($enEvents->keys())->filter()->unique()->values()
            ->map(function (string $id) use ($arEvents, $enEvents, $past): array {
                $ar = is_array($arEvents->get($id)) ? $arEvents->get($id) : [];
                $en = is_array($enEvents->get($id)) ? $enEvents->get($id) : [];
                $shared = $en !== [] ? $en : $ar;

                return [
                    'id' => $id,
                    'startsAt' => (string) ($shared['startsAt'] ?? ''),
                    'endsAt' => is_string($shared['endsAt'] ?? null) ? $shared['endsAt'] : null,
                    'categoryId' => (string) ($shared['categoryId'] ?? ''),
                    'image' => (string) ($shared['image'] ?? ''),
                    'formId' => $past ? null : (string) ($shared['formId'] ?? ''),
                    'capacity' => $past ? null : ($shared['capacity'] ?? 0),
                    'registered' => $past ? 0 : ($shared['registered'] ?? 0),
                    'featured' => ! $past && (bool) ($shared['featured'] ?? false),
                    'gallery' => $past ? $this->stringList($shared['gallery'] ?? []) : [],
                    ...$this->localizedEventWorkspaceState($ar, 'ar', $past),
                    ...$this->localizedEventWorkspaceState($en, 'en', $past),
                ];
            })->all();
    }

    /** @param array<string, mixed> $event @return array<string, mixed> */
    private function localizedEventWorkspaceState(array $event, string $locale, bool $past): array
    {
        $state = [
            'title_'.$locale => (string) ($event['title'] ?? ''),
            'summary_'.$locale => (string) ($event['summary'] ?? ''),
            'date_label_'.$locale => (string) ($event['dateLabel'] ?? ''),
            'time_label_'.$locale => (string) ($event['timeLabel'] ?? ''),
            'location_'.$locale => (string) ($event['location'] ?? ''),
        ];

        if ($past) {
            $state['participants_'.$locale] = (string) ($event['participants'] ?? '');
            $state['highlights_'.$locale] = $this->stringList($event['highlights'] ?? []);
            $state['speakers_'.$locale] = $this->listOfArrays($event['speakers'] ?? []);
            $state['results_'.$locale] = (string) ($event['results'] ?? '');
        }

        return $state;
    }

    /** @param array<string, mixed> $workspace @return array<string, mixed> */
    private function eventsTranslationFromWorkspace(array $workspace, string $locale): array
    {
        $payload = is_array($workspace[$locale.'_meta'] ?? null) ? $workspace[$locale.'_meta'] : [];
        $page = is_array($workspace['page_'.$locale] ?? null) ? $workspace['page_'.$locale] : [];
        $payload['title'] = (string) ($page['title'] ?? '');
        $payload['headline'] = $payload['title'];
        $payload['summary'] = (string) ($page['summary'] ?? '');
        $payload['heroImage'] = (string) ($page['heroImage'] ?? '');
        $payload['categories'] = array_map(fn (array $category): array => [
            'id' => (string) ($category['id'] ?? ''),
            'label' => (string) ($category['label_'.$locale] ?? ''),
        ], $this->listOfArrays($workspace['categories'] ?? []));
        $categoryLabels = collect($payload['categories'])->pluck('label', 'id')->all();
        $payload['upcoming'] = $this->eventsForTranslation($workspace['upcoming'] ?? [], $locale, false, $categoryLabels);
        $payload['past'] = $this->eventsForTranslation($workspace['past'] ?? [], $locale, true, $categoryLabels);

        return $payload;
    }

    /** @param array<string, string> $categoryLabels @return array<int, array<string, mixed>> */
    private function eventsForTranslation(mixed $records, string $locale, bool $past, array $categoryLabels): array
    {
        return array_map(function (array $event) use ($locale, $past, $categoryLabels): array {
            $categoryId = (string) ($event['categoryId'] ?? '');
            $translated = [
                'id' => (string) ($event['id'] ?? ''),
                'title' => (string) ($event['title_'.$locale] ?? ''),
                'summary' => (string) ($event['summary_'.$locale] ?? ''),
                'startsAt' => (string) ($event['startsAt'] ?? ''),
                'endsAt' => is_string($event['endsAt'] ?? null) && $event['endsAt'] !== '' ? $event['endsAt'] : null,
                'dateLabel' => (string) ($event['date_label_'.$locale] ?? ''),
                'timeLabel' => (string) ($event['time_label_'.$locale] ?? ''),
                'location' => (string) ($event['location_'.$locale] ?? ''),
                'categoryId' => $categoryId,
                'categoryLabel' => (string) ($categoryLabels[$categoryId] ?? ''),
                'image' => (string) ($event['image'] ?? ''),
            ];

            if (! $past) {
                return [
                    ...$translated,
                    'formId' => (string) ($event['formId'] ?? ''),
                    'capacity' => (int) ($event['capacity'] ?? 0),
                    'registered' => (int) ($event['registered'] ?? 0),
                    'featured' => (bool) ($event['featured'] ?? false),
                ];
            }

            return [
                ...$translated,
                'participants' => (string) ($event['participants_'.$locale] ?? ''),
                'highlights' => $this->stringList($event['highlights_'.$locale] ?? []),
                'speakers' => $this->listOfArrays($event['speakers_'.$locale] ?? []),
                'results' => (string) ($event['results_'.$locale] ?? ''),
                'gallery' => $this->stringList($event['gallery'] ?? []),
            ];
        }, $this->listOfArrays($records));
    }

    /** @return array<string, string> */
    private function eventCategoryOptions(): array
    {
        $localeKey = app()->getLocale() === 'ar' ? 'label_ar' : 'label_en';

        return collect($this->listOfArrays($this->data['events_workspace']['categories'] ?? []))
            ->mapWithKeys(fn (array $category): array => [
                (string) ($category['id'] ?? '') => (string) ($category[$localeKey] ?? $category['label_ar'] ?? $category['label_en'] ?? ''),
            ])
            ->filter(fn (string $label, string $id): bool => $id !== '' && $label !== '')
            ->all();
    }

    /** @return array<int, array<string, mixed>> */
    private function listOfArrays(mixed $items): array
    {
        return array_values(array_filter(is_array($items) ? $items : [], static fn (mixed $item): bool => is_array($item)));
    }

    /** @return array<int, string> */
    private function stringList(mixed $items): array
    {
        return array_values(array_filter(array_map(
            static fn (mixed $item): string => trim((string) $item),
            is_array($items) ? $items : [],
        ), static fn (string $item): bool => $item !== ''));
    }

    private function targetKeyForSchema(): string
    {
        if (is_string($this->activeTargetKey) && $this->activeTargetKey !== '') {
            return $this->activeTargetKey;
        }

        return is_string($this->data['target_key'] ?? null) && $this->data['target_key'] !== ''
            ? $this->data['target_key']
            : $this->defaultNewsTargetKey();
    }

    private function validateEventDates(): void
    {
        if ($this->currentTargetKey() !== 'news.events') {
            return;
        }

        $rules = [];

        foreach (['upcoming', 'past'] as $group) {
            $events = is_array($this->data['events_workspace'][$group] ?? null) ? $this->data['events_workspace'][$group] : [];

            foreach ($events as $key => $event) {
                if (! is_array($event)) {
                    continue;
                }

                $prefix = 'data.events_workspace.'.$group.'.'.$key;
                $rules[$prefix.'.endsAt'] = ['nullable', 'date', 'after_or_equal:'.$prefix.'.startsAt'];
            }
        }

        if ($rules !== []) {
            $this->validate($rules, [
                'data.events_workspace.*.*.endsAt.after_or_equal' => __('admin.editorial_workspace.validation.ends_after_start'),
            ]);
        }
    }

    /** @return list<array{label: string, description: string, url: string}> */
    public function getNewsOperationalLinks(): array
    {
        if ($this->targetKeyForSchema() !== 'news.announcements') {
            return [];
        }

        return [
            [
                'label' => __('admin.editorial_workspace.announcements.records'),
                'description' => __('admin.editorial_workspace.announcements.records_help'),
                'url' => NewsArticleResource::getUrl('index', [
                    'tableFilters' => ['category_type' => ['value' => 'announcement']],
                ]),
            ],
        ];
    }

    /** @return array<string, mixed> */
    private function normalizeIndexPayload(array $payload): array
    {
        $payload['title'] = (string) ($payload['pageTitle'] ?? ($payload['title'] ?? ''));
        $payload['headline'] = (string) ($payload['heroTitle'] ?? ($payload['headline'] ?? ''));
        $payload['summary'] = (string) ($payload['pageDescription'] ?? ($payload['summary'] ?? ''));

        return $payload;
    }

    /** @return array<string, mixed> */
    private function normalizeAnnouncementsPayload(array $payload): array
    {
        $payload['title'] = (string) ($payload['pageTitle'] ?? '');
        $payload['headline'] = (string) ($payload['pageTitle'] ?? '');
        $payload['summary'] = (string) ($payload['pageDescription'] ?? '');

        return $payload;
    }

    /** @return array<int, mixed> */
    private function eventFields(bool $past): array
    {
        $fields = [
            TextInput::make('id')->required()->maxLength(80),
            TextInput::make('title')->required()->maxLength(200),
            Textarea::make('summary')->required()->rows(2)->columnSpanFull(),
            DateTimePicker::make('startsAt')->required()->seconds(false),
            DateTimePicker::make('endsAt')->seconds(false),
            TextInput::make('dateLabel')->required()->maxLength(120),
            TextInput::make('timeLabel')->required()->maxLength(120),
            TextInput::make('location')->required()->maxLength(200),
            TextInput::make('categoryId')->required()->maxLength(80),
            TextInput::make('categoryLabel')->required()->maxLength(120),
            MediaPicker::image('image', 'Image', true),
        ];

        if (! $past) {
            $fields[] = Select::make('formId')->options([
                'conference-registration' => 'Conference Registration',
                'activity-registration' => 'Activity Registration',
            ])->required();
            $fields[] = TextInput::make('capacity')->numeric()->minValue(0)->required();
            $fields[] = TextInput::make('registered')->numeric()->minValue(0)->required();
            $fields[] = Select::make('featured')->options(['0' => 'No', '1' => 'Yes'])->required();

            return $fields;
        }

        $fields[] = TextInput::make('participants')->maxLength(120);
        $fields[] = TagsInput::make('highlights')->columnSpanFull();
        $fields[] = Repeater::make('speakers')->schema([
            TextInput::make('name')->required()->maxLength(160),
            TextInput::make('title')->required()->maxLength(200),
        ])->columns(2)->columnSpanFull();
        $fields[] = Textarea::make('results')->rows(2)->columnSpanFull();
        $fields[] = TagsInput::make('gallery')->columnSpanFull();

        return $fields;
    }

    /** @return array<string, mixed> */
    private function normalizeEventsPayload(array $payload): array
    {
        $payload['headline'] = (string) ($payload['title'] ?? '');

        return $payload;
    }

    /** @return array<string, mixed> */
    private function normalizeGalleryPayload(array $payload): array
    {
        $payload['headline'] = (string) ($payload['title'] ?? '');

        return $payload;
    }

    /** @param array<string, array<int, string>> $errors */
    private function formatValidationErrors(array $errors): string
    {
        return collect($errors)->flatten()->implode(PHP_EOL);
    }
}
