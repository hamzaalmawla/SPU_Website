<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Contracts\Cms\CmsTargetRegistryInterface;
use App\Contracts\Cms\CmsWorkflowServiceInterface;
use App\Contracts\Page\AboutPageServiceInterface;
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
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class ManageAbout extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-building-library';

    protected static ?string $slug = 'manage-about';

    protected static ?int $navigationSort = 1;

    protected static string $view = 'filament.pages.manage-about';

    /** @var array<string, mixed> */
    public ?array $data = [];

    public ?int $draftVersion = null;

    private AboutPageServiceInterface $aboutPageService;

    private CmsTargetRegistryInterface $targetRegistry;

    private CmsWorkflowServiceInterface $cmsWorkflowService;

    public function boot(
        AboutPageServiceInterface $aboutPageService,
        CmsTargetRegistryInterface $targetRegistry,
        CmsWorkflowServiceInterface $cmsWorkflowService,
    ): void {
        $this->aboutPageService = $aboutPageService;
        $this->targetRegistry = $targetRegistry;
        $this->cmsWorkflowService = $cmsWorkflowService;
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
        return __('admin.navigation.items.about');
    }

    public function getTitle(): string
    {
        return __('admin.pages.manage_about');
    }

    public function mount(): void
    {
        $this->loadTarget('about.landing');
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make('About Target')->schema([
                    Select::make('target_key')
                        ->label('Page / Subpage')
                        ->options($this->targetOptions())
                        ->required()
                        ->live()
                        ->afterStateUpdated(fn (?string $state): mixed => is_string($state) && $state !== '' ? $this->loadTarget($state) : null),
                ]),
                Tabs::make('about_locales')
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
        $this->assertAboutTarget($targetKey);

        if (! in_array($targetKey, ['about.landing', 'about.history', 'about.leadership', 'about.directorates', 'about.directorates_staff', 'about.partnerships', 'about.quality-policy', 'about.ethical-charter', 'about.organizational-structure'], true)) {
            $this->draftVersion = $this->cmsWorkflowService->latestEditableDraftVersion($targetKey);
            $this->form->fill([
                'target_key' => $targetKey,
                'ar_landing' => [],
                'en_landing' => [],
                'ar_history' => [],
                'en_history' => [],
                'ar_leadership' => [],
                'en_leadership' => [],
                'ar_directorates' => [],
                'en_directorates' => [],
                'ar_directorates_staff' => [],
                'en_directorates_staff' => [],
                'ar_partnerships' => [],
                'en_partnerships' => [],
                'ar_quality_policy' => [],
                'en_quality_policy' => [],
                'ar_ethical_charter' => [],
                'en_ethical_charter' => [],
                'ar_organizational_structure' => [],
                'en_organizational_structure' => [],
            ]);

            return;
        }

        $draftPayload = $this->cmsWorkflowService->latestEditableDraftPayload($targetKey);
        $payload = is_array($draftPayload) ? $draftPayload : $this->aboutPageService->getEditablePayload($targetKey);
        $this->draftVersion = $this->cmsWorkflowService->latestEditableDraftVersion($targetKey);

        $this->form->fill([
            'target_key' => $targetKey,
            'ar_landing' => $targetKey === 'about.landing' && is_array($payload['translations']['ar'] ?? null) ? $this->landingFormData($payload['translations']['ar']) : [],
            'en_landing' => $targetKey === 'about.landing' && is_array($payload['translations']['en'] ?? null) ? $this->landingFormData($payload['translations']['en']) : [],
            'ar_history' => $targetKey === 'about.history' && is_array($payload['translations']['ar'] ?? null) ? $this->historyFormData($payload['translations']['ar']) : [],
            'en_history' => $targetKey === 'about.history' && is_array($payload['translations']['en'] ?? null) ? $this->historyFormData($payload['translations']['en']) : [],
            'ar_leadership' => $targetKey === 'about.leadership' && is_array($payload['translations']['ar'] ?? null) ? $this->contentShellFormData($payload['translations']['ar']) : [],
            'en_leadership' => $targetKey === 'about.leadership' && is_array($payload['translations']['en'] ?? null) ? $this->contentShellFormData($payload['translations']['en']) : [],
            'ar_directorates' => $targetKey === 'about.directorates' && is_array($payload['translations']['ar'] ?? null) ? $this->contentShellFormData($payload['translations']['ar']) : [],
            'en_directorates' => $targetKey === 'about.directorates' && is_array($payload['translations']['en'] ?? null) ? $this->contentShellFormData($payload['translations']['en']) : [],
            'ar_directorates_staff' => $targetKey === 'about.directorates_staff' && is_array($payload['translations']['ar'] ?? null) ? $this->contentShellFormData($payload['translations']['ar']) : [],
            'en_directorates_staff' => $targetKey === 'about.directorates_staff' && is_array($payload['translations']['en'] ?? null) ? $this->contentShellFormData($payload['translations']['en']) : [],
            'ar_partnerships' => $targetKey === 'about.partnerships' && is_array($payload['translations']['ar'] ?? null) ? $this->contentShellFormData($payload['translations']['ar']) : [],
            'en_partnerships' => $targetKey === 'about.partnerships' && is_array($payload['translations']['en'] ?? null) ? $this->contentShellFormData($payload['translations']['en']) : [],
            'ar_quality_policy' => $targetKey === 'about.quality-policy' && is_array($payload['translations']['ar'] ?? null) ? $this->importedContentFormData($payload['translations']['ar']) : [],
            'en_quality_policy' => $targetKey === 'about.quality-policy' && is_array($payload['translations']['en'] ?? null) ? $this->importedContentFormData($payload['translations']['en']) : [],
            'ar_ethical_charter' => $targetKey === 'about.ethical-charter' && is_array($payload['translations']['ar'] ?? null) ? $this->importedContentFormData($payload['translations']['ar']) : [],
            'en_ethical_charter' => $targetKey === 'about.ethical-charter' && is_array($payload['translations']['en'] ?? null) ? $this->importedContentFormData($payload['translations']['en']) : [],
            'ar_organizational_structure' => $targetKey === 'about.organizational-structure' && is_array($payload['translations']['ar'] ?? null) ? $this->importedContentFormData($payload['translations']['ar']) : [],
            'en_organizational_structure' => $targetKey === 'about.organizational-structure' && is_array($payload['translations']['en'] ?? null) ? $this->importedContentFormData($payload['translations']['en']) : [],
        ]);
    }

    public function save(): void
    {
        /** @var User $user */
        $user = auth()->user();

        try {
            $draft = $this->cmsWorkflowService->saveDraft($this->currentTargetKey(), $this->payloadFromForm($this->currentFormData()), (int) $user->id, $this->draftVersion);
            $this->draftVersion = $draft->version;

            Notification::make()->title('About draft saved')->success()->send();
        } catch (ConflictException $e) {
            $this->draftVersion = $e->currentVersion;
            Notification::make()->title('Draft conflict detected')->body('Reload this about target before saving again.')->danger()->persistent()->send();
        } catch (\Throwable $e) {
            report($e);
            Notification::make()->title('Failed to save about draft')->body($e->getMessage())->danger()->send();
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
            Notification::make()->title('Draft conflict detected')->body('Reload this about target before previewing again.')->danger()->persistent()->send();
        } catch (\Throwable $e) {
            report($e);
            Notification::make()->title('Failed to create about preview')->body($e->getMessage())->danger()->send();
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

            Notification::make()->title('About target published')->success()->send();
        } catch (ValidationException $e) {
            Notification::make()->title('Publish failed')->body($this->formatValidationErrors($e->errors()))->danger()->persistent()->send();
        } catch (\Throwable $e) {
            report($e);
            Notification::make()->title('Failed to publish about target')->body($e->getMessage())->danger()->send();
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

            Notification::make()->title('About target scheduled')->success()->send();
        } catch (ValidationException $e) {
            Notification::make()->title('Schedule failed')->body($this->formatValidationErrors($e->errors()))->danger()->persistent()->send();
        } catch (\Throwable $e) {
            report($e);
            Notification::make()->title('Failed to schedule about target')->body($e->getMessage())->danger()->send();
        }
    }

    public function unpublish(): void
    {
        /** @var User $user */
        $user = auth()->user();
        $result = $this->cmsWorkflowService->unpublish($this->currentTargetKey(), (int) $user->id);
        $notification = Notification::make()->title($result ? 'About target unpublished' : 'No published about target found');

        ($result ? $notification->success() : $notification->warning())->send();
    }

    /** @return array<string, string> */
    private function targetOptions(): array
    {
        return $this->targetRegistry->forArea('about')
            ->mapWithKeys(fn (CmsTargetDTO $target): array => [$target->key => __($target->labelKey)])
            ->all();
    }

    private function currentTargetKey(): string
    {
        $targetKey = (string) ($this->data['target_key'] ?? 'about.landing');
        $this->assertAboutTarget($targetKey);

        return $targetKey;
    }

    private function assertAboutTarget(string $targetKey): void
    {
        $target = $this->targetRegistry->find($targetKey);

        if (! $target instanceof CmsTargetDTO || $target->area !== 'about') {
            throw new \InvalidArgumentException('Unsupported about target.');
        }
    }

    /** @param array<string, mixed> $state */
    private function payloadFromForm(array $state): array
    {
        if (($state['target_key'] ?? null) === 'about.landing') {
            return [
                'translations' => [
                    'ar' => $this->landingPayloadFromForm(is_array($state['ar_landing'] ?? null) ? $state['ar_landing'] : []),
                    'en' => $this->landingPayloadFromForm(is_array($state['en_landing'] ?? null) ? $state['en_landing'] : []),
                ],
            ];
        }

        if (($state['target_key'] ?? null) === 'about.history') {
            return [
                'translations' => [
                    'ar' => $this->historyPayloadFromForm(is_array($state['ar_history'] ?? null) ? $state['ar_history'] : []),
                    'en' => $this->historyPayloadFromForm(is_array($state['en_history'] ?? null) ? $state['en_history'] : []),
                ],
            ];
        }

        if (($state['target_key'] ?? null) === 'about.leadership') {
            return [
                'translations' => [
                    'ar' => $this->contentShellPayloadFromForm(is_array($state['ar_leadership'] ?? null) ? $state['ar_leadership'] : []),
                    'en' => $this->contentShellPayloadFromForm(is_array($state['en_leadership'] ?? null) ? $state['en_leadership'] : []),
                ],
            ];
        }

        if (($state['target_key'] ?? null) === 'about.directorates') {
            return [
                'translations' => [
                    'ar' => $this->contentShellPayloadFromForm(is_array($state['ar_directorates'] ?? null) ? $state['ar_directorates'] : []),
                    'en' => $this->contentShellPayloadFromForm(is_array($state['en_directorates'] ?? null) ? $state['en_directorates'] : []),
                ],
            ];
        }

        if (($state['target_key'] ?? null) === 'about.directorates_staff') {
            return [
                'translations' => [
                    'ar' => $this->contentShellPayloadFromForm(is_array($state['ar_directorates_staff'] ?? null) ? $state['ar_directorates_staff'] : []),
                    'en' => $this->contentShellPayloadFromForm(is_array($state['en_directorates_staff'] ?? null) ? $state['en_directorates_staff'] : []),
                ],
            ];
        }

        if (($state['target_key'] ?? null) === 'about.partnerships') {
            return [
                'translations' => [
                    'ar' => $this->contentShellPayloadFromForm(is_array($state['ar_partnerships'] ?? null) ? $state['ar_partnerships'] : []),
                    'en' => $this->contentShellPayloadFromForm(is_array($state['en_partnerships'] ?? null) ? $state['en_partnerships'] : []),
                ],
            ];
        }

        if (in_array($state['target_key'] ?? null, ['about.quality-policy', 'about.ethical-charter', 'about.organizational-structure'], true)) {
            $stateKey = $this->importedContentStateKey((string) $state['target_key']);

            return [
                'translations' => [
                    'ar' => $this->importedContentPayloadFromForm(is_array($state['ar_'.$stateKey] ?? null) ? $state['ar_'.$stateKey] : []),
                    'en' => $this->importedContentPayloadFromForm(is_array($state['en_'.$stateKey] ?? null) ? $state['en_'.$stateKey] : []),
                ],
            ];
        }

        throw new \InvalidArgumentException('This about subpage form will be structured next. Select the About landing page for now.');
    }

    /** @return array<string, mixed> */
    private function currentFormData(): array
    {
        return is_array($this->data) ? $this->data : [];
    }

    /** @return array<int, Section> */
    private function payloadFields(string $locale): array
    {
        if ($this->targetKeyForSchema() === 'about.history') {
            return $this->historyFields($locale);
        }

        if ($this->targetKeyForSchema() === 'about.leadership') {
            return $this->contentShellFields($locale, 'leadership');
        }

        if ($this->targetKeyForSchema() === 'about.directorates') {
            return $this->contentShellFields($locale, 'directorates');
        }

        if ($this->targetKeyForSchema() === 'about.directorates_staff') {
            return $this->contentShellFields($locale, 'directorates_staff');
        }

        if ($this->targetKeyForSchema() === 'about.partnerships') {
            return $this->contentShellFields($locale, 'partnerships');
        }

        if (in_array($this->targetKeyForSchema(), ['about.quality-policy', 'about.ethical-charter', 'about.organizational-structure'], true)) {
            return $this->importedContentFields($locale, $this->importedContentStateKey($this->targetKeyForSchema()));
        }

        if ($this->targetKeyForSchema() !== 'about.landing') {
            return [
                Section::make('Subpage Schema Pending')
                    ->description('We are converting About one page at a time. The landing page is editable now; this subpage will get its own correct structured form next.')
                    ->schema([
                        TextInput::make($locale.'_subpage_pending')->label('Status')->default('Structured form pending for this subpage')->disabled(),
                    ]),
            ];
        }

        $prefix = $locale.'_landing';

        return [
            Section::make('Hero and Story')->schema([
                TextInput::make($prefix.'.title')->label('Title')->required()->maxLength(180),
                TextInput::make($prefix.'.headline')->label('Headline')->required()->maxLength(255),
                Textarea::make($prefix.'.summary')->label('Summary')->required()->rows(2)->columnSpanFull(),
                TextInput::make($prefix.'.badge')->label('Badge')->required()->maxLength(120),
                Textarea::make($prefix.'.quote')->label('Quote')->required()->rows(3)->columnSpanFull(),
                Textarea::make($prefix.'.description')->label('Description')->required()->rows(3)->columnSpanFull(),
                MediaPicker::image($prefix.'.imagePrimary', 'Primary Image', true),
                MediaPicker::image($prefix.'.imageSecondary', 'Secondary Image', true),
            ])->columns(2),

            Section::make('Stats')->schema([
                Repeater::make($prefix.'.stats')
                    ->label('Stats')
                    ->schema([
                        TextInput::make('value')->required()->maxLength(40),
                        TextInput::make('label')->required()->maxLength(120),
                        MediaPicker::icon('icon', 'Icon'),
                    ])
                    ->columns(3)
                    ->defaultItems(0)
                    ->reorderable()
                    ->collapsible()
                    ->columnSpanFull(),
            ]),

            Section::make('Story Items')->schema([
                Repeater::make($prefix.'.storyItems')
                    ->label('Story Items')
                    ->schema([
                        TextInput::make('title')->required()->maxLength(160),
                        Textarea::make('summary')->required()->rows(2)->columnSpanFull(),
                    ])
                    ->columns(2)
                    ->defaultItems(0)
                    ->reorderable()
                    ->collapsible()
                    ->columnSpanFull(),
            ]),

            Section::make('Highlights')->schema([
                Repeater::make($prefix.'.highlights')
                    ->label('Highlights')
                    ->schema([
                        TextInput::make('title')->required()->maxLength(160),
                    ])
                    ->defaultItems(0)
                    ->reorderable()
                    ->collapsible()
                    ->columnSpanFull(),
            ]),

            Section::make('Navigation Cards')->schema([
                Repeater::make($prefix.'.subPages')
                    ->label('Sub Pages')
                    ->schema([
                        TextInput::make('title')->required()->maxLength(160),
                        TextInput::make('link')->required()->maxLength(255),
                    ])
                    ->columns(2)
                    ->defaultItems(0)
                    ->reorderable()
                    ->collapsible()
                    ->columnSpanFull(),
            ]),
        ];
    }

    /** @return array<int, Section> */
    private function contentShellFields(string $locale, string $stateKey): array
    {
        $prefix = $locale.'_'.$stateKey;

        return [
            Section::make('Hero')->schema([
                TextInput::make($prefix.'.title')->label('Page Title')->required()->maxLength(180),
                TextInput::make($prefix.'.headline')->label('Headline')->required()->maxLength(255),
                Textarea::make($prefix.'.summary')->label('Summary')->required()->rows(2)->columnSpanFull(),
                MediaPicker::image($prefix.'.heroImage', 'Hero Image', true),
            ])->columns(2),
        ];
    }

    /** @return array<int, Section> */
    private function importedContentFields(string $locale, string $stateKey): array
    {
        $prefix = $locale.'_'.$stateKey;

        return [
            Section::make('Hero')->schema([
                TextInput::make($prefix.'.title')->label('Page Title')->required()->maxLength(180),
                TextInput::make($prefix.'.headline')->label('Headline')->required()->maxLength(255),
                Textarea::make($prefix.'.summary')->label('Summary')->required()->rows(2)->columnSpanFull(),
                MediaPicker::image($prefix.'.heroImage', 'Hero Image', true),
            ])->columns(2),

            Section::make('Content Cards')->schema([
                Repeater::make($prefix.'.sections')
                    ->label('Cards')
                    ->schema([
                        TextInput::make('title')->required()->maxLength(180),
                        Textarea::make('body')->required()->rows(3)->columnSpanFull(),
                    ])
                    ->columns(2)
                    ->defaultItems(0)
                    ->reorderable()
                    ->collapsible()
                    ->columnSpanFull(),
            ]),
        ];
    }

    /** @return array<int, Section> */
    private function historyFields(string $locale): array
    {
        $prefix = $locale.'_history';

        return [
            Section::make('Hero')->schema([
                TextInput::make($prefix.'.title')->label('Page Title')->required()->maxLength(180),
                TextInput::make($prefix.'.headline')->label('Headline')->required()->maxLength(255),
                Textarea::make($prefix.'.summary')->label('Summary')->required()->rows(2)->columnSpanFull(),
                MediaPicker::image($prefix.'.heroImage', 'Hero Image', true),
            ])->columns(2),

            Section::make('Founding Vision')->schema([
                TextInput::make($prefix.'.foundingTitle')->label('Section Title')->required()->maxLength(180),
                Textarea::make($prefix.'.quote')->label('Quote')->required()->rows(3)->columnSpanFull(),
                Repeater::make($prefix.'.body')
                    ->label('Body Paragraphs')
                    ->schema([
                        Textarea::make('paragraph')->required()->rows(3)->columnSpanFull(),
                    ])
                    ->defaultItems(0)
                    ->reorderable()
                    ->collapsible()
                    ->columnSpanFull(),
            ]),

            Section::make('Institutional Timeline')->schema([
                TextInput::make($prefix.'.timelineTitle')->label('Timeline Title')->required()->maxLength(180),
                Repeater::make($prefix.'.timeline')
                    ->label('Timeline Items')
                    ->schema([
                        TextInput::make('year')->required()->maxLength(40),
                        TextInput::make('title')->required()->maxLength(180),
                        Textarea::make('body')->required()->rows(2)->columnSpanFull(),
                    ])
                    ->columns(2)
                    ->defaultItems(0)
                    ->reorderable()
                    ->collapsible()
                    ->columnSpanFull(),
            ]),

            Section::make('Narratives')->schema([
                Repeater::make($prefix.'.narratives')
                    ->label('Narrative Rows')
                    ->schema([
                        TextInput::make('title')->required()->maxLength(180),
                        TextInput::make('eyebrow')->required()->maxLength(120),
                        Textarea::make('body')->required()->rows(3)->columnSpanFull(),
                    ])
                    ->columns(2)
                    ->defaultItems(0)
                    ->reorderable()
                    ->collapsible()
                    ->columnSpanFull(),
            ]),

            Section::make('Legacy')->schema([
                TextInput::make($prefix.'.legacyTitle')->label('Legacy Title')->required()->maxLength(180),
                Textarea::make($prefix.'.legacyBody')->label('Legacy Body')->required()->rows(3)->columnSpanFull(),
            ]),
        ];
    }

    /** @return array<string, mixed> */
    private function landingFormData(array $payload): array
    {
        return [
            'title' => $this->stringValue($payload, 'title'),
            'headline' => $this->stringValue($payload, 'headline'),
            'summary' => $this->stringValue($payload, 'summary'),
            'quote' => $this->stringValue($payload, 'quote'),
            'description' => $this->stringValue($payload, 'description'),
            'badge' => $this->stringValue($payload, 'badge'),
            'imagePrimary' => $this->stringValue($payload, 'imagePrimary'),
            'imageSecondary' => $this->stringValue($payload, 'imageSecondary'),
            'stats' => $this->listValue($payload, 'stats'),
            'storyItems' => $this->listValue($payload, 'storyItems'),
            'highlights' => $this->listValue($payload, 'highlights'),
            'subPages' => $this->listValue($payload, 'subPages'),
        ];
    }

    /** @return array<string, mixed> */
    private function landingPayloadFromForm(array $data): array
    {
        return [
            'title' => (string) ($data['title'] ?? ''),
            'headline' => (string) ($data['headline'] ?? ''),
            'summary' => (string) ($data['summary'] ?? ''),
            'quote' => (string) ($data['quote'] ?? ''),
            'description' => (string) ($data['description'] ?? ''),
            'badge' => (string) ($data['badge'] ?? ''),
            'imagePrimary' => (string) ($data['imagePrimary'] ?? ''),
            'imageSecondary' => (string) ($data['imageSecondary'] ?? ''),
            'stats' => collect($this->listValue($data, 'stats'))
                ->map(static fn (array $stat): array => [
                    'value' => (string) ($stat['value'] ?? ''),
                    'label' => (string) ($stat['label'] ?? ''),
                    'icon' => (string) ($stat['icon'] ?? ''),
                ])
                ->values()
                ->all(),
            'storyItems' => collect($this->listValue($data, 'storyItems'))
                ->map(static fn (array $item): array => [
                    'title' => (string) ($item['title'] ?? ''),
                    'summary' => (string) ($item['summary'] ?? ''),
                ])
                ->values()
                ->all(),
            'highlights' => collect($this->listValue($data, 'highlights'))
                ->map(static fn (array $item): array => ['title' => (string) ($item['title'] ?? '')])
                ->values()
                ->all(),
            'subPages' => collect($this->listValue($data, 'subPages'))
                ->map(static fn (array $item): array => [
                    'title' => (string) ($item['title'] ?? ''),
                    'link' => (string) ($item['link'] ?? ''),
                ])
                ->values()
                ->all(),
        ];
    }

    /** @return array<string, mixed> */
    private function historyFormData(array $payload): array
    {
        $sections = is_array($payload['sections'] ?? null) ? $payload['sections'] : [];

        return [
            'title' => $this->stringValue($payload, 'title'),
            'headline' => $this->stringValue($payload, 'headline'),
            'summary' => $this->stringValue($payload, 'summary'),
            'heroImage' => $this->stringValue($payload, 'heroImage'),
            'foundingTitle' => $this->stringValue($sections, 'foundingTitle'),
            'quote' => $this->stringValue($sections, 'quote'),
            'body' => collect(is_array($sections['body'] ?? null) ? $sections['body'] : [])
                ->map(static fn (mixed $paragraph): array => ['paragraph' => is_string($paragraph) || is_numeric($paragraph) ? (string) $paragraph : ''])
                ->filter(static fn (array $item): bool => $item['paragraph'] !== '')
                ->values()
                ->all(),
            'timelineTitle' => $this->stringValue($sections, 'timelineTitle'),
            'timeline' => $this->listValue($sections, 'timeline'),
            'narratives' => $this->listValue($sections, 'narratives'),
            'legacyTitle' => $this->stringValue($sections, 'legacyTitle'),
            'legacyBody' => $this->stringValue($sections, 'legacyBody'),
        ];
    }

    /** @return array<string, mixed> */
    private function historyPayloadFromForm(array $data): array
    {
        return [
            'title' => (string) ($data['title'] ?? ''),
            'headline' => (string) ($data['headline'] ?? ''),
            'summary' => (string) ($data['summary'] ?? ''),
            'heroImage' => (string) ($data['heroImage'] ?? ''),
            'sections' => [
                'foundingTitle' => (string) ($data['foundingTitle'] ?? ''),
                'quote' => (string) ($data['quote'] ?? ''),
                'body' => collect($this->listValue($data, 'body'))
                    ->map(static fn (array $item): string => (string) ($item['paragraph'] ?? ''))
                    ->filter(static fn (string $paragraph): bool => $paragraph !== '')
                    ->values()
                    ->all(),
                'timelineTitle' => (string) ($data['timelineTitle'] ?? ''),
                'timeline' => collect($this->listValue($data, 'timeline'))
                    ->map(static fn (array $item): array => [
                        'year' => (string) ($item['year'] ?? ''),
                        'title' => (string) ($item['title'] ?? ''),
                        'body' => (string) ($item['body'] ?? ''),
                    ])
                    ->values()
                    ->all(),
                'narratives' => collect($this->listValue($data, 'narratives'))
                    ->map(static fn (array $item): array => [
                        'title' => (string) ($item['title'] ?? ''),
                        'eyebrow' => (string) ($item['eyebrow'] ?? ''),
                        'body' => (string) ($item['body'] ?? ''),
                    ])
                    ->values()
                    ->all(),
                'legacyTitle' => (string) ($data['legacyTitle'] ?? ''),
                'legacyBody' => (string) ($data['legacyBody'] ?? ''),
            ],
        ];
    }

    /** @return array<string, mixed> */
    private function contentShellFormData(array $payload): array
    {
        return [
            'title' => $this->stringValue($payload, 'title'),
            'headline' => $this->stringValue($payload, 'headline'),
            'summary' => $this->stringValue($payload, 'summary'),
            'heroImage' => $this->stringValue($payload, 'heroImage'),
        ];
    }

    /** @return array<string, mixed> */
    private function contentShellPayloadFromForm(array $data): array
    {
        return [
            'title' => (string) ($data['title'] ?? ''),
            'headline' => (string) ($data['headline'] ?? ''),
            'summary' => (string) ($data['summary'] ?? ''),
            'heroImage' => (string) ($data['heroImage'] ?? ''),
            'sections' => [],
        ];
    }

    /** @return array<string, mixed> */
    private function importedContentFormData(array $payload): array
    {
        return [
            ...$this->contentShellFormData($payload),
            'sections' => $this->listValue($payload, 'sections'),
        ];
    }

    /** @return array<string, mixed> */
    private function importedContentPayloadFromForm(array $data): array
    {
        return [
            'title' => (string) ($data['title'] ?? ''),
            'headline' => (string) ($data['headline'] ?? ''),
            'summary' => (string) ($data['summary'] ?? ''),
            'heroImage' => (string) ($data['heroImage'] ?? ''),
            'sections' => collect($this->listValue($data, 'sections'))
                ->map(static fn (array $section): array => [
                    'title' => (string) ($section['title'] ?? ''),
                    'body' => (string) ($section['body'] ?? ''),
                ])
                ->values()
                ->all(),
        ];
    }

    private function importedContentStateKey(string $targetKey): string
    {
        return match ($targetKey) {
            'about.quality-policy' => 'quality_policy',
            'about.ethical-charter' => 'ethical_charter',
            'about.organizational-structure' => 'organizational_structure',
            default => throw new \InvalidArgumentException('Unsupported about content target.'),
        };
    }

    private function targetKeyForSchema(): string
    {
        return is_string($this->data['target_key'] ?? null) && $this->data['target_key'] !== '' ? $this->data['target_key'] : 'about.landing';
    }

    /** @return array<int, array<string, mixed>> */
    private function listValue(array $data, string $key): array
    {
        return array_values(array_filter(is_array($data[$key] ?? null) ? $data[$key] : [], static fn (mixed $item): bool => is_array($item)));
    }

    /** @param array<string, mixed> $payload */
    private function stringValue(array $payload, string $key): string
    {
        $value = $payload[$key] ?? '';

        return is_string($value) || is_numeric($value) ? (string) $value : '';
    }

    /** @param array<string, array<int, string>> $errors */
    private function formatValidationErrors(array $errors): string
    {
        return collect($errors)->flatten()->implode(PHP_EOL);
    }
}
