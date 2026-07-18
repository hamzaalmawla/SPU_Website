<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Contracts\Cms\CmsTargetRegistryInterface;
use App\Contracts\Cms\CmsWorkflowServiceInterface;
use App\Contracts\News\NewsServiceInterface;
use App\DTOs\Cms\CmsTargetDTO;
use App\Exceptions\ConflictException;
use App\Filament\Support\MediaPicker;
use App\Models\User\User;
use Filament\Actions\Action;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Tabs;
use Filament\Forms\Components\Tabs\Tab;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Gate;
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
        $this->loadTarget('news.index');
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make('News Target')->schema([
                    Select::make('target_key')
                        ->label('Page / Subpage')
                        ->options($this->targetOptions())
                        ->required()
                        ->live()
                        ->afterStateUpdated(fn (?string $state): mixed => is_string($state) && $state !== '' ? $this->loadTarget($state) : null),
                ]),
                Tabs::make('news_locales')
                    ->tabs([
                        Tab::make('Arabic')->schema($this->payloadFields('ar')),
                        Tab::make('English')->schema($this->payloadFields('en')),
                    ])
                    ->persistTabInQueryString('locale')
                    ->columnSpanFull(),
            ])
            ->statePath('data');
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('save')->label('Save Draft')->icon('heroicon-o-check')->color('gray')->action(function (): void {
                $this->save();
            }),
            Action::make('preview_ar')->label('Preview AR')->icon('heroicon-o-eye')->color('info')->action(function (): void {
                $this->openPreview('ar');
            }),
            Action::make('preview_en')->label('Preview EN')->icon('heroicon-o-eye')->color('info')->action(function (): void {
                $this->openPreview('en');
            }),
            Action::make('publish')->label('Publish')->icon('heroicon-o-paper-airplane')->color('success')->requiresConfirmation()->action(function (): void {
                $this->publish();
            }),
            Action::make('schedule')
                ->label('Schedule')
                ->icon('heroicon-o-clock')
                ->color('warning')
                ->form([
                    DateTimePicker::make('publish_at')->label('Publish At')->required()->minDate(now())->native(false),
                ])
                ->action(fn (array $data) => $this->schedule((string) $data['publish_at'])),
            Action::make('unpublish')->label('Unpublish')->icon('heroicon-o-x-circle')->color('danger')->requiresConfirmation()->action(function (): void {
                $this->unpublish();
            }),
        ];
    }

    public function loadTarget(string $targetKey): void
    {
        $this->assertNewsTarget($targetKey);

        if (! in_array($targetKey, ['news.index', 'news.announcements', 'news.events', 'news.gallery'], true)) {
            $this->draftVersion = $this->cmsWorkflowService->latestEditableDraftVersion($targetKey);
            $this->form->fill([
                'target_key' => $targetKey,
                'ar_index' => [],
                'en_index' => [],
            ]);

            return;
        }

        $draftPayload = $this->cmsWorkflowService->latestEditableDraftPayload($targetKey);
        $payload = is_array($draftPayload) ? $draftPayload : $this->newsService->getEditablePayload($targetKey);
        $this->draftVersion = $this->cmsWorkflowService->latestEditableDraftVersion($targetKey);

        if ($targetKey === 'news.announcements') {
            $this->form->fill([
                'target_key' => $targetKey,
                'ar_target' => is_array($payload['translations']['ar'] ?? null) ? $payload['translations']['ar'] : [],
                'en_target' => is_array($payload['translations']['en'] ?? null) ? $payload['translations']['en'] : [],
            ]);

            return;
        }

        if ($targetKey === 'news.events') {
            $this->form->fill([
                'target_key' => $targetKey,
                'ar_events' => is_array($payload['translations']['ar'] ?? null) ? $payload['translations']['ar'] : [],
                'en_events' => is_array($payload['translations']['en'] ?? null) ? $payload['translations']['en'] : [],
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
        /** @var User $user */
        $user = auth()->user();

        try {
            $draft = $this->cmsWorkflowService->saveDraft($this->currentTargetKey(), $this->payloadFromForm($this->currentFormData()), (int) $user->id, $this->draftVersion);
            $this->draftVersion = $draft->version;

            Notification::make()->title('News draft saved')->success()->send();
        } catch (ConflictException $e) {
            $this->draftVersion = $e->currentVersion;
            Notification::make()->title('Draft conflict detected')->body('Reload this news target before saving again.')->danger()->persistent()->send();
        } catch (\Throwable $e) {
            report($e);
            Notification::make()->title('Failed to save news draft')->body($e->getMessage())->danger()->send();
        }
    }

    public function openPreview(string $locale): void
    {
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
            Notification::make()->title('Draft conflict detected')->body('Reload this news target before previewing again.')->danger()->persistent()->send();
        } catch (\Throwable $e) {
            report($e);
            Notification::make()->title('Failed to create news preview')->body($e->getMessage())->danger()->send();
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

            Notification::make()->title('News target published')->success()->send();
        } catch (ValidationException $e) {
            Notification::make()->title('Publish failed')->body($this->formatValidationErrors($e->errors()))->danger()->persistent()->send();
        } catch (\Throwable $e) {
            report($e);
            Notification::make()->title('Failed to publish news target')->body($e->getMessage())->danger()->send();
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

            Notification::make()->title('News target scheduled')->success()->send();
        } catch (ValidationException $e) {
            Notification::make()->title('Schedule failed')->body($this->formatValidationErrors($e->errors()))->danger()->persistent()->send();
        } catch (\Throwable $e) {
            report($e);
            Notification::make()->title('Failed to schedule news target')->body($e->getMessage())->danger()->send();
        }
    }

    public function unpublish(): void
    {
        /** @var User $user */
        $user = auth()->user();
        $result = $this->cmsWorkflowService->unpublish($this->currentTargetKey(), (int) $user->id);
        $notification = Notification::make()->title($result ? 'News target unpublished' : 'No published news target found');

        ($result ? $notification->success() : $notification->warning())->send();
    }

    /** @return array<string, string> */
    private function targetOptions(): array
    {
        return $this->targetRegistry->forArea('news')
            ->mapWithKeys(fn (CmsTargetDTO $target): array => [$target->key => __($target->labelKey)])
            ->all();
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

        if (($state['target_key'] ?? null) === 'news.events') {
            return [
                'translations' => [
                    'ar' => is_array($state['ar_events'] ?? null) ? $this->normalizeEventsPayload($state['ar_events']) : [],
                    'en' => is_array($state['en_events'] ?? null) ? $this->normalizeEventsPayload($state['en_events']) : [],
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

        return [
            Section::make('Announcement Page')
                ->schema([
                    TextInput::make($announcementPrefix.'.pageTitle')->label('Page Title')->required()->maxLength(160),
                    Textarea::make($announcementPrefix.'.pageDescription')->label('Description')->required()->rows(2)->columnSpanFull(),
                    MediaPicker::image($announcementPrefix.'.heroImage', 'Hero Image', true),
                    TextInput::make($announcementPrefix.'.featuredLabel')->label('Featured Label')->required()->maxLength(120),
                    TextInput::make($announcementPrefix.'.allCategoriesLabel')->label('All Categories Label')->required()->maxLength(120),
                    TextInput::make($announcementPrefix.'.readMoreLabel')->label('Read More Label')->required()->maxLength(120),
                    TextInput::make($announcementPrefix.'.downloadLabel')->label('Download Label')->required()->maxLength(120),
                    Textarea::make($announcementPrefix.'.emptyState')->label('Empty State')->required()->rows(2)->columnSpanFull(),
                ])
                ->columns(2)
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
                        TextInput::make('id')->required()->maxLength(80),
                        TextInput::make('label')->required()->maxLength(120),
                    ])->columns(2)->reorderable()->collapsible()->columnSpanFull(),
                    Repeater::make($eventsPrefix.'.upcoming')->label('Upcoming Events')->schema($this->eventFields(false))->reorderable()->collapsible()->columnSpanFull(),
                    Repeater::make($eventsPrefix.'.past')->label('Past Events')->schema($this->eventFields(true))->reorderable()->collapsible()->columnSpanFull(),
                ])
                ->columns(2)
                ->visible(fn (): bool => $this->targetKeyForSchema() === 'news.events'),
            Section::make('Media Gallery')
                ->schema([
                    TextInput::make($galleryPrefix.'.title')->label('Page Title')->required()->maxLength(160),
                    Textarea::make($galleryPrefix.'.summary')->label('Page Summary')->required()->rows(2)->columnSpanFull(),
                    MediaPicker::image($galleryPrefix.'.heroImage', 'Hero Image', true),
                    TextInput::make($galleryPrefix.'.allLabel')->label('All Images Label')->required()->maxLength(120),
                    TextInput::make($galleryPrefix.'.latestLabel')->label('Latest Label')->required()->maxLength(120),
                    Textarea::make($galleryPrefix.'.emptyLabel')->label('Empty State')->required()->rows(2),
                    TextInput::make($galleryPrefix.'.openLabel')->label('Open Image Label')->required()->maxLength(120),
                    TextInput::make($galleryPrefix.'.closeLabel')->label('Close Viewer Label')->required()->maxLength(120),
                    TextInput::make($galleryPrefix.'.previousLabel')->label('Previous Image Label')->required()->maxLength(120),
                    TextInput::make($galleryPrefix.'.nextLabel')->label('Next Image Label')->required()->maxLength(120),
                    Repeater::make($galleryPrefix.'.categories')->label('Gallery Categories')->schema([
                        TextInput::make('id')->required()->maxLength(80),
                        TextInput::make('label')->required()->maxLength(120),
                    ])->columns(2)->reorderable()->collapsible()->columnSpanFull(),
                    Repeater::make($galleryPrefix.'.items')->label('Gallery Images')->schema([
                        TextInput::make('id')->required()->maxLength(80),
                        MediaPicker::assetImage('mediaId', 'Gallery Image', true),
                        TextInput::make('categoryId')->required()->maxLength(80),
                        TextInput::make('categoryLabel')->required()->maxLength(120),
                        TextInput::make('dateLabel')->required()->maxLength(120),
                        Select::make('featured')->options(['0' => 'No', '1' => 'Yes'])->required(),
                    ])->columns(2)->reorderable()->collapsible()->columnSpanFull(),
                ])
                ->columns(2)
                ->visible(fn (): bool => $this->targetKeyForSchema() === 'news.gallery'),
            Section::make('Target Schema Pending')
                ->description('The selected News target does not have its curated editor yet.')
                ->schema([
                    TextInput::make($locale.'_target_pending')->label('Status')->default('Structured form pending for this news target')->disabled(),
                ])
                ->visible(fn (): bool => ! in_array($this->targetKeyForSchema(), ['news.index', 'news.announcements', 'news.events', 'news.gallery'], true)),
            Section::make('Hero')->schema([
                TextInput::make($indexPrefix.'.pageTitle')->label('Page Title')->required()->maxLength(160),
                TextInput::make($indexPrefix.'.heroTitle')->label('Hero Title')->required()->maxLength(160),
                Textarea::make($indexPrefix.'.pageDescription')->label('Description')->required()->rows(2)->columnSpanFull(),
                MediaPicker::image($indexPrefix.'.heroImage', 'Hero Image', true),
                Repeater::make($indexPrefix.'.heroLinks')
                    ->label('Hero Links')
                    ->schema([
                        TextInput::make('id')->required()->maxLength(80),
                        TextInput::make('label')->required()->maxLength(120),
                    ])
                    ->columns(2)
                    ->defaultItems(0)
                    ->reorderable()
                    ->collapsible()
                    ->columnSpanFull(),
            ])->columns(2)->visible(fn (): bool => $this->targetKeyForSchema() === 'news.index'),

            Section::make('Sections')->schema([
                TextInput::make($indexPrefix.'.lastNewsTitle')->label('Last News Title')->required()->maxLength(160),
                TextInput::make($indexPrefix.'.lastNewsViewAllLabel')->label('Last News View All')->required()->maxLength(160),
                TextInput::make($indexPrefix.'.announcementsTitle')->label('Announcements Title')->required()->maxLength(160),
                TextInput::make($indexPrefix.'.announcementsViewAllLabel')->label('Announcements View All')->required()->maxLength(160),
                TextInput::make($indexPrefix.'.eventsTitle')->label('Events Title')->required()->maxLength(160),
                TextInput::make($indexPrefix.'.eventsViewAllLabel')->label('Events View All')->required()->maxLength(160),
                TextInput::make($indexPrefix.'.exploreMoreTitle')->label('Explore More Title')->required()->maxLength(160),
            ])->columns(2)->visible(fn (): bool => $this->targetKeyForSchema() === 'news.index'),

            Section::make('Cards and Labels')->schema([
                TextInput::make($indexPrefix.'.archiveTitle')->label('Archive Card Title')->required()->maxLength(160),
                TextInput::make($indexPrefix.'.archiveCta')->label('Archive Card CTA')->required()->maxLength(120),
                TextInput::make($indexPrefix.'.announcementsCardTitle')->label('Announcements Card Title')->required()->maxLength(160),
                TextInput::make($indexPrefix.'.announcementsCardCta')->label('Announcements Card CTA')->required()->maxLength(120),
                TextInput::make($indexPrefix.'.readMoreLabel')->label('Read More Label')->required()->maxLength(80),
                TextInput::make($indexPrefix.'.viewDetailsLabel')->label('View Details Label')->required()->maxLength(80),
                TextInput::make($indexPrefix.'.newLabel')->label('New Badge')->required()->maxLength(80),
                TextInput::make($indexPrefix.'.newsFallbackCategory')->label('News Fallback Category')->required()->maxLength(120),
                TextInput::make($indexPrefix.'.universityNewsFallbackCategory')->label('University News Fallback Category')->required()->maxLength(120),
                Textarea::make($indexPrefix.'.emptyAnnouncements')->label('Empty Announcements Text')->required()->rows(2)->columnSpanFull(),
            ])->columns(2)->visible(fn (): bool => $this->targetKeyForSchema() === 'news.index'),
        ];
    }

    private function targetKeyForSchema(): string
    {
        return is_string($this->data['target_key'] ?? null) && $this->data['target_key'] !== '' ? $this->data['target_key'] : 'news.index';
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
