<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Contracts\Cms\CmsTargetRegistryInterface;
use App\Contracts\Cms\CmsWorkflowServiceInterface;
use App\Contracts\Page\CampusLifePageServiceInterface;
use App\Contracts\Page\VirtualTourPageServiceInterface;
use App\DTOs\Cms\CmsTargetDTO;
use App\Exceptions\ConflictException;
use App\Filament\Support\MediaPicker;
use App\Models\User\User;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\DateTimePicker;
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

    private VirtualTourPageServiceInterface $virtualTourPageService;

    public function boot(
        CampusLifePageServiceInterface $campusLifePageService,
        CmsTargetRegistryInterface $targetRegistry,
        CmsWorkflowServiceInterface $cmsWorkflowService,
        VirtualTourPageServiceInterface $virtualTourPageService,
    ): void {
        $this->campusLifePageService = $campusLifePageService;
        $this->targetRegistry = $targetRegistry;
        $this->cmsWorkflowService = $cmsWorkflowService;
        $this->virtualTourPageService = $virtualTourPageService;
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
                ->action(function (array $data): void {
                    $this->schedule((string) $data['publish_at']);
                }),
            Action::make('unpublish')->label('Unpublish')->icon('heroicon-o-x-circle')->color('danger')->requiresConfirmation()->action(function (): void {
                $this->unpublish();
            }),
        ];
    }

    public function loadTarget(string $targetKey): void
    {
        $this->assertCampusLifeTarget($targetKey);

        if (! in_array($targetKey, $this->curatedTargetKeys(), true)) {
            $this->draftVersion = $this->cmsWorkflowService->latestEditableDraftVersion($targetKey, (int) auth()->id());
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
                'ar_jobs' => [],
                'en_jobs' => [],
                'ar_dental' => [],
                'en_dental' => [],
                'ar_hospital' => [],
                'en_hospital' => [],
                'ar_health_insurance' => [],
                'en_health_insurance' => [],
                'ar_damascus_research_pub' => [],
                'en_damascus_research_pub' => [],
                'ar_rules_regulations' => [],
                'en_rules_regulations' => [],
                'ar_general_rules' => [],
                'en_general_rules' => [],
                'ar_exam_instructions' => [],
                'en_exam_instructions' => [],
                'ar_exam_penalties' => [],
                'en_exam_penalties' => [],
            ]);

            return;
        }

        $draftPayload = $this->cmsWorkflowService->latestEditableDraftPayload($targetKey, (int) auth()->id());
        $payload = is_array($draftPayload) ? $draftPayload : ($targetKey === 'campus_life.virtual_tour'
            ? $this->virtualTourPageService->getEditablePayload()
            : $this->campusLifePageService->getEditablePayload($targetKey));
        $this->draftVersion = $this->cmsWorkflowService->latestEditableDraftVersion($targetKey, (int) auth()->id());

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
            'ar_jobs' => $targetKey === 'campus_life.jobs' && is_array($payload['translations']['ar'] ?? null) ? $payload['translations']['ar'] : [],
            'en_jobs' => $targetKey === 'campus_life.jobs' && is_array($payload['translations']['en'] ?? null) ? $payload['translations']['en'] : [],
            'ar_dental' => $targetKey === 'campus_life.dental' && is_array($payload['translations']['ar'] ?? null) ? $payload['translations']['ar'] : [],
            'en_dental' => $targetKey === 'campus_life.dental' && is_array($payload['translations']['en'] ?? null) ? $payload['translations']['en'] : [],
            'ar_hospital' => $targetKey === 'campus_life.hospital' && is_array($payload['translations']['ar'] ?? null) ? $payload['translations']['ar'] : [],
            'en_hospital' => $targetKey === 'campus_life.hospital' && is_array($payload['translations']['en'] ?? null) ? $payload['translations']['en'] : [],
            'ar_health_insurance' => $targetKey === 'campus_life.health-insurance' && is_array($payload['translations']['ar'] ?? null) ? $payload['translations']['ar'] : [],
            'en_health_insurance' => $targetKey === 'campus_life.health-insurance' && is_array($payload['translations']['en'] ?? null) ? $payload['translations']['en'] : [],
            'ar_damascus_research_pub' => $targetKey === 'campus_life.damascus-research-pub' && is_array($payload['translations']['ar'] ?? null) ? $payload['translations']['ar'] : [],
            'en_damascus_research_pub' => $targetKey === 'campus_life.damascus-research-pub' && is_array($payload['translations']['en'] ?? null) ? $payload['translations']['en'] : [],
            'ar_rules_regulations' => $targetKey === 'campus_life.rules-regulations' && is_array($payload['translations']['ar'] ?? null) ? $payload['translations']['ar'] : [],
            'en_rules_regulations' => $targetKey === 'campus_life.rules-regulations' && is_array($payload['translations']['en'] ?? null) ? $payload['translations']['en'] : [],
            'ar_general_rules' => $targetKey === 'campus_life.general-rules' && is_array($payload['translations']['ar'] ?? null) ? $payload['translations']['ar'] : [],
            'en_general_rules' => $targetKey === 'campus_life.general-rules' && is_array($payload['translations']['en'] ?? null) ? $payload['translations']['en'] : [],
            'ar_exam_instructions' => $targetKey === 'campus_life.exam-instructions' && is_array($payload['translations']['ar'] ?? null) ? $payload['translations']['ar'] : [],
            'en_exam_instructions' => $targetKey === 'campus_life.exam-instructions' && is_array($payload['translations']['en'] ?? null) ? $payload['translations']['en'] : [],
            'ar_exam_penalties' => $targetKey === 'campus_life.exam-penalties' && is_array($payload['translations']['ar'] ?? null) ? $payload['translations']['ar'] : [],
            'en_exam_penalties' => $targetKey === 'campus_life.exam-penalties' && is_array($payload['translations']['en'] ?? null) ? $payload['translations']['en'] : [],
            'ar_virtual_tour' => $targetKey === 'campus_life.virtual_tour' && is_array($payload['translations']['ar'] ?? null) ? $payload['translations']['ar'] : [],
            'en_virtual_tour' => $targetKey === 'campus_life.virtual_tour' && is_array($payload['translations']['en'] ?? null) ? $payload['translations']['en'] : [],
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

        if (! $target instanceof CmsTargetDTO || $target->area !== 'campus_life') {
            throw new \InvalidArgumentException('Unsupported campus life target.');
        }
    }

    /** @return array<int, string> */
    private function curatedTargetKeys(): array
    {
        return [
            'campus_life.landing',
            'campus_life.virtual_tour',
            'campus_life.services',
            'campus_life.transport',
            'campus_life.clubs-activities',
            'campus_life.career-development',
            'campus_life.jobs',
            'campus_life.dental',
            'campus_life.hospital',
            'campus_life.health-insurance',
            ...$this->simpleInfoTargetKeys(),
        ];
    }

    /** @param array<string, mixed> $state */
    private function payloadFromForm(array $state): array
    {
        if (($state['target_key'] ?? null) === 'campus_life.virtual_tour') {
            return [
                'translations' => [
                    'ar' => $this->normalizeVirtualTourPayload(is_array($state['ar_virtual_tour'] ?? null) ? $state['ar_virtual_tour'] : []),
                    'en' => $this->normalizeVirtualTourPayload(is_array($state['en_virtual_tour'] ?? null) ? $state['en_virtual_tour'] : []),
                ],
            ];
        }
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

        if (($state['target_key'] ?? null) === 'campus_life.jobs') {
            return [
                'translations' => [
                    'ar' => $this->normalizeJobsPayload(is_array($state['ar_jobs'] ?? null) ? $state['ar_jobs'] : []),
                    'en' => $this->normalizeJobsPayload(is_array($state['en_jobs'] ?? null) ? $state['en_jobs'] : []),
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

        if (in_array($state['target_key'] ?? null, $this->simpleInfoTargetKeys(), true)) {
            $stateKey = $this->simpleInfoStateKey((string) $state['target_key']);

            return [
                'translations' => [
                    'ar' => $this->normalizeSimpleInfoPayload(is_array($state['ar_'.$stateKey] ?? null) ? $state['ar_'.$stateKey] : []),
                    'en' => $this->normalizeSimpleInfoPayload(is_array($state['en_'.$stateKey] ?? null) ? $state['en_'.$stateKey] : []),
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
        if ($this->targetKeyForSchema() === 'campus_life.virtual_tour') {
            return $this->virtualTourFields($locale);
        }
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

        if ($this->targetKeyForSchema() === 'campus_life.jobs') {
            return $this->jobsFields($locale);
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

        if (in_array($this->targetKeyForSchema(), $this->simpleInfoTargetKeys(), true)) {
            return $this->simpleInfoFields($locale, $this->simpleInfoStateKey($this->targetKeyForSchema()));
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
                        Toggle::make('verified')->label('Figure verified by content owner')->required(),
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
                Textarea::make($prefix.'.portalGuidance')->label('Availability Guidance')->helperText('Displayed as non-link guidance when no verified portal destination is available.')->required()->rows(2)->columnSpanFull(),
                Repeater::make($prefix.'.portals')
                    ->label('Portals')
                    ->schema([
                        TextInput::make('title')->required()->maxLength(160),
                        MediaPicker::icon('icon', 'Icon', true),
                        TextInput::make('url')->required()->maxLength(255)->helperText('Use a real localized SPU route. Placeholder and # URLs cannot be published.'),
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
    private function virtualTourFields(string $locale): array
    {
        $prefix = $locale.'_virtual_tour';

        return [
            Section::make('Virtual Tour Hero')->schema([
                TextInput::make($prefix.'.hero.eyebrow')->required()->maxLength(120),
                TextInput::make($prefix.'.hero.title')->required()->maxLength(180),
                Textarea::make($prefix.'.hero.summary')->required()->rows(3)->columnSpanFull(),
                MediaPicker::image($prefix.'.hero.image', 'Hero Image', true),
                TextInput::make($prefix.'.hero.imageAlt')->label('Hero Image Alt')->required()->maxLength(200),
                TextInput::make($prefix.'.hero.primaryLabel')->required()->maxLength(120),
                TextInput::make($prefix.'.hero.primaryUrl')->required()->maxLength(255),
                TextInput::make($prefix.'.hero.secondaryLabel')->required()->maxLength(120),
                TextInput::make($prefix.'.hero.secondaryUrl')->required()->maxLength(255),
            ])->columns(2),
            Section::make('Interactive Photo Viewer')->description('Scenes are standard campus photographs. Do not describe them as 360-degree panoramas.')->schema([
                TextInput::make($prefix.'.tour.eyebrow')->required()->maxLength(120),
                TextInput::make($prefix.'.tour.title')->required()->maxLength(180),
                Textarea::make($prefix.'.tour.summary')->required()->rows(3)->columnSpanFull(),
                TextInput::make($prefix.'.tour.experienceLabel')->required()->maxLength(160),
                TextInput::make($prefix.'.tour.controlLabel')->required()->maxLength(120),
                TextInput::make($prefix.'.tour.fullscreenLabel')->required()->maxLength(120),
                TextInput::make($prefix.'.tour.exitFullscreenLabel')->required()->maxLength(120),
                TextInput::make($prefix.'.tour.playLabel')->required()->maxLength(120),
                TextInput::make($prefix.'.tour.pauseLabel')->required()->maxLength(120),
                TextInput::make($prefix.'.tour.zoomInLabel')->required()->maxLength(120),
                TextInput::make($prefix.'.tour.zoomOutLabel')->required()->maxLength(120),
                TextInput::make($prefix.'.tour.resetLabel')->required()->maxLength(120),
                TextInput::make($prefix.'.tour.previousLabel')->required()->maxLength(120),
                TextInput::make($prefix.'.tour.nextLabel')->required()->maxLength(120),
                TextInput::make($prefix.'.tour.autoplayInterval')->label('Autoplay Interval (ms)')->numeric()->minValue(3000)->maxValue(20000)->required(),
                Repeater::make($prefix.'.tour.scenes')->label('Photo Scenes')->schema([
                    TextInput::make('id')->required()->maxLength(80),
                    TextInput::make('title')->required()->maxLength(180),
                    Textarea::make('summary')->required()->rows(2)->columnSpanFull(),
                    MediaPicker::image('image', 'Scene Image', true),
                    TextInput::make('imageAlt')->required()->maxLength(200),
                    Repeater::make('hotspots')->schema([
                        TextInput::make('id')->required()->maxLength(80),
                        TextInput::make('label')->required()->maxLength(160),
                        TextInput::make('x')->numeric()->minValue(0)->maxValue(100)->required(),
                        TextInput::make('y')->numeric()->minValue(0)->maxValue(100)->required(),
                        Textarea::make('description')->required()->rows(2)->columnSpanFull(),
                        TextInput::make('targetSceneId')->label('Optional Target Scene ID')->maxLength(80),
                    ])->columns(2)->reorderable()->columnSpanFull(),
                ])->columns(2)->reorderable()->minItems(1)->columnSpanFull(),
            ])->columns(2),
            Section::make('Campus Highlights')->schema([
                TextInput::make($prefix.'.highlights.eyebrow')->required()->maxLength(120),
                TextInput::make($prefix.'.highlights.title')->required()->maxLength(180),
                Textarea::make($prefix.'.highlights.summary')->required()->rows(2)->columnSpanFull(),
                Repeater::make($prefix.'.highlights.items')->schema([
                    TextInput::make('id')->required()->maxLength(80),
                    TextInput::make('title')->required()->maxLength(180),
                    TextInput::make('href')->required()->maxLength(255),
                    TextInput::make('label')->required()->maxLength(120),
                    MediaPicker::image('image', 'Image', true),
                    TextInput::make('imageAlt')->required()->maxLength(200),
                    Toggle::make('featured'),
                    Textarea::make('summary')->required()->rows(2)->columnSpanFull(),
                ])->columns(2)->reorderable()->columnSpanFull(),
            ])->columns(2),
            Section::make('Facilities')->schema([
                TextInput::make($prefix.'.facilities.eyebrow')->required()->maxLength(120),
                TextInput::make($prefix.'.facilities.title')->required()->maxLength(180),
                Textarea::make($prefix.'.facilities.summary')->required()->rows(2)->columnSpanFull(),
                TextInput::make($prefix.'.facilities.detailsLabel')->required()->maxLength(120),
                Repeater::make($prefix.'.facilities.items')->schema([
                    TextInput::make('id')->required()->maxLength(80),
                    TextInput::make('title')->required()->maxLength(180),
                    TextInput::make('href')->required()->maxLength(255),
                    MediaPicker::icon('icon', 'Icon', true),
                    MediaPicker::image('image', 'Image', true),
                    Textarea::make('summary')->required()->rows(2)->columnSpanFull(),
                ])->columns(2)->reorderable()->columnSpanFull(),
            ])->columns(2),
            Section::make('SEO')->schema([
                TextInput::make($prefix.'.seo.title')->required()->maxLength(180),
                Textarea::make($prefix.'.seo.description')->required()->rows(2),
                MediaPicker::image($prefix.'.seo.image', 'SEO Image', true),
            ])->columns(2),
        ];
    }

    /** @param array<string, mixed> $payload @return array<string, mixed> */
    private function normalizeVirtualTourPayload(array $payload): array
    {
        $payload['tour'] = is_array($payload['tour'] ?? null) ? $payload['tour'] : [];
        $payload['tour']['autoplayInterval'] = max(3000, min(20000, (int) ($payload['tour']['autoplayInterval'] ?? 6000)));
        $payload['tour']['scenes'] = $this->listOfArrays($payload['tour']['scenes'] ?? []);
        foreach ($payload['tour']['scenes'] as &$scene) {
            $scene['hotspots'] = $this->listOfArrays($scene['hotspots'] ?? []);
            foreach ($scene['hotspots'] as &$hotspot) {
                $hotspot['x'] = max(0, min(100, (float) ($hotspot['x'] ?? 50)));
                $hotspot['y'] = max(0, min(100, (float) ($hotspot['y'] ?? 50)));
            }
            unset($hotspot);
        }
        unset($scene);
        $payload['highlights'] = is_array($payload['highlights'] ?? null) ? $payload['highlights'] : [];
        $payload['highlights']['items'] = $this->listOfArrays($payload['highlights']['items'] ?? []);
        $payload['facilities'] = is_array($payload['facilities'] ?? null) ? $payload['facilities'] : [];
        $payload['facilities']['items'] = $this->listOfArrays($payload['facilities']['items'] ?? []);

        return $payload;
    }

    /** @return array<int, Section> */
    private function simpleInfoFields(string $locale, string $stateKey): array
    {
        $prefix = $locale.'_'.$stateKey;

        return [
            Section::make('Hero')->schema([
                TextInput::make($prefix.'.hero.title')->label('Title')->required()->maxLength(180),
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

            Section::make('Overview')->schema([
                TextInput::make($prefix.'.overview.title')->label('Title')->required()->maxLength(180),
                Textarea::make($prefix.'.overview.summary')->label('Summary')->required()->rows(3)->columnSpanFull(),
                Textarea::make($prefix.'.seoDescription')->label('SEO Description')->rows(2)->columnSpanFull(),
            ])->columns(2),

            Section::make('Information Cards')->schema([
                Repeater::make($prefix.'.items')
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

    /** @return array<int, Section> */
    private function jobsFields(string $locale): array
    {
        $prefix = $locale.'_jobs';

        return [
            Section::make('Job Board Hero')->schema([
                TextInput::make($prefix.'.hero.title')->required()->maxLength(180),
                MediaPicker::image($prefix.'.hero.image', 'Hero Image', true),
                Textarea::make($prefix.'.hero.summary')->required()->rows(3)->columnSpanFull(),
            ])->columns(2),
            Section::make('Board and Detail Labels')->schema([
                TextInput::make($prefix.'.labels.category')->required()->maxLength(100),
                TextInput::make($prefix.'.labels.type')->required()->maxLength(100),
                TextInput::make($prefix.'.labels.search')->required()->maxLength(180),
                TextInput::make($prefix.'.labels.searchAction')->required()->maxLength(100),
                TextInput::make($prefix.'.labels.showing')->required()->maxLength(100),
                TextInput::make($prefix.'.labels.positions')->required()->maxLength(100),
                TextInput::make($prefix.'.labels.of')->required()->maxLength(100),
                TextInput::make($prefix.'.labels.previous')->required()->maxLength(100),
                TextInput::make($prefix.'.labels.next')->required()->maxLength(100),
                TextInput::make($prefix.'.labels.reset')->required()->maxLength(100),
                TextInput::make($prefix.'.labels.noResults')->required()->maxLength(180),
                TextInput::make($prefix.'.labels.learnMore')->required()->maxLength(100),
                TextInput::make($prefix.'.labels.apply')->required()->maxLength(100),
                TextInput::make($prefix.'.labels.applicationsClosed')->required()->maxLength(120),
                TextInput::make($prefix.'.labels.postedOn')->required()->maxLength(100),
                TextInput::make($prefix.'.labels.closesOn')->required()->maxLength(100),
                TextInput::make($prefix.'.labels.status')->required()->maxLength(100),
                TextInput::make($prefix.'.labels.openStatus')->required()->maxLength(100),
                TextInput::make($prefix.'.labels.closedStatus')->required()->maxLength(100),
                TextInput::make($prefix.'.labels.share')->required()->maxLength(100),
                TextInput::make($prefix.'.labels.copyLink')->required()->maxLength(100),
                TextInput::make($prefix.'.labels.copied')->required()->maxLength(100),
                TextInput::make($prefix.'.labels.related')->required()->maxLength(100),
                TextInput::make($prefix.'.labels.overview')->required()->maxLength(100),
                TextInput::make($prefix.'.labels.responsibilities')->required()->maxLength(120),
                TextInput::make($prefix.'.labels.requirements')->required()->maxLength(100),
                TextInput::make($prefix.'.labels.benefits')->required()->maxLength(100),
                TextInput::make($prefix.'.labels.back')->required()->maxLength(120),
            ])->columns(3),
            Section::make('Job Filters')->schema([
                Repeater::make($prefix.'.categories')
                    ->label('Categories')
                    ->schema([
                        TextInput::make('id')->required()->maxLength(80),
                        TextInput::make('label')->required()->maxLength(120),
                    ])
                    ->columns(2)
                    ->defaultItems(0)
                    ->reorderable()
                    ->collapsible()
                    ->columnSpanFull(),
                Repeater::make($prefix.'.types')
                    ->label('Job Types')
                    ->schema([
                        TextInput::make('id')->required()->maxLength(80),
                        TextInput::make('label')->required()->maxLength(120),
                    ])
                    ->columns(2)
                    ->defaultItems(0)
                    ->reorderable()
                    ->collapsible()
                    ->columnSpanFull(),
            ]),
            Section::make('Job Catalog')->schema([
                Repeater::make($prefix.'.jobs')
                    ->schema([
                        TextInput::make('id')->required()->maxLength(80),
                        TextInput::make('slug')->required()->maxLength(160),
                        Select::make('category')->required()->options([
                            'academic' => 'Academic',
                            'administrative' => 'Administrative',
                            'driver' => 'Driver',
                            'technical' => 'Technical',
                            'medical' => 'Medical',
                        ]),
                        Select::make('type')->required()->options([
                            'full-time' => 'Full-time',
                            'part-time' => 'Part-time',
                            'contract' => 'Contract',
                        ]),
                        Select::make('status')->required()->options([
                            'open' => 'Open',
                            'closed' => 'Closed',
                        ]),
                        Toggle::make('applicationEligible')->label('Application Eligible'),
                        TextInput::make('title')->required()->maxLength(240)->columnSpanFull(),
                        TextInput::make('department')->required()->maxLength(180),
                        TextInput::make('location')->required()->maxLength(180),
                        Textarea::make('shortDescription')->required()->rows(2)->columnSpanFull(),
                        TagsInput::make('overview')->required()->columnSpanFull(),
                        TagsInput::make('responsibilities')->required()->columnSpanFull(),
                        TagsInput::make('requirements')->required()->columnSpanFull(),
                        TagsInput::make('benefits')->required()->columnSpanFull(),
                        DatePicker::make('postedDate')->required()->native(false),
                        DatePicker::make('closeDate')->required()->native(false),
                        MediaPicker::image('image', 'Job Image', true)->columnSpanFull(),
                    ])
                    ->columns(3)
                    ->defaultItems(0)
                    ->reorderable()
                    ->cloneable()
                    ->collapsible()
                    ->itemLabel(fn (array $state): ?string => $state['title'] ?? $state['slug'] ?? null)
                    ->columnSpanFull(),
            ]),
            Section::make('SEO')->schema([
                Textarea::make($prefix.'.seoDescription')->required()->rows(2)->columnSpanFull(),
            ]),
        ];
    }

    private function targetKeyForSchema(): string
    {
        return is_string($this->data['target_key'] ?? null) && $this->data['target_key'] !== '' ? $this->data['target_key'] : 'campus_life.landing';
    }

    /** @return array<int, string> */
    private function simpleInfoTargetKeys(): array
    {
        return [
            'campus_life.damascus-research-pub',
            'campus_life.rules-regulations',
            'campus_life.general-rules',
            'campus_life.exam-instructions',
            'campus_life.exam-penalties',
        ];
    }

    private function simpleInfoStateKey(string $targetKey): string
    {
        return match ($targetKey) {
            'campus_life.damascus-research-pub' => 'damascus_research_pub',
            'campus_life.rules-regulations' => 'rules_regulations',
            'campus_life.general-rules' => 'general_rules',
            'campus_life.exam-instructions' => 'exam_instructions',
            'campus_life.exam-penalties' => 'exam_penalties',
            default => throw new \InvalidArgumentException('Unsupported campus life info target.'),
        };
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
    private function normalizeJobsPayload(array $payload): array
    {
        $payload['type'] = 'job-board';
        $payload['categories'] = $this->listOfArrays($payload['categories'] ?? []);
        $payload['types'] = $this->listOfArrays($payload['types'] ?? []);
        $payload['jobs'] = array_map(function (array $job): array {
            $job['id'] = strtolower(trim((string) ($job['id'] ?? '')));
            $job['slug'] = strtolower(trim((string) ($job['slug'] ?? '')));
            $job['applicationEligible'] = (bool) ($job['applicationEligible'] ?? false);

            foreach (['overview', 'responsibilities', 'requirements', 'benefits'] as $field) {
                $job[$field] = array_values(array_filter(array_map(
                    static fn (mixed $item): string => trim((string) $item),
                    is_array($job[$field] ?? null) ? $job[$field] : [],
                ), static fn (string $item): bool => $item !== ''));
            }

            return $job;
        }, $this->listOfArrays($payload['jobs'] ?? []));

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

    /** @return array<string, mixed> */
    private function normalizeSimpleInfoPayload(array $payload): array
    {
        $payload['type'] = 'simple-info';
        $payload['hero']['breadcrumbs'] = $this->listOfArrays($payload['hero']['breadcrumbs'] ?? []);
        $payload['items'] = collect($this->listOfArrays($payload['items'] ?? []))
            ->map(static fn (array $item): array => [
                'title' => (string) ($item['title'] ?? ''),
                'body' => (string) ($item['body'] ?? ''),
            ])
            ->values()
            ->all();

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
