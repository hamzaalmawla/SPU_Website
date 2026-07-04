<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Contracts\Cms\CmsTargetRegistryInterface;
use App\Contracts\Cms\CmsWorkflowServiceInterface;
use App\Contracts\Page\CampusLifePageServiceInterface;
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
use Filament\Forms\Components\Toggle;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class ManageCampusLife extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-sparkles';

    protected static ?string $slug = 'manage-campus-life';

    protected static ?int $navigationSort = 1;

    protected static string $view = 'filament.pages.manage-campus-life';

    /** @var array<string, mixed> */
    public ?array $data = [];

    public ?int $draftVersion = null;

    private CampusLifePageServiceInterface $campusLifePageService;

    private CmsTargetRegistryInterface $targetRegistry;

    private CmsWorkflowServiceInterface $cmsWorkflowService;

    public function boot(
        CampusLifePageServiceInterface $campusLifePageService,
        CmsTargetRegistryInterface $targetRegistry,
        CmsWorkflowServiceInterface $cmsWorkflowService,
    ): void {
        $this->campusLifePageService = $campusLifePageService;
        $this->targetRegistry = $targetRegistry;
        $this->cmsWorkflowService = $cmsWorkflowService;
    }

    public static function canAccess(): bool
    {
        return Gate::allows('manage-faculties');
    }

    public static function getNavigationGroup(): ?string
    {
        return __('admin.navigation.groups.campus_life');
    }

    public static function getNavigationLabel(): string
    {
        return __('admin.navigation.items.campus_life');
    }

    public function getTitle(): string
    {
        return __('admin.pages.manage_campus_life');
    }

    public function mount(): void
    {
        $this->loadTarget('campus_life.landing');
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make('Campus Life Target')->schema([
                    Select::make('target_key')
                        ->label('Page / Subpage')
                        ->options($this->targetOptions())
                        ->required()
                        ->live()
                        ->afterStateUpdated(fn (?string $state): mixed => is_string($state) && $state !== '' ? $this->loadTarget($state) : null),
                ]),
                Tabs::make('campus_life_locales')
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
                ->action(fn (array $data): mixed => $this->schedule((string) $data['publish_at'])),
            Action::make('unpublish')->label('Unpublish')->icon('heroicon-o-x-circle')->color('danger')->requiresConfirmation()->action(function (): void {
                $this->unpublish();
            }),
        ];
    }

    public function loadTarget(string $targetKey): void
    {
        $this->assertCampusLifeTarget($targetKey);

        if (! in_array($targetKey, $this->curatedTargetKeys(), true)) {
            $this->draftVersion = $this->cmsWorkflowService->latestEditableDraftVersion($targetKey);
            $this->form->fill([
                'target_key' => $targetKey,
                'ar_landing' => [],
                'en_landing' => [],
                'ar_services' => [],
                'en_services' => [],
                'ar_transport' => [],
                'en_transport' => [],
                'ar_clubs_activities' => [],
                'en_clubs_activities' => [],
                'ar_career_development' => [],
                'en_career_development' => [],
                'ar_dental' => [],
                'en_dental' => [],
                'ar_hospital' => [],
                'en_hospital' => [],
                'ar_health_insurance' => [],
                'en_health_insurance' => [],
            ]);

            return;
        }

        $draftPayload = $this->cmsWorkflowService->latestEditableDraftPayload($targetKey);
        $payload = is_array($draftPayload) ? $draftPayload : $this->campusLifePageService->getEditablePayload($targetKey);
        $this->draftVersion = $this->cmsWorkflowService->latestEditableDraftVersion($targetKey);

        $this->form->fill([
            'target_key' => $targetKey,
            'ar_landing' => $targetKey === 'campus_life.landing' && is_array($payload['translations']['ar'] ?? null) ? $payload['translations']['ar'] : [],
            'en_landing' => $targetKey === 'campus_life.landing' && is_array($payload['translations']['en'] ?? null) ? $payload['translations']['en'] : [],
            'ar_services' => $targetKey === 'campus_life.services' && is_array($payload['translations']['ar'] ?? null) ? $payload['translations']['ar'] : [],
            'en_services' => $targetKey === 'campus_life.services' && is_array($payload['translations']['en'] ?? null) ? $payload['translations']['en'] : [],
            'ar_transport' => $targetKey === 'campus_life.transport' && is_array($payload['translations']['ar'] ?? null) ? $payload['translations']['ar'] : [],
            'en_transport' => $targetKey === 'campus_life.transport' && is_array($payload['translations']['en'] ?? null) ? $payload['translations']['en'] : [],
            'ar_clubs_activities' => $targetKey === 'campus_life.clubs-activities' && is_array($payload['translations']['ar'] ?? null) ? $payload['translations']['ar'] : [],
            'en_clubs_activities' => $targetKey === 'campus_life.clubs-activities' && is_array($payload['translations']['en'] ?? null) ? $payload['translations']['en'] : [],
            'ar_career_development' => $targetKey === 'campus_life.career-development' && is_array($payload['translations']['ar'] ?? null) ? $payload['translations']['ar'] : [],
            'en_career_development' => $targetKey === 'campus_life.career-development' && is_array($payload['translations']['en'] ?? null) ? $payload['translations']['en'] : [],
            'ar_dental' => $targetKey === 'campus_life.dental' && is_array($payload['translations']['ar'] ?? null) ? $payload['translations']['ar'] : [],
            'en_dental' => $targetKey === 'campus_life.dental' && is_array($payload['translations']['en'] ?? null) ? $payload['translations']['en'] : [],
            'ar_hospital' => $targetKey === 'campus_life.hospital' && is_array($payload['translations']['ar'] ?? null) ? $payload['translations']['ar'] : [],
            'en_hospital' => $targetKey === 'campus_life.hospital' && is_array($payload['translations']['en'] ?? null) ? $payload['translations']['en'] : [],
            'ar_health_insurance' => $targetKey === 'campus_life.health-insurance' && is_array($payload['translations']['ar'] ?? null) ? $payload['translations']['ar'] : [],
            'en_health_insurance' => $targetKey === 'campus_life.health-insurance' && is_array($payload['translations']['en'] ?? null) ? $payload['translations']['en'] : [],
        ]);
    }

    public function save(): void
    {
        /** @var User $user */
        $user = auth()->user();

        try {
            $draft = $this->cmsWorkflowService->saveDraft($this->currentTargetKey(), $this->payloadFromForm($this->currentFormData()), (int) $user->id, $this->draftVersion);
            $this->draftVersion = $draft->version;

            Notification::make()->title('Campus Life draft saved')->success()->send();
        } catch (ConflictException $e) {
            $this->draftVersion = $e->currentVersion;
            Notification::make()->title('Draft conflict detected')->body('Reload this campus life target before saving again.')->danger()->persistent()->send();
        } catch (\Throwable $e) {
            report($e);
            Notification::make()->title('Failed to save campus life draft')->body($e->getMessage())->danger()->send();
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
            Notification::make()->title('Draft conflict detected')->body('Reload this campus life target before previewing again.')->danger()->persistent()->send();
        } catch (\Throwable $e) {
            report($e);
            Notification::make()->title('Failed to create campus life preview')->body($e->getMessage())->danger()->send();
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

            Notification::make()->title('Campus Life target published')->success()->send();
        } catch (ValidationException $e) {
            Notification::make()->title('Publish failed')->body($this->formatValidationErrors($e->errors()))->danger()->persistent()->send();
        } catch (\Throwable $e) {
            report($e);
            Notification::make()->title('Failed to publish campus life target')->body($e->getMessage())->danger()->send();
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

            Notification::make()->title('Campus Life target scheduled')->success()->send();
        } catch (ValidationException $e) {
            Notification::make()->title('Schedule failed')->body($this->formatValidationErrors($e->errors()))->danger()->persistent()->send();
        } catch (\Throwable $e) {
            report($e);
            Notification::make()->title('Failed to schedule campus life target')->body($e->getMessage())->danger()->send();
        }
    }

    public function unpublish(): void
    {
        /** @var User $user */
        $user = auth()->user();
        $result = $this->cmsWorkflowService->unpublish($this->currentTargetKey(), (int) $user->id);
        $notification = Notification::make()->title($result ? 'Campus Life target unpublished' : 'No published campus life target found');

        ($result ? $notification->success() : $notification->warning())->send();
    }

    /** @return array<string, string> */
    private function targetOptions(): array
    {
        return $this->targetRegistry->forArea('campus_life')
            ->reject(fn (CmsTargetDTO $target): bool => $target->key === 'campus_life.virtual_tour')
            ->mapWithKeys(fn (CmsTargetDTO $target): array => [$target->key => __($target->labelKey)])
            ->all();
    }

    private function currentTargetKey(): string
    {
        $targetKey = (string) ($this->data['target_key'] ?? 'campus_life.landing');
        $this->assertCampusLifeTarget($targetKey);

        return $targetKey;
    }

    private function assertCampusLifeTarget(string $targetKey): void
    {
        $target = $this->targetRegistry->find($targetKey);

        if (! $target instanceof CmsTargetDTO || $target->area !== 'campus_life' || $target->key === 'campus_life.virtual_tour') {
            throw new \InvalidArgumentException('Unsupported campus life target.');
        }
    }

    /** @return array<int, string> */
    private function curatedTargetKeys(): array
    {
        return [
            'campus_life.landing',
            'campus_life.services',
            'campus_life.transport',
            'campus_life.clubs-activities',
            'campus_life.career-development',
            'campus_life.dental',
            'campus_life.hospital',
            'campus_life.health-insurance',
        ];
    }

    /** @param array<string, mixed> $state */
    private function payloadFromForm(array $state): array
    {
        if (($state['target_key'] ?? null) === 'campus_life.landing') {
            return [
                'translations' => [
                    'ar' => $this->normalizeLandingPayload(is_array($state['ar_landing'] ?? null) ? $state['ar_landing'] : []),
                    'en' => $this->normalizeLandingPayload(is_array($state['en_landing'] ?? null) ? $state['en_landing'] : []),
                ],
            ];
        }

        if (($state['target_key'] ?? null) === 'campus_life.services') {
            return [
                'translations' => [
                    'ar' => $this->normalizeServicesPayload(is_array($state['ar_services'] ?? null) ? $state['ar_services'] : []),
                    'en' => $this->normalizeServicesPayload(is_array($state['en_services'] ?? null) ? $state['en_services'] : []),
                ],
            ];
        }

        if (($state['target_key'] ?? null) === 'campus_life.transport') {
            return [
                'translations' => [
                    'ar' => $this->normalizeTransportPayload(is_array($state['ar_transport'] ?? null) ? $state['ar_transport'] : []),
                    'en' => $this->normalizeTransportPayload(is_array($state['en_transport'] ?? null) ? $state['en_transport'] : []),
                ],
            ];
        }

        if (($state['target_key'] ?? null) === 'campus_life.clubs-activities') {
            return [
                'translations' => [
                    'ar' => $this->normalizeClubsActivitiesPayload(is_array($state['ar_clubs_activities'] ?? null) ? $state['ar_clubs_activities'] : []),
                    'en' => $this->normalizeClubsActivitiesPayload(is_array($state['en_clubs_activities'] ?? null) ? $state['en_clubs_activities'] : []),
                ],
            ];
        }

        if (($state['target_key'] ?? null) === 'campus_life.career-development') {
            return [
                'translations' => [
                    'ar' => $this->normalizeCareerDevelopmentPayload(is_array($state['ar_career_development'] ?? null) ? $state['ar_career_development'] : []),
                    'en' => $this->normalizeCareerDevelopmentPayload(is_array($state['en_career_development'] ?? null) ? $state['en_career_development'] : []),
                ],
            ];
        }

        if (($state['target_key'] ?? null) === 'campus_life.dental') {
            return [
                'translations' => [
                    'ar' => $this->normalizeClinicalPayload('dental', is_array($state['ar_dental'] ?? null) ? $state['ar_dental'] : []),
                    'en' => $this->normalizeClinicalPayload('dental', is_array($state['en_dental'] ?? null) ? $state['en_dental'] : []),
                ],
            ];
        }

        if (($state['target_key'] ?? null) === 'campus_life.hospital') {
            return [
                'translations' => [
                    'ar' => $this->normalizeClinicalPayload('hospital', is_array($state['ar_hospital'] ?? null) ? $state['ar_hospital'] : []),
                    'en' => $this->normalizeClinicalPayload('hospital', is_array($state['en_hospital'] ?? null) ? $state['en_hospital'] : []),
                ],
            ];
        }

        if (($state['target_key'] ?? null) === 'campus_life.health-insurance') {
            return [
                'translations' => [
                    'ar' => $this->normalizeHealthInsurancePayload(is_array($state['ar_health_insurance'] ?? null) ? $state['ar_health_insurance'] : []),
                    'en' => $this->normalizeHealthInsurancePayload(is_array($state['en_health_insurance'] ?? null) ? $state['en_health_insurance'] : []),
                ],
            ];
        }

        throw new \InvalidArgumentException('This campus life target form will be structured next. Select an editable Campus Life target for now.');
    }

    /** @return array<string, mixed> */
    private function currentFormData(): array
    {
        return is_array($this->data) ? $this->data : [];
    }

    /** @return array<int, Section> */
    private function payloadFields(string $locale): array
    {
        if ($this->targetKeyForSchema() === 'campus_life.services') {
            return $this->servicesFields($locale);
        }

        if ($this->targetKeyForSchema() === 'campus_life.transport') {
            return $this->transportFields($locale);
        }

        if ($this->targetKeyForSchema() === 'campus_life.clubs-activities') {
            return $this->clubsActivitiesFields($locale);
        }

        if ($this->targetKeyForSchema() === 'campus_life.career-development') {
            return $this->careerDevelopmentFields($locale);
        }

        if ($this->targetKeyForSchema() === 'campus_life.dental') {
            return $this->clinicalFields($locale, 'dental');
        }

        if ($this->targetKeyForSchema() === 'campus_life.hospital') {
            return $this->clinicalFields($locale, 'hospital');
        }

        if ($this->targetKeyForSchema() === 'campus_life.health-insurance') {
            return $this->healthInsuranceFields($locale);
        }

        if ($this->targetKeyForSchema() !== 'campus_life.landing') {
            return [
                Section::make('Target Schema Pending')
                    ->description('All Campus Life route subpages are editable now. Virtual Tour is intentionally skipped until the end.')
                    ->schema([
                        TextInput::make($locale.'_target_pending')->label('Status')->default('Structured form pending for this campus life target')->disabled(),
                    ]),
            ];
        }

        $prefix = $locale.'_landing';

        return [
            Section::make('Hero')->schema([
                TextInput::make($prefix.'.hero.title')->label('Title')->required()->maxLength(160),
                MediaPicker::image($prefix.'.hero.image', 'Hero Image', true),
                Textarea::make($prefix.'.hero.summary')->label('Summary')->required()->rows(3)->columnSpanFull(),
                Repeater::make($prefix.'.hero.quickLinks')
                    ->label('Quick Links')
                    ->schema([
                        TextInput::make('label')->required()->maxLength(120),
                        TextInput::make('href')->required()->maxLength(255),
                    ])
                    ->columns(2)
                    ->defaultItems(0)
                    ->reorderable()
                    ->collapsible()
                    ->columnSpanFull(),
            ])->columns(2),

            Section::make('Intro')->schema([
                TextInput::make($prefix.'.intro.title')->label('Title')->required()->maxLength(160),
                Textarea::make($prefix.'.intro.summary')->label('Summary')->required()->rows(2)->columnSpanFull(),
            ])->columns(2),

            Section::make('Stats')->schema([
                Repeater::make($prefix.'.stats')
                    ->label('Stats')
                    ->schema([
                        TextInput::make('id')->required()->maxLength(80),
                        TextInput::make('value')->required()->numeric(),
                        TextInput::make('suffix')->maxLength(20),
                        TextInput::make('label')->required()->maxLength(120),
                        MediaPicker::icon('icon', 'Icon', true)->columnSpanFull(),
                    ])
                    ->columns(4)
                    ->defaultItems(0)
                    ->reorderable()
                    ->collapsible()
                    ->columnSpanFull(),
            ]),

            Section::make('Feature Cards')->schema([
                TextInput::make($prefix.'.features.eyebrow')->label('Eyebrow')->required()->maxLength(120),
                TextInput::make($prefix.'.features.title')->label('Title')->required()->maxLength(160),
                Textarea::make($prefix.'.features.summary')->label('Summary')->required()->rows(2)->columnSpanFull(),
                Repeater::make($prefix.'.features.items')
                    ->label('Cards')
                    ->schema([
                        MediaPicker::icon('icon', 'Icon', true),
                        TextInput::make('title')->required()->maxLength(160),
                        Textarea::make('summary')->required()->rows(2)->columnSpanFull(),
                    ])
                    ->columns(2)
                    ->defaultItems(0)
                    ->reorderable()
                    ->collapsible()
                    ->columnSpanFull(),
            ])->columns(2),

            Section::make('Services')->schema([
                TextInput::make($prefix.'.servicesHeading.eyebrow')->label('Eyebrow')->required()->maxLength(120),
                TextInput::make($prefix.'.servicesHeading.title')->label('Title')->required()->maxLength(160),
                Repeater::make($prefix.'.services')
                    ->label('Service Rows')
                    ->schema([
                        TextInput::make('number')->required()->maxLength(20),
                        TextInput::make('title')->required()->maxLength(160),
                        TextInput::make('href')->required()->maxLength(255),
                        TextInput::make('link')->required()->maxLength(120),
                        MediaPicker::image('image', 'Image', true),
                        Select::make('imagePosition')->options(['left' => 'Left', 'right' => 'Right'])->required(),
                        Textarea::make('summary')->required()->rows(2)->columnSpanFull(),
                    ])
                    ->columns(3)
                    ->defaultItems(0)
                    ->reorderable()
                    ->collapsible()
                    ->columnSpanFull(),
            ])->columns(2),

            Section::make('Gallery')->schema([
                TextInput::make($prefix.'.gallery.eyebrow')->label('Eyebrow')->required()->maxLength(120),
                TextInput::make($prefix.'.gallery.title')->label('Title')->required()->maxLength(160),
                Textarea::make($prefix.'.gallery.summary')->label('Summary')->required()->rows(2)->columnSpanFull(),
                Repeater::make($prefix.'.gallery.images')
                    ->label('Images')
                    ->schema([
                        MediaPicker::image('src', 'Image', true),
                        TextInput::make('alt')->required()->maxLength(160),
                    ])
                    ->columns(2)
                    ->defaultItems(0)
                    ->reorderable()
                    ->collapsible()
                    ->columnSpanFull(),
            ])->columns(2),

            Section::make('Digital Portals')->schema([
                TextInput::make($prefix.'.portalsHeading.eyebrow')->label('Eyebrow')->required()->maxLength(120),
                TextInput::make($prefix.'.portalsHeading.title')->label('Title')->required()->maxLength(160),
                Repeater::make($prefix.'.portals')
                    ->label('Portals')
                    ->schema([
                        TextInput::make('title')->required()->maxLength(160),
                        MediaPicker::icon('icon', 'Icon', true),
                        TextInput::make('url')->required()->maxLength(255),
                        Textarea::make('summary')->required()->rows(2)->columnSpanFull(),
                    ])
                    ->columns(3)
                    ->defaultItems(0)
                    ->reorderable()
                    ->collapsible()
                    ->columnSpanFull(),
            ])->columns(2),

            Section::make('Call to Action')->schema([
                TextInput::make($prefix.'.cta.title')->label('Title')->required()->maxLength(160),
                Textarea::make($prefix.'.cta.summary')->label('Summary')->required()->rows(2)->columnSpanFull(),
                TextInput::make($prefix.'.cta.primaryLabel')->label('Primary Label')->required()->maxLength(120),
                TextInput::make($prefix.'.cta.primaryUrl')->label('Primary URL')->required()->maxLength(255),
                TextInput::make($prefix.'.cta.secondaryLabel')->label('Secondary Label')->required()->maxLength(120),
                TextInput::make($prefix.'.cta.secondaryUrl')->label('Secondary URL')->required()->maxLength(255),
            ])->columns(2),
        ];
    }

    /** @return array<int, Section> */
    private function clinicalFields(string $locale, string $kind): array
    {
        $prefix = $locale.'_'.$kind;
        $contentList = $kind === 'dental' ? 'services' : 'departments';
        $contentLabel = $kind === 'dental' ? 'Dental Services' : 'Medical Departments';

        $sections = [
            Section::make('Hero')->schema([
                TextInput::make($prefix.'.hero.title')->label('Title')->required()->maxLength(160),
                MediaPicker::image($prefix.'.hero.image', 'Hero Image', true),
                Textarea::make($prefix.'.hero.summary')->label('Summary')->required()->rows(3)->columnSpanFull(),
                TextInput::make($prefix.'.hero.cta')->label('CTA Label')->required()->maxLength(120),
                TextInput::make($prefix.'.hero.ctaUrl')->label('CTA URL')->required()->maxLength(255),
                Repeater::make($prefix.'.hero.breadcrumbs')
                    ->label('Breadcrumbs')
                    ->schema([
                        TextInput::make('label')->required()->maxLength(120),
                        TextInput::make('href')->required()->maxLength(255),
                    ])
                    ->columns(2)
                    ->defaultItems(0)
                    ->reorderable()
                    ->collapsible()
                    ->columnSpanFull(),
            ])->columns(2),

            Section::make($contentLabel)->schema([
                TextInput::make($prefix.'.sectionHeader.title')->label('Section Title')->required()->maxLength(160),
                Repeater::make($prefix.'.'.$contentList)
                    ->label($contentLabel)
                    ->schema([
                        TextInput::make('title')->required()->maxLength(160),
                        MediaPicker::icon('icon', 'Icon', true),
                        Textarea::make('description')->required()->rows(2)->columnSpanFull(),
                    ])
                    ->columns(2)
                    ->defaultItems(0)
                    ->reorderable()
                    ->collapsible()
                    ->columnSpanFull(),
            ])->columns(2),

            Section::make('Weekly Schedule')->schema([
                TextInput::make($prefix.'.scheduleSection.title')->label('Schedule Title')->required()->maxLength(160),
                TextInput::make($prefix.'.scheduleSection.status')->label('Open Status')->required()->maxLength(80),
                TextInput::make($prefix.'.scheduleSection.statusClosed')->label('Closed Status')->required()->maxLength(80),
                Textarea::make($prefix.'.scheduleDetails')->label('Schedule Details')->required()->rows(2)->columnSpanFull(),
                Repeater::make($prefix.'.schedule')
                    ->label('Schedule Rows')
                    ->schema([
                        TextInput::make('day')->required()->maxLength(80),
                        TextInput::make('time')->required()->maxLength(120),
                        Toggle::make('isEmergency')->label('Emergency Only'),
                    ])
                    ->columns(3)
                    ->defaultItems(0)
                    ->reorderable()
                    ->collapsible()
                    ->columnSpanFull(),
            ])->columns(3),
        ];

        if ($kind === 'hospital') {
            $sections[] = Section::make('Emergency Support')->schema([
                TextInput::make($prefix.'.emergency.label')->label('Label')->required()->maxLength(120),
                TextInput::make($prefix.'.emergency.status')->label('Status')->required()->maxLength(120),
                TextInput::make($prefix.'.emergency.hotlineLabel')->label('Hotline Label')->required()->maxLength(120),
                TextInput::make($prefix.'.emergency.phone')->label('Phone')->required()->maxLength(80),
                TextInput::make($prefix.'.emergency.callCta')->label('Call CTA')->required()->maxLength(120),
                TextInput::make($prefix.'.emergency.directionsCta')->label('Directions CTA')->required()->maxLength(120),
                MediaPicker::icon($prefix.'.emergency.icon', 'Icon', true),
            ])->columns(2);
        }

        $sections[] = Section::make('SEO')->schema([
            Textarea::make($prefix.'.seoDescription')->label('SEO Description')->required()->rows(2)->columnSpanFull(),
        ]);

        return $sections;
    }

    /** @return array<int, Section> */
    private function healthInsuranceFields(string $locale): array
    {
        $prefix = $locale.'_health_insurance';

        return [
            Section::make('Hero')->schema([
                TextInput::make($prefix.'.hero.title')->label('Title')->required()->maxLength(160),
                MediaPicker::image($prefix.'.hero.image', 'Hero Image', true),
                Textarea::make($prefix.'.hero.summary')->label('Summary')->required()->rows(2)->columnSpanFull(),
                Repeater::make($prefix.'.hero.breadcrumbs')
                    ->label('Breadcrumbs')
                    ->schema([
                        TextInput::make('label')->required()->maxLength(120),
                        TextInput::make('href')->required()->maxLength(255),
                    ])
                    ->columns(2)
                    ->defaultItems(0)
                    ->reorderable()
                    ->collapsible()
                    ->columnSpanFull(),
            ])->columns(2),

            Section::make('Health Insurance Sections')->schema([
                Repeater::make($prefix.'.sections')
                    ->label('Sections')
                    ->schema([
                        TextInput::make('id')->required()->maxLength(80),
                        Select::make('type')->options([
                            'highlight' => 'Highlight',
                            'steps' => 'Steps',
                            'cards' => 'Cards',
                            'documents' => 'Documents',
                        ])->required(),
                        TextInput::make('title')->required()->maxLength(160),
                        Textarea::make('description')->label('Highlight Description')->rows(2)->columnSpanFull(),
                        Repeater::make('items')
                            ->label('Steps / Cards')
                            ->schema([
                                TextInput::make('number')->maxLength(20),
                                TextInput::make('title')->required()->maxLength(160),
                                MediaPicker::icon('icon', 'Icon'),
                                Textarea::make('desc')->required()->rows(2)->columnSpanFull(),
                            ])
                            ->columns(3)
                            ->defaultItems(0)
                            ->reorderable()
                            ->collapsible()
                            ->columnSpanFull(),
                        Repeater::make('list')
                            ->label('Document List')
                            ->schema([
                                TextInput::make('item')->required()->maxLength(255),
                            ])
                            ->defaultItems(0)
                            ->reorderable()
                            ->collapsible()
                            ->columnSpanFull(),
                        TextInput::make('support.title')->label('Support Title')->maxLength(160),
                        TextInput::make('support.location')->label('Support Location')->maxLength(255),
                        MediaPicker::icon('support.locationIcon', 'Location Icon'),
                        TextInput::make('support.phone')->label('Support Phone')->maxLength(80),
                        MediaPicker::icon('support.phoneIcon', 'Phone Icon'),
                        TextInput::make('support.email')->label('Support Email')->maxLength(160),
                        MediaPicker::icon('support.emailIcon', 'Email Icon'),
                    ])
                    ->columns(2)
                    ->defaultItems(0)
                    ->reorderable()
                    ->collapsible()
                    ->columnSpanFull(),
            ]),

            Section::make('SEO')->schema([
                Textarea::make($prefix.'.seoDescription')->label('SEO Description')->required()->rows(2)->columnSpanFull(),
            ]),
        ];
    }

    /** @return array<int, Section> */
    private function careerDevelopmentFields(string $locale): array
    {
        $prefix = $locale.'_career_development';

        return [
            Section::make('Hero')->schema([
                TextInput::make($prefix.'.hero.title')->label('Title')->required()->maxLength(160),
                MediaPicker::image($prefix.'.hero.image', 'Hero Image', true),
                TextInput::make($prefix.'.hero.panel.title')->label('Panel Title')->required()->maxLength(160),
                Textarea::make($prefix.'.hero.panel.summary')->label('Panel Summary')->required()->rows(2)->columnSpanFull(),
                Repeater::make($prefix.'.hero.breadcrumbs')
                    ->label('Breadcrumbs')
                    ->schema([
                        TextInput::make('label')->required()->maxLength(120),
                        TextInput::make('href')->required()->maxLength(255),
                    ])
                    ->columns(2)
                    ->defaultItems(0)
                    ->reorderable()
                    ->collapsible()
                    ->columnSpanFull(),
            ])->columns(2),

            Section::make('Career Services')->schema([
                TextInput::make($prefix.'.services.title')->label('Section Title')->required()->maxLength(160),
                Repeater::make($prefix.'.services.items')
                    ->label('Service Cards')
                    ->schema([
                        TextInput::make('id')->required()->maxLength(80),
                        MediaPicker::icon('icon', 'Icon', true),
                        TextInput::make('title')->required()->maxLength(160),
                        TextInput::make('link')->required()->maxLength(120),
                        TextInput::make('href')->required()->maxLength(255),
                        Textarea::make('summary')->required()->rows(2)->columnSpanFull(),
                    ])
                    ->columns(3)
                    ->defaultItems(0)
                    ->reorderable()
                    ->collapsible()
                    ->columnSpanFull(),
            ])->columns(2),

            Section::make('Success Panel')->schema([
                TextInput::make($prefix.'.success.title')->label('Title')->required()->maxLength(160),
                MediaPicker::image($prefix.'.success.image', 'Image', true),
                TextInput::make($prefix.'.success.imageAlt')->label('Image Alt')->required()->maxLength(160),
                Textarea::make($prefix.'.success.summary')->label('Summary')->required()->rows(3)->columnSpanFull(),
                Repeater::make($prefix.'.success.badges')
                    ->label('Badges')
                    ->schema([
                        TextInput::make('label')->required()->maxLength(120),
                        MediaPicker::icon('icon', 'Icon', true),
                    ])
                    ->columns(2)
                    ->defaultItems(0)
                    ->reorderable()
                    ->collapsible()
                    ->columnSpanFull(),
            ])->columns(2),

            Section::make('SEO')->schema([
                Textarea::make($prefix.'.seoDescription')->label('SEO Description')->required()->rows(2)->columnSpanFull(),
            ]),
        ];
    }

    /** @return array<int, Section> */
    private function clubsActivitiesFields(string $locale): array
    {
        $prefix = $locale.'_clubs_activities';

        return [
            Section::make('Hero')->schema([
                TextInput::make($prefix.'.hero.title')->label('Title')->required()->maxLength(160),
                MediaPicker::image($prefix.'.hero.image', 'Hero Image', true),
                Repeater::make($prefix.'.hero.breadcrumbs')
                    ->label('Breadcrumbs')
                    ->schema([
                        TextInput::make('label')->required()->maxLength(120),
                        TextInput::make('href')->required()->maxLength(255),
                    ])
                    ->columns(2)
                    ->defaultItems(0)
                    ->reorderable()
                    ->collapsible()
                    ->columnSpanFull(),
            ])->columns(2),

            Section::make('Clubs Directory')->schema([
                TextInput::make($prefix.'.clubs.title')->label('Section Title')->required()->maxLength(160),
                TextInput::make($prefix.'.clubs.directoryLabel')->label('Directory Label')->required()->maxLength(120),
                TextInput::make($prefix.'.clubs.directoryUrl')->label('Directory URL')->required()->maxLength(255),
                TextInput::make($prefix.'.clubs.detailsLabel')->label('Details Label')->required()->maxLength(120),
                Repeater::make($prefix.'.clubs.items')
                    ->label('Clubs')
                    ->schema([
                        TextInput::make('id')->required()->maxLength(80),
                        TextInput::make('tag')->required()->maxLength(80),
                        TextInput::make('title')->required()->maxLength(160),
                        TextInput::make('href')->required()->maxLength(255),
                        MediaPicker::image('image', 'Image', true),
                        Textarea::make('summary')->required()->rows(2)->columnSpanFull(),
                    ])
                    ->columns(3)
                    ->defaultItems(0)
                    ->reorderable()
                    ->collapsible()
                    ->columnSpanFull(),
            ])->columns(2),

            Section::make('Featured Activity')->schema([
                TextInput::make($prefix.'.activities.title')->label('Section Title')->required()->maxLength(160),
                TextInput::make($prefix.'.activities.announcementLabel')->label('Announcement Label')->required()->maxLength(120),
                TextInput::make($prefix.'.activities.announcementUrl')->label('Announcement URL')->required()->maxLength(255),
                TextInput::make($prefix.'.activities.feature.badge')->label('Badge')->required()->maxLength(120),
                TextInput::make($prefix.'.activities.feature.title')->label('Feature Title')->required()->maxLength(160),
                MediaPicker::image($prefix.'.activities.feature.image', 'Feature Image', true),
                TextInput::make($prefix.'.activities.feature.href')->label('Feature URL')->required()->maxLength(255),
                Textarea::make($prefix.'.activities.feature.summary')->label('Feature Summary')->required()->rows(3)->columnSpanFull(),
            ])->columns(2),

            Section::make('Activity List')->schema([
                Repeater::make($prefix.'.activities.items')
                    ->label('Activities')
                    ->schema([
                        TextInput::make('id')->required()->maxLength(80),
                        TextInput::make('date')->required()->maxLength(120),
                        TextInput::make('title')->required()->maxLength(160),
                        TextInput::make('href')->required()->maxLength(255),
                        MediaPicker::image('image', 'Image', true),
                        Textarea::make('summary')->required()->rows(2)->columnSpanFull(),
                    ])
                    ->columns(3)
                    ->defaultItems(0)
                    ->reorderable()
                    ->collapsible()
                    ->columnSpanFull(),
            ]),

            Section::make('SEO')->schema([
                Textarea::make($prefix.'.seoDescription')->label('SEO Description')->required()->rows(2)->columnSpanFull(),
            ]),
        ];
    }

    /** @return array<int, Section> */
    private function transportFields(string $locale): array
    {
        $prefix = $locale.'_transport';

        return [
            Section::make('Hero')->schema([
                TextInput::make($prefix.'.hero.title')->label('Title')->required()->maxLength(160),
                MediaPicker::image($prefix.'.hero.image', 'Hero Image', true),
                TextInput::make($prefix.'.hero.imageAlt')->label('Image Alt')->maxLength(160),
                Repeater::make($prefix.'.hero.breadcrumbs')
                    ->label('Breadcrumbs')
                    ->schema([
                        TextInput::make('label')->required()->maxLength(120),
                        TextInput::make('href')->required()->maxLength(255),
                    ])
                    ->columns(2)
                    ->defaultItems(0)
                    ->reorderable()
                    ->collapsible()
                    ->columnSpanFull(),
            ])->columns(2),

            Section::make('Transport Cards')->schema([
                TextInput::make($prefix.'.overview.title')->label('Overview Title')->required()->maxLength(160),
                Repeater::make($prefix.'.cards')
                    ->label('Cards')
                    ->schema([
                        TextInput::make('title')->required()->maxLength(160),
                        TextInput::make('cta')->required()->maxLength(120),
                        TextInput::make('href')->required()->maxLength(255),
                        MediaPicker::icon('icon', 'Icon', true),
                        Textarea::make('description')->required()->rows(2)->columnSpanFull(),
                    ])
                    ->columns(2)
                    ->defaultItems(0)
                    ->reorderable()
                    ->collapsible()
                    ->columnSpanFull(),
            ])->columns(2),

            Section::make('Success Panel')->schema([
                TextInput::make($prefix.'.success.title')->label('Title')->required()->maxLength(160),
                MediaPicker::image($prefix.'.success.image', 'Image', true),
                TextInput::make($prefix.'.success.imageAlt')->label('Image Alt')->required()->maxLength(160),
                Textarea::make($prefix.'.success.description')->label('Description')->required()->rows(3)->columnSpanFull(),
                Repeater::make($prefix.'.success.links')
                    ->label('Links / Badges')
                    ->schema([
                        TextInput::make('label')->required()->maxLength(120),
                        MediaPicker::icon('icon', 'Icon', true),
                    ])
                    ->columns(2)
                    ->defaultItems(0)
                    ->reorderable()
                    ->collapsible()
                    ->columnSpanFull(),
            ])->columns(2),

            Section::make('SEO')->schema([
                Textarea::make($prefix.'.seoDescription')->label('SEO Description')->required()->rows(2)->columnSpanFull(),
            ]),
        ];
    }

    /** @return array<int, Section> */
    private function servicesFields(string $locale): array
    {
        $prefix = $locale.'_services';

        return [
            Section::make('Hero')->schema([
                TextInput::make($prefix.'.hero.title')->label('Title')->required()->maxLength(160),
                MediaPicker::image($prefix.'.hero.image', 'Hero Image', true),
                Repeater::make($prefix.'.hero.breadcrumbs')
                    ->label('Breadcrumbs')
                    ->schema([
                        TextInput::make('label')->required()->maxLength(120),
                        TextInput::make('href')->required()->maxLength(255),
                    ])
                    ->columns(2)
                    ->defaultItems(0)
                    ->reorderable()
                    ->collapsible()
                    ->columnSpanFull(),
            ])->columns(2),

            Section::make('Services Directory')->schema([
                TextInput::make($prefix.'.services.title')->label('Section Title')->required()->maxLength(160),
                TextInput::make($prefix.'.services.accessLabel')->label('Access Label')->required()->maxLength(120),
                TextInput::make($prefix.'.services.detailsLabel')->label('Details Label')->required()->maxLength(120),
                Repeater::make($prefix.'.services.items')
                    ->label('Service Cards')
                    ->schema([
                        TextInput::make('id')->required()->maxLength(80),
                        TextInput::make('title')->required()->maxLength(160),
                        TextInput::make('href')->required()->maxLength(255),
                        MediaPicker::image('image', 'Image', true),
                        Toggle::make('wide')->label('Wide Card'),
                        Textarea::make('access')->required()->rows(2)->columnSpanFull(),
                    ])
                    ->columns(3)
                    ->defaultItems(0)
                    ->reorderable()
                    ->collapsible()
                    ->columnSpanFull(),
            ])->columns(3),

            Section::make('Support Panel')->schema([
                TextInput::make($prefix.'.support.title')->label('Title')->required()->maxLength(160),
                MediaPicker::image($prefix.'.support.image', 'Image', true),
                TextInput::make($prefix.'.support.imageAlt')->label('Image Alt')->required()->maxLength(160),
                Textarea::make($prefix.'.support.summary')->label('Summary')->required()->rows(3)->columnSpanFull(),
                Repeater::make($prefix.'.support.badges')
                    ->label('Badges')
                    ->schema([
                        TextInput::make('label')->required()->maxLength(120),
                        MediaPicker::icon('icon', 'Icon', true),
                    ])
                    ->columns(2)
                    ->defaultItems(0)
                    ->reorderable()
                    ->collapsible()
                    ->columnSpanFull(),
            ])->columns(2),

            Section::make('SEO')->schema([
                Textarea::make($prefix.'.seoDescription')->label('SEO Description')->required()->rows(2)->columnSpanFull(),
            ]),
        ];
    }

    private function targetKeyForSchema(): string
    {
        return is_string($this->data['target_key'] ?? null) && $this->data['target_key'] !== '' ? $this->data['target_key'] : 'campus_life.landing';
    }

    /** @return array<string, mixed> */
    private function normalizeLandingPayload(array $payload): array
    {
        $payload['hero']['quickLinks'] = $this->listOfArrays($payload['hero']['quickLinks'] ?? []);
        $payload['stats'] = $this->listOfArrays($payload['stats'] ?? []);
        $payload['features']['items'] = $this->listOfArrays($payload['features']['items'] ?? []);
        $payload['services'] = $this->listOfArrays($payload['services'] ?? []);
        $payload['gallery']['images'] = $this->listOfArrays($payload['gallery']['images'] ?? []);
        $payload['portals'] = $this->listOfArrays($payload['portals'] ?? []);

        return $payload;
    }

    /** @return array<string, mixed> */
    private function normalizeServicesPayload(array $payload): array
    {
        $payload['type'] = 'services';
        $payload['hero']['breadcrumbs'] = $this->listOfArrays($payload['hero']['breadcrumbs'] ?? []);
        $payload['services']['items'] = $this->listOfArrays($payload['services']['items'] ?? []);
        $payload['support']['badges'] = $this->listOfArrays($payload['support']['badges'] ?? []);

        return $payload;
    }

    /** @return array<string, mixed> */
    private function normalizeTransportPayload(array $payload): array
    {
        $payload['type'] = 'transport';
        $payload['hero']['breadcrumbs'] = $this->listOfArrays($payload['hero']['breadcrumbs'] ?? []);
        $payload['cards'] = $this->listOfArrays($payload['cards'] ?? []);
        $payload['success']['links'] = $this->listOfArrays($payload['success']['links'] ?? []);

        return $payload;
    }

    /** @return array<string, mixed> */
    private function normalizeClubsActivitiesPayload(array $payload): array
    {
        $payload['type'] = 'clubs-activities';
        $payload['hero']['breadcrumbs'] = $this->listOfArrays($payload['hero']['breadcrumbs'] ?? []);
        $payload['clubs']['items'] = $this->listOfArrays($payload['clubs']['items'] ?? []);
        $payload['activities']['items'] = $this->listOfArrays($payload['activities']['items'] ?? []);

        return $payload;
    }

    /** @return array<string, mixed> */
    private function normalizeCareerDevelopmentPayload(array $payload): array
    {
        $payload['type'] = 'career-development';
        $payload['hero']['breadcrumbs'] = $this->listOfArrays($payload['hero']['breadcrumbs'] ?? []);
        $payload['services']['items'] = $this->listOfArrays($payload['services']['items'] ?? []);
        $payload['success']['badges'] = $this->listOfArrays($payload['success']['badges'] ?? []);

        return $payload;
    }

    /** @return array<string, mixed> */
    private function normalizeClinicalPayload(string $kind, array $payload): array
    {
        $payload['type'] = $kind;
        unset($payload['today']);
        $payload['hero']['breadcrumbs'] = $this->listOfArrays($payload['hero']['breadcrumbs'] ?? []);
        $payload['schedule'] = $this->listOfArrays($payload['schedule'] ?? []);

        if ($kind === 'dental') {
            $payload['services'] = $this->listOfArrays($payload['services'] ?? []);
            unset($payload['departments'], $payload['emergency']);
        } else {
            $payload['departments'] = $this->listOfArrays($payload['departments'] ?? []);
            unset($payload['services']);
        }

        return $payload;
    }

    /** @return array<string, mixed> */
    private function normalizeHealthInsurancePayload(array $payload): array
    {
        $payload['type'] = 'health-insurance';
        $payload['hero']['breadcrumbs'] = $this->listOfArrays($payload['hero']['breadcrumbs'] ?? []);
        $payload['sections'] = array_map(function (array $section): array {
            $section['items'] = $this->listOfArrays($section['items'] ?? []);
            $section['list'] = array_values(array_filter(array_map(
                static fn (mixed $item): string => is_array($item) ? (string) ($item['item'] ?? '') : (string) $item,
                is_array($section['list'] ?? null) ? $section['list'] : []
            ), static fn (string $item): bool => trim($item) !== ''));

            if (($section['type'] ?? null) !== 'documents') {
                unset($section['support']);
            }

            return $section;
        }, $this->listOfArrays($payload['sections'] ?? []));

        return $payload;
    }

    /** @return array<int, array<string, mixed>> */
    private function listOfArrays(mixed $items): array
    {
        return array_values(array_filter(is_array($items) ? $items : [], static fn (mixed $item): bool => is_array($item)));
    }

    /** @param array<string, array<int, string>> $errors */
    private function formatValidationErrors(array $errors): string
    {
        return collect($errors)->flatten()->implode(PHP_EOL);
    }
}
