<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Contracts\Cms\CmsTargetRegistryInterface;
use App\Contracts\Cms\CmsWorkflowServiceInterface;
use App\Contracts\Page\AboutPageServiceInterface;
use App\DTOs\Cms\CmsTargetDTO;
use App\Exceptions\ConflictException;
use App\Filament\Concerns\LimitsCmsTargetsToGrants;
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
use Illuminate\Validation\ValidationException;

class ManageAbout extends Page implements HasForms
{
    use InteractsWithForms;
    use LimitsCmsTargetsToGrants;

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
        return static::canAccessCmsArea('manage-pages', 'about');
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
        $this->loadTarget($this->firstGrantedTarget('about.landing', $this->targetOptions()));
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make(__('admin_pages.about.about_target'))->schema([
                    Select::make('target_key')
                        ->label(__('admin_pages.about.page_subpage'))
                        ->options($this->targetOptions())
                        ->required()
                        ->live()
                        ->afterStateUpdated(fn (?string $state): mixed => is_string($state) && $state !== '' ? $this->loadTarget($state) : null),
                ]),
                Tabs::make('about_locales')
                    ->tabs([
                        Tab::make('Arabic')->label(__('admin_pages.about.arabic'))->schema($this->payloadFields('ar')),
                        Tab::make('English')->label(__('admin_pages.about.english'))->schema($this->payloadFields('en')),
                    ])
                    ->persistTabInQueryString('locale')
                    ->columnSpanFull(),
            ])
            ->statePath('data');
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('save')->label(__('admin_pages.about.save_draft'))->icon('heroicon-o-check')->color('gray')->action(function (): void {
                $this->save();
            }),
            Action::make('preview_ar')->label(__('admin_pages.about.preview_ar'))->icon('heroicon-o-eye')->color('info')->action(function (): void {
                $this->openPreview('ar');
            }),
            Action::make('preview_en')->label(__('admin_pages.about.preview_en'))->icon('heroicon-o-eye')->color('info')->action(function (): void {
                $this->openPreview('en');
            }),
            Action::make('publish')->label(__('admin_pages.about.publish'))->icon('heroicon-o-paper-airplane')->color('success')->requiresConfirmation()->action(function (): void {
                $this->publish();
            }),
            Action::make('schedule')
                ->label(__('admin_pages.about.schedule'))
                ->icon('heroicon-o-clock')
                ->color('warning')
                ->form([
                    DateTimePicker::make('publish_at')->label(__('admin_pages.about.publish_at'))->required()->minDate(now())->native(false),
                ])
                ->action(fn (array $data) => $this->schedule((string) $data['publish_at'])),
            Action::make('unpublish')->label(__('admin_pages.about.unpublish'))->icon('heroicon-o-x-circle')->color('danger')->requiresConfirmation()->action(function (): void {
                $this->unpublish();
            }),
        ];
    }

    public function loadTarget(string $targetKey): void
    {
        $this->assertAboutTarget($targetKey);

        if (! in_array($targetKey, ['about.landing', 'about.vision-mission', 'about.history', 'about.leadership', 'about.directorates', 'about.directorates_staff', 'about.partnerships', 'about.quality-policy', 'about.ethical-charter', 'about.organizational-structure', 'about.accreditation', 'about.why-spu'], true)) {
            $this->draftVersion = $this->cmsWorkflowService->latestEditableDraftVersion($targetKey, (int) auth()->id());
            $this->form->fill([
                'target_key' => $targetKey,
                'ar_landing' => [],
                'en_landing' => [],
                'ar_vision_mission' => [],
                'en_vision_mission' => [],
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
                'ar_accreditation' => [],
                'en_accreditation' => [],
                'ar_why_spu' => [],
                'en_why_spu' => [],
            ]);

            return;
        }

        $draftPayload = $this->cmsWorkflowService->latestEditableDraftPayload($targetKey, (int) auth()->id());
        $payload = is_array($draftPayload) ? $draftPayload : $this->aboutPageService->getEditablePayload($targetKey);
        $this->draftVersion = $this->cmsWorkflowService->latestEditableDraftVersion($targetKey, (int) auth()->id());

        $this->form->fill([
            'target_key' => $targetKey,
            'ar_landing' => $targetKey === 'about.landing' && is_array($payload['translations']['ar'] ?? null) ? $this->landingFormData($payload['translations']['ar']) : [],
            'en_landing' => $targetKey === 'about.landing' && is_array($payload['translations']['en'] ?? null) ? $this->landingFormData($payload['translations']['en']) : [],
            'ar_vision_mission' => $targetKey === 'about.vision-mission' && is_array($payload['translations']['ar'] ?? null) ? $this->visionMissionFormData($payload['translations']['ar']) : [],
            'en_vision_mission' => $targetKey === 'about.vision-mission' && is_array($payload['translations']['en'] ?? null) ? $this->visionMissionFormData($payload['translations']['en']) : [],
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
            'ar_accreditation' => $targetKey === 'about.accreditation' && is_array($payload['translations']['ar'] ?? null) ? $this->importedContentFormData($payload['translations']['ar']) : [],
            'en_accreditation' => $targetKey === 'about.accreditation' && is_array($payload['translations']['en'] ?? null) ? $this->importedContentFormData($payload['translations']['en']) : [],
            'ar_why_spu' => $targetKey === 'about.why-spu' && is_array($payload['translations']['ar'] ?? null) ? $this->importedContentFormData($payload['translations']['ar']) : [],
            'en_why_spu' => $targetKey === 'about.why-spu' && is_array($payload['translations']['en'] ?? null) ? $this->importedContentFormData($payload['translations']['en']) : [],
        ]);
    }

    public function save(): void
    {
        /** @var User $user */
        $user = auth()->user();

        try {
            $draft = $this->cmsWorkflowService->saveDraft($this->currentTargetKey(), $this->payloadFromForm($this->currentFormData()), (int) $user->id, $this->draftVersion);
            $this->draftVersion = $draft->version;

            Notification::make()->title(__('admin_pages.about.about_draft_saved'))->success()->send();
        } catch (ConflictException $e) {
            $this->draftVersion = $e->currentVersion;
            Notification::make()->title(__('admin_pages.about.draft_conflict_detected'))->body(__('admin_pages.about.reload_this_about_target_before_saving_again'))->danger()->persistent()->send();
        } catch (\Throwable $e) {
            report($e);
            Notification::make()->title(__('admin_pages.about.failed_to_save_about_draft'))->body($e->getMessage())->danger()->send();
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
            Notification::make()->title(__('admin_pages.about.draft_conflict_detected'))->body(__('admin_pages.about.reload_this_about_target_before_previewing_again'))->danger()->persistent()->send();
        } catch (\Throwable $e) {
            report($e);
            Notification::make()->title(__('admin_pages.about.failed_to_create_about_preview'))->body($e->getMessage())->danger()->send();
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

            Notification::make()->title(__('admin_pages.about.about_target_published'))->success()->send();
        } catch (ValidationException $e) {
            Notification::make()->title(__('admin_pages.about.publish_failed'))->body($this->formatValidationErrors($e->errors()))->danger()->persistent()->send();
        } catch (\Throwable $e) {
            report($e);
            Notification::make()->title(__('admin_pages.about.failed_to_publish_about_target'))->body($e->getMessage())->danger()->send();
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

            Notification::make()->title(__('admin_pages.about.about_target_scheduled'))->success()->send();
        } catch (ValidationException $e) {
            Notification::make()->title(__('admin_pages.about.schedule_failed'))->body($this->formatValidationErrors($e->errors()))->danger()->persistent()->send();
        } catch (\Throwable $e) {
            report($e);
            Notification::make()->title(__('admin_pages.about.failed_to_schedule_about_target'))->body($e->getMessage())->danger()->send();
        }
    }

    public function unpublish(): void
    {
        /** @var User $user */
        $user = auth()->user();
        $result = $this->cmsWorkflowService->unpublish($this->currentTargetKey(), (int) $user->id);
        $notification = Notification::make()->title($result ? __('admin_pages.about.about_target_unpublished') : __('admin_pages.about.no_published_about_target_found'));

        ($result ? $notification->success() : $notification->warning())->send();
    }

    /** @return array<string, string> */
    private function targetOptions(): array
    {
        return $this->onlyGrantedTargets($this->targetRegistry->forArea('about')
            ->mapWithKeys(fn (CmsTargetDTO $target): array => [$target->key => __($target->labelKey)])
            ->all());
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
            throw new \InvalidArgumentException(__('admin_pages.about.unsupported_about_target'));
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

        if (($state['target_key'] ?? null) === 'about.vision-mission') {
            return [
                'translations' => [
                    'ar' => $this->visionMissionPayloadFromForm(is_array($state['ar_vision_mission'] ?? null) ? $state['ar_vision_mission'] : []),
                    'en' => $this->visionMissionPayloadFromForm(is_array($state['en_vision_mission'] ?? null) ? $state['en_vision_mission'] : []),
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

        if (in_array($state['target_key'] ?? null, ['about.quality-policy', 'about.ethical-charter', 'about.organizational-structure', 'about.accreditation', 'about.why-spu'], true)) {
            $stateKey = $this->importedContentStateKey((string) $state['target_key']);

            return [
                'translations' => [
                    'ar' => $this->importedContentPayloadFromForm(is_array($state['ar_'.$stateKey] ?? null) ? $state['ar_'.$stateKey] : []),
                    'en' => $this->importedContentPayloadFromForm(is_array($state['en_'.$stateKey] ?? null) ? $state['en_'.$stateKey] : []),
                ],
            ];
        }

        throw new \InvalidArgumentException(__('admin_pages.about.this_about_subpage_form_will_be_structured_next_select_the_a'));
    }

    /** @return array<string, mixed> */
    private function currentFormData(): array
    {
        return is_array($this->data) ? $this->data : [];
    }

    /** @return array<int, Section> */
    private function payloadFields(string $locale): array
    {
        if ($this->targetKeyForSchema() === 'about.vision-mission') {
            return $this->visionMissionFields($locale);
        }

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

        if (in_array($this->targetKeyForSchema(), ['about.quality-policy', 'about.ethical-charter', 'about.organizational-structure', 'about.accreditation', 'about.why-spu'], true)) {
            return $this->importedContentFields($locale, $this->importedContentStateKey($this->targetKeyForSchema()));
        }

        if ($this->targetKeyForSchema() !== 'about.landing') {
            return [
                Section::make(__('admin_pages.about.subpage_schema_pending'))
                    ->description(__('admin_pages.about.we_are_converting_about_one_page_at_a_time_the_landing_page'))
                    ->schema([
                        TextInput::make($locale.'_subpage_pending')->label(__('admin_pages.about.status'))->default(__('admin_pages.about.structured_form_pending_for_this_subpage'))->disabled(),
                    ]),
            ];
        }

        $prefix = $locale.'_landing';

        return [
            Section::make(__('admin_pages.about.hero_and_story'))->schema([
                TextInput::make($prefix.'.title')->label(__('admin_pages.about.title'))->required()->maxLength(180),
                TextInput::make($prefix.'.headline')->label(__('admin_pages.about.headline'))->required()->maxLength(255),
                Textarea::make($prefix.'.summary')->label(__('admin_pages.about.summary'))->required()->rows(2)->columnSpanFull(),
                TextInput::make($prefix.'.badge')->label(__('admin_pages.about.badge'))->required()->maxLength(120),
                Textarea::make($prefix.'.quote')->label(__('admin_pages.about.quote'))->required()->rows(3)->columnSpanFull(),
                Textarea::make($prefix.'.description')->label(__('admin_pages.about.description'))->required()->rows(3)->columnSpanFull(),
                MediaPicker::image($prefix.'.imagePrimary', __('admin_pages.about.primary_image'), true),
                MediaPicker::image($prefix.'.imageSecondary', __('admin_pages.about.secondary_image'), true),
                MediaPicker::image($prefix.'.imageOverview', __('admin_pages.about.overview_image'), true),
                TextInput::make($prefix.'.seoTitle')->label(__('admin_pages.about.seo_title'))->required()->maxLength(180),
                Textarea::make($prefix.'.seoDescription')->label(__('admin_pages.about.seo_description'))->required()->rows(2)->columnSpanFull(),
                MediaPicker::image($prefix.'.seoImage', __('admin_pages.about.social_image'), true),
            ])->columns(2),

            Section::make(__('admin_pages.about.stats'))->schema([
                Repeater::make($prefix.'.stats')
                    ->label(__('admin_pages.about.stats'))
                    ->schema([
                        TextInput::make('value')->required()->maxLength(40),
                        TextInput::make('label')->required()->maxLength(120),
                        MediaPicker::icon('icon', __('admin_pages.about.icon')),
                    ])
                    ->columns(3)
                    ->defaultItems(0)
                    ->reorderable()
                    ->collapsible()
                    ->columnSpanFull(),
            ]),

            Section::make(__('admin_pages.about.story_items'))->schema([
                Repeater::make($prefix.'.storyItems')
                    ->label(__('admin_pages.about.story_items'))
                    ->schema([
                        TextInput::make('title')->required()->maxLength(160),
                        Textarea::make('summary')->required()->rows(2)->columnSpanFull(),
                        Textarea::make('summary')->required()->rows(2)->columnSpanFull(),
                    ])
                    ->columns(2)
                    ->defaultItems(0)
                    ->reorderable()
                    ->collapsible()
                    ->columnSpanFull(),
            ]),

            Section::make(__('admin_pages.about.highlights'))->schema([
                Repeater::make($prefix.'.highlights')
                    ->label(__('admin_pages.about.highlights'))
                    ->schema([
                        TextInput::make('title')->required()->maxLength(160),
                    ])
                    ->defaultItems(0)
                    ->reorderable()
                    ->collapsible()
                    ->columnSpanFull(),
            ]),
        ];
    }

    /** @return array<int, Section> */
    private function visionMissionFields(string $locale): array
    {
        $prefix = $locale.'_vision_mission';

        return [
            Section::make(__('admin_pages.about.hero_and_seo'))->schema([
                TextInput::make($prefix.'.title')->label(__('admin_pages.about.page_title'))->required()->maxLength(180),
                Textarea::make($prefix.'.summary')->label(__('admin_pages.about.summary'))->required()->rows(3)->columnSpanFull(),
                MediaPicker::image($prefix.'.heroImage', __('admin_pages.about.hero_image'), true),
                TextInput::make($prefix.'.seoTitle')->label(__('admin_pages.about.seo_title'))->required()->maxLength(180),
                Textarea::make($prefix.'.seoDescription')->label(__('admin_pages.about.seo_description'))->required()->rows(2)->columnSpanFull(),
                MediaPicker::image($prefix.'.seoImage', __('admin_pages.about.social_image'), true),
            ])->columns(2),

            Section::make(__('admin_pages.about.vision_mission_and_values'))->schema([
                TextInput::make($prefix.'.cardsTitle')->label(__('admin_pages.about.accessible_section_title'))->required()->maxLength(180),
                Repeater::make($prefix.'.cards')
                    ->label(__('admin_pages.about.cards'))
                    ->schema([
                        MediaPicker::icon('icon', __('admin_pages.about.icon'), true),
                        TextInput::make('title')->required()->maxLength(180),
                        Textarea::make('body')
                            ->required()
                            ->rows(8)
                            ->helperText(__('admin_pages.about.for_numbered_objectives_put_each_objective_on_a_separate_lin'))
                            ->columnSpanFull(),
                    ])
                    ->columns(2)
                    ->minItems(1)
                    ->defaultItems(0)
                    ->reorderable()
                    ->collapsible()
                    ->columnSpanFull(),
            ]),

            Section::make(__('admin_pages.about.strategic_pillars'))->schema([
                TextInput::make($prefix.'.pillarsTitle')->label(__('admin_pages.about.section_title'))->required()->maxLength(180),
                Repeater::make($prefix.'.pillars')
                    ->label(__('admin_pages.about.pillars'))
                    ->schema([
                        TextInput::make('title')->required()->maxLength(180),
                        Textarea::make('summary')->required()->rows(3)->columnSpanFull(),
                    ])
                    ->columns(2)
                    ->minItems(1)
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

        $fields = [
            Section::make(__('admin_pages.about.hero'))->schema([
                TextInput::make($prefix.'.title')->label(__('admin_pages.about.page_title'))->required()->maxLength(180),
                TextInput::make($prefix.'.headline')->label(__('admin_pages.about.headline'))->required()->maxLength(255),
                Textarea::make($prefix.'.summary')->label(__('admin_pages.about.summary'))->required()->rows(2)->columnSpanFull(),
                MediaPicker::image($prefix.'.heroImage', __('admin_pages.about.hero_image'), true),
                TextInput::make($prefix.'.seoTitle')->label(__('admin_pages.about.seo_title'))->required()->maxLength(180),
                Textarea::make($prefix.'.seoDescription')->label(__('admin_pages.about.seo_description'))->required()->rows(2)->columnSpanFull(),
                MediaPicker::image($prefix.'.seoImage', __('admin_pages.about.social_image'), true),
            ])->columns(2),
        ];

        if ($stateKey === 'partnerships') {
            $fields[] = Section::make(__('admin_pages.about.partnership_goals'))->schema([
                Repeater::make($prefix.'.sections')
                    ->label(__('admin_pages.about.goals'))
                    ->schema([
                        MediaPicker::icon('icon', __('admin_pages.about.icon')),
                        TextInput::make('title')->required()->maxLength(180),
                        Textarea::make('body')->required()->rows(3)->columnSpanFull(),
                    ])
                    ->columns(2)
                    ->defaultItems(0)
                    ->reorderable()
                    ->collapsible()
                    ->columnSpanFull(),
            ]);
        }

        if ($stateKey === 'leadership') {
            $fields[] = Section::make(__('admin_pages.about.leadership_presentation'))->schema([
                Repeater::make($prefix.'.sections')
                    ->label(__('admin_pages.about.presentation_copy'))
                    ->schema([
                        Select::make('key')->label(__('admin_pages.about.content_key'))->options([
                            'rector_quote' => __('admin_pages.about.rector_quote'),
                            'vice_presidents_title' => __('admin_pages.about.vice_presidents_heading'),
                            'deans_title' => __('admin_pages.about.faculty_deans_heading'),
                        ])->required(),
                        TextInput::make('title')->label(__('admin_pages.about.title'))->maxLength(180),
                        Textarea::make('body')->label(__('admin_pages.about.body'))->rows(3)->columnSpanFull(),
                    ])
                    ->columns(2)
                    ->defaultItems(0)
                    ->reorderable()
                    ->collapsible()
                    ->columnSpanFull(),
            ]);
        }

        return $fields;
    }

    /** @return array<int, Section> */
    private function orgChartFields(string $prefix): array
    {
        $url = static fn (): TextInput => TextInput::make('url')->label(__('admin_pages.about.chart_url'))->maxLength(255)->rule('regex:#^(/|https://)#');

        return [
            Section::make(__('admin_pages.about.chart'))->description(__('admin_pages.about.chart_help'))->schema([
                Repeater::make($prefix.'.chart.apex')->label(__('admin_pages.about.chart_apex'))
                    ->schema([TextInput::make('title')->required()->maxLength(255), $url()])
                    ->columns(2)->defaultItems(0)->reorderable()->collapsible()->columnSpanFull(),
                Repeater::make($prefix.'.chart.offices')->label(__('admin_pages.about.chart_offices'))
                    ->schema([TextInput::make('title')->required()->maxLength(255), $url()])
                    ->columns(2)->defaultItems(0)->reorderable()->collapsible()->columnSpanFull(),
                Repeater::make($prefix.'.chart.branches')->label(__('admin_pages.about.chart_branches'))
                    ->schema([
                        TextInput::make('title')->required()->maxLength(255),
                        $url(),
                        TextInput::make('note')->label(__('admin_pages.about.chart_note'))->maxLength(255),
                        TextInput::make('noteUrl')->label(__('admin_pages.about.chart_url'))->maxLength(255)->rule('regex:#^(/|https://)#'),
                        Repeater::make('units')->label(__('admin_pages.about.chart_units'))
                            ->schema([
                                TextInput::make('title')->required()->maxLength(255),
                                $url(),
                                Repeater::make('children')->label(__('admin_pages.about.chart_children'))
                                    ->schema([TextInput::make('title')->required()->maxLength(255), $url()])
                                    ->columns(2)->defaultItems(0)->reorderable()->collapsible()->columnSpanFull(),
                            ])
                            ->columns(2)->defaultItems(0)->reorderable()->collapsible()->columnSpanFull(),
                    ])
                    ->columns(2)->defaultItems(0)->reorderable()->collapsible()->columnSpanFull(),
            ]),
        ];
    }

    /** @return array<int, Section> */
    private function importedContentFields(string $locale, string $stateKey): array
    {
        $prefix = $locale.'_'.$stateKey;

        return [
            ...($stateKey === 'organizational_structure' ? $this->orgChartFields($prefix) : []),
            Section::make(__('admin_pages.about.hero'))->schema([
                TextInput::make($prefix.'.title')->label(__('admin_pages.about.page_title'))->required()->maxLength(180),
                TextInput::make($prefix.'.headline')->label(__('admin_pages.about.headline'))->required()->maxLength(255),
                Textarea::make($prefix.'.summary')->label(__('admin_pages.about.summary'))->required()->rows(2)->columnSpanFull(),
                MediaPicker::image($prefix.'.heroImage', __('admin_pages.about.hero_image'), true),
                TextInput::make($prefix.'.badge')->label(__('admin_pages.about.eyebrow_badge'))->required()->maxLength(120),
                TextInput::make($prefix.'.seoTitle')->label(__('admin_pages.about.seo_title'))->required()->maxLength(180),
                Textarea::make($prefix.'.seoDescription')->label(__('admin_pages.about.seo_description'))->required()->rows(2)->columnSpanFull(),
                MediaPicker::image($prefix.'.seoImage', __('admin_pages.about.social_image'), true),
            ])->columns(2),

            Section::make(__('admin_pages.about.introduction'))->schema([
                Repeater::make($prefix.'.intro')
                    ->schema([Textarea::make('paragraph')->required()->rows(3)->columnSpanFull()])
                    ->defaultItems(0)->reorderable()->collapsible()->columnSpanFull(),
            ]),

            Section::make(__('admin_pages.about.facts'))->schema([
                Repeater::make($prefix.'.stats')
                    ->schema([
                        TextInput::make('value')->required()->maxLength(40),
                        TextInput::make('label')->required()->maxLength(120),
                    ])
                    ->columns(2)->defaultItems(0)->reorderable()->collapsible()->columnSpanFull(),
            ]),

            Section::make(__('admin_pages.about.content_cards'))->schema([
                Repeater::make($prefix.'.sections')
                    ->label(__('admin_pages.about.cards'))
                    ->schema([
                        TextInput::make('title')->maxLength(255),
                        Textarea::make('body')->rows(3)->columnSpanFull(),
                    ])
                    ->columns(2)
                    ->defaultItems(0)
                    ->reorderable()
                    ->collapsible()
                    ->columnSpanFull(),
                Textarea::make($prefix.'.closing')->label(__('admin_pages.about.closing'))->rows(3)->columnSpanFull(),
            ]),

            Section::make(__('admin_pages.about.documents'))->schema([
                Repeater::make($prefix.'.documents')
                    ->label(__('admin_pages.about.documents'))
                    ->schema([
                        TextInput::make('label')->label(__('admin_pages.about.document_label'))->maxLength(180),
                        TextInput::make('url')->label(__('admin_pages.about.document_url'))->required()->maxLength(500)
                            ->rule('regex:#^(/|https://)#'),
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
            Section::make(__('admin_pages.about.hero'))->schema([
                TextInput::make($prefix.'.title')->label(__('admin_pages.about.page_title'))->required()->maxLength(180),
                TextInput::make($prefix.'.headline')->label(__('admin_pages.about.headline'))->required()->maxLength(255),
                Textarea::make($prefix.'.summary')->label(__('admin_pages.about.summary'))->required()->rows(2)->columnSpanFull(),
                MediaPicker::image($prefix.'.heroImage', __('admin_pages.about.hero_image'), true),
                MediaPicker::image($prefix.'.contentImage', __('admin_pages.about.founding_image'), true),
                TextInput::make($prefix.'.seoTitle')->label(__('admin_pages.about.seo_title'))->required()->maxLength(180),
                Textarea::make($prefix.'.seoDescription')->label(__('admin_pages.about.seo_description'))->required()->rows(2)->columnSpanFull(),
                MediaPicker::image($prefix.'.seoImage', __('admin_pages.about.social_image'), true),
            ])->columns(2),

            Section::make(__('admin_pages.about.founding_vision'))->schema([
                TextInput::make($prefix.'.foundingTitle')->label(__('admin_pages.about.section_title'))->required()->maxLength(180),
                Textarea::make($prefix.'.quote')->label(__('admin_pages.about.quote'))->required()->rows(3)->columnSpanFull(),
                Repeater::make($prefix.'.body')
                    ->label(__('admin_pages.about.body_paragraphs'))
                    ->schema([
                        Textarea::make('paragraph')->required()->rows(3)->columnSpanFull(),
                    ])
                    ->defaultItems(0)
                    ->reorderable()
                    ->collapsible()
                    ->columnSpanFull(),
            ]),

            Section::make(__('admin_pages.about.institutional_timeline'))->schema([
                TextInput::make($prefix.'.timelineTitle')->label(__('admin_pages.about.timeline_title'))->required()->maxLength(180),
                Repeater::make($prefix.'.timeline')
                    ->label(__('admin_pages.about.timeline_items'))
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

            Section::make(__('admin_pages.about.narratives'))->schema([
                Repeater::make($prefix.'.narratives')
                    ->label(__('admin_pages.about.narrative_rows'))
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

            Section::make(__('admin_pages.about.legacy'))->schema([
                TextInput::make($prefix.'.legacyTitle')->label(__('admin_pages.about.legacy_title'))->required()->maxLength(180),
                Textarea::make($prefix.'.legacyBody')->label(__('admin_pages.about.legacy_body'))->required()->rows(3)->columnSpanFull(),
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
            'imageOverview' => $this->stringValue($payload, 'imageOverview'),
            'seoTitle' => $this->stringValue($payload, 'seoTitle') ?: $this->stringValue($payload, 'title'),
            'seoDescription' => $this->stringValue($payload, 'seoDescription') ?: $this->stringValue($payload, 'summary'),
            'seoImage' => $this->stringValue($payload, 'seoImage') ?: $this->stringValue($payload, 'imagePrimary'),
            'stats' => $this->listValue($payload, 'stats'),
            'storyItems' => $this->listValue($payload, 'storyItems'),
            'highlights' => $this->listValue($payload, 'highlights'),
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
            'imageOverview' => (string) ($data['imageOverview'] ?? ''),
            'seoTitle' => (string) ($data['seoTitle'] ?? ''),
            'seoDescription' => (string) ($data['seoDescription'] ?? ''),
            'seoImage' => (string) ($data['seoImage'] ?? ''),
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
                ->map(static fn (array $item): array => [
                    'title' => (string) ($item['title'] ?? ''),
                    'summary' => (string) ($item['summary'] ?? ''),
                ])
                ->values()
                ->all(),
        ];
    }

    /** @return array<string, mixed> */
    private function visionMissionFormData(array $payload): array
    {
        $sections = is_array($payload['sections'] ?? null) ? $payload['sections'] : [];

        return [
            'title' => $this->stringValue($payload, 'title'),
            'summary' => $this->stringValue($payload, 'summary'),
            'heroImage' => $this->stringValue($payload, 'heroImage'),
            'seoTitle' => $this->stringValue($payload, 'seoTitle') ?: $this->stringValue($payload, 'title'),
            'seoDescription' => $this->stringValue($payload, 'seoDescription') ?: $this->stringValue($payload, 'summary'),
            'seoImage' => $this->stringValue($payload, 'seoImage') ?: $this->stringValue($payload, 'heroImage'),
            'cardsTitle' => $this->stringValue($sections, 'cardsTitle'),
            'cards' => $this->listValue($sections, 'cards'),
            'pillarsTitle' => $this->stringValue($sections, 'pillarsTitle'),
            'pillars' => $this->listValue($sections, 'pillars'),
        ];
    }

    /** @return array<string, mixed> */
    private function visionMissionPayloadFromForm(array $data): array
    {
        return [
            'title' => (string) ($data['title'] ?? ''),
            'headline' => (string) ($data['title'] ?? ''),
            'summary' => (string) ($data['summary'] ?? ''),
            'heroImage' => (string) ($data['heroImage'] ?? ''),
            'seoTitle' => (string) ($data['seoTitle'] ?? ''),
            'seoDescription' => (string) ($data['seoDescription'] ?? ''),
            'seoImage' => (string) ($data['seoImage'] ?? ''),
            'sections' => [
                'cardsTitle' => (string) ($data['cardsTitle'] ?? ''),
                'cards' => collect($this->listValue($data, 'cards'))
                    ->map(static fn (array $card): array => [
                        'icon' => (string) ($card['icon'] ?? ''),
                        'title' => (string) ($card['title'] ?? ''),
                        'body' => (string) ($card['body'] ?? ''),
                    ])
                    ->values()
                    ->all(),
                'pillarsTitle' => (string) ($data['pillarsTitle'] ?? ''),
                'pillars' => collect($this->listValue($data, 'pillars'))
                    ->map(static fn (array $pillar): array => [
                        'title' => (string) ($pillar['title'] ?? ''),
                        'summary' => (string) ($pillar['summary'] ?? ''),
                    ])
                    ->values()
                    ->all(),
            ],
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
            'contentImage' => $this->stringValue($payload, 'contentImage') ?: '/images/uni-main-place.JPG',
            'seoTitle' => $this->stringValue($payload, 'seoTitle') ?: $this->stringValue($payload, 'title'),
            'seoDescription' => $this->stringValue($payload, 'seoDescription') ?: $this->stringValue($payload, 'summary'),
            'seoImage' => $this->stringValue($payload, 'seoImage') ?: $this->stringValue($payload, 'heroImage'),
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
            'contentImage' => (string) ($data['contentImage'] ?? ''),
            'seoTitle' => (string) ($data['seoTitle'] ?? ''),
            'seoDescription' => (string) ($data['seoDescription'] ?? ''),
            'seoImage' => (string) ($data['seoImage'] ?? ''),
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
            'seoTitle' => $this->stringValue($payload, 'seoTitle') ?: $this->stringValue($payload, 'title'),
            'seoDescription' => $this->stringValue($payload, 'seoDescription') ?: $this->stringValue($payload, 'summary'),
            'seoImage' => $this->stringValue($payload, 'seoImage') ?: $this->stringValue($payload, 'heroImage'),
            'sections' => $this->listValue($payload, 'sections'),
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
            'seoTitle' => (string) ($data['seoTitle'] ?? ''),
            'seoDescription' => (string) ($data['seoDescription'] ?? ''),
            'seoImage' => (string) ($data['seoImage'] ?? ''),
            'sections' => collect($this->listValue($data, 'sections'))
                ->map(static fn (array $section): array => [
                    'key' => (string) ($section['key'] ?? ''),
                    'icon' => (string) ($section['icon'] ?? ''),
                    'title' => (string) ($section['title'] ?? ''),
                    'body' => (string) ($section['body'] ?? ''),
                ])
                ->values()
                ->all(),
        ];
    }

    /** @return array<string, mixed> */
    private function importedContentFormData(array $payload): array
    {
        return [
            ...$this->contentShellFormData($payload),
            'badge' => $this->stringValue($payload, 'badge'),
            'intro' => collect(is_array($payload['intro'] ?? null) ? $payload['intro'] : [])->map(static fn (mixed $paragraph): array => [
                'paragraph' => is_string($paragraph) ? $paragraph : (is_array($paragraph) && is_string($paragraph['value'] ?? null) ? $paragraph['value'] : ''),
            ])->filter(static fn (array $item): bool => $item['paragraph'] !== '')->values()->all(),
            'stats' => $this->listValue($payload, 'stats'),
            'seoTitle' => $this->stringValue($payload, 'seoTitle') ?: $this->stringValue($payload, 'title'),
            'seoDescription' => $this->stringValue($payload, 'seoDescription') ?: $this->stringValue($payload, 'summary'),
            'seoImage' => $this->stringValue($payload, 'seoImage') ?: $this->stringValue($payload, 'heroImage'),
            'sections' => $this->listValue($payload, 'sections'),
            'closing' => $this->stringValue($payload, 'closing'),
            'documents' => $this->listValue($payload, 'documents'),
            'chart' => is_array($payload['chart'] ?? null) ? $payload['chart'] : [],
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
            'badge' => (string) ($data['badge'] ?? ''),
            'intro' => collect($this->listValue($data, 'intro'))->map(static fn (array $item): string => (string) ($item['paragraph'] ?? ''))->filter()->values()->all(),
            'stats' => collect($this->listValue($data, 'stats'))->map(static fn (array $stat): array => ['value' => (string) ($stat['value'] ?? ''), 'label' => (string) ($stat['label'] ?? '')])->values()->all(),
            'seoTitle' => (string) ($data['seoTitle'] ?? ''),
            'seoDescription' => (string) ($data['seoDescription'] ?? ''),
            'seoImage' => (string) ($data['seoImage'] ?? ''),
            'sections' => collect($this->listValue($data, 'sections'))
                ->map(static fn (array $section): array => [
                    'title' => (string) ($section['title'] ?? ''),
                    'body' => (string) ($section['body'] ?? ''),
                ])
                ->filter(static fn (array $section): bool => $section['title'] !== '' || $section['body'] !== '')
                ->values()
                ->all(),
            'closing' => (string) ($data['closing'] ?? ''),
            'documents' => collect($this->listValue($data, 'documents'))
                ->map(static fn (array $document): array => ['label' => (string) ($document['label'] ?? ''), 'url' => (string) ($document['url'] ?? '')])
                ->filter(static fn (array $document): bool => $document['url'] !== '')
                ->values()
                ->all(),
            ...(is_array($data['chart'] ?? null) && $data['chart'] !== [] ? ['chart' => $this->chartFromForm($data['chart'])] : []),
        ];
    }

    /**
     * Repeaters key their items by uuid; the stored chart keeps plain lists.
     *
     * @param  array<string, mixed>  $chart
     * @return array<string, mixed>
     */
    private function chartFromForm(array $chart): array
    {
        $box = function (mixed $node) use (&$box): array {
            $node = is_array($node) ? $node : [];

            return [
                'title' => (string) ($node['title'] ?? ''),
                'url' => (string) ($node['url'] ?? ''),
                'children' => array_values(array_map($box, is_array($node['children'] ?? null) ? $node['children'] : [])),
            ];
        };

        return [
            'apex' => array_values(array_map($box, $this->listValue($chart, 'apex'))),
            'offices' => array_values(array_map($box, $this->listValue($chart, 'offices'))),
            'branches' => array_values(array_map(fn (array $branch): array => [
                'title' => (string) ($branch['title'] ?? ''),
                'url' => (string) ($branch['url'] ?? ''),
                'note' => (string) ($branch['note'] ?? ''),
                'noteUrl' => (string) ($branch['noteUrl'] ?? ''),
                'units' => array_values(array_map($box, $this->listValue($branch, 'units'))),
            ], $this->listValue($chart, 'branches'))),
        ];
    }

    private function importedContentStateKey(string $targetKey): string
    {
        return match ($targetKey) {
            'about.quality-policy' => 'quality_policy',
            'about.ethical-charter' => 'ethical_charter',
            'about.organizational-structure' => 'organizational_structure',
            'about.accreditation' => 'accreditation',
            'about.why-spu' => 'why_spu',
            default => throw new \InvalidArgumentException(__('admin_pages.about.unsupported_about_content_target')),
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
