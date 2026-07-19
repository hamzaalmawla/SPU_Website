<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Contracts\Cms\CmsWorkflowServiceInterface;
use App\Contracts\Research\ResearchPageServiceInterface;
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
use Filament\Forms\Components\Toggle;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class ManageResearch extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-academic-cap';

    protected static ?string $slug = 'manage-research';

    protected static ?int $navigationSort = 1;

    protected static string $view = 'filament.pages.manage-research';

    /** @var array<string, mixed> */
    public ?array $data = [];

    public ?int $draftVersion = null;

    private ResearchPageServiceInterface $researchPageService;

    private CmsWorkflowServiceInterface $cmsWorkflowService;

    public function boot(
        ResearchPageServiceInterface $researchPageService,
        CmsWorkflowServiceInterface $cmsWorkflowService,
    ): void {
        $this->researchPageService = $researchPageService;
        $this->cmsWorkflowService = $cmsWorkflowService;
    }

    public static function canAccess(): bool
    {
        return Gate::allows('manage-pages');
    }

    public static function getNavigationGroup(): ?string
    {
        return __('admin.navigation.groups.research');
    }

    public static function getNavigationLabel(): string
    {
        return __('admin.navigation.items.research');
    }

    public function getTitle(): string
    {
        return __('admin.pages.manage_research');
    }

    public function mount(): void
    {
        $this->loadTarget('research.publications');
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make('Research Target')->schema([
                    Select::make('target_key')
                        ->label('Page / Content Type')
                        ->options($this->targetOptions())
                        ->required()
                        ->live()
                        ->afterStateUpdated(fn (?string $state): mixed => is_string($state) && $state !== '' ? $this->loadTarget($state) : null),
                ]),
                Tabs::make('research_publications_locales')
                    ->tabs([
                        Tab::make('Arabic')->schema([...$this->landingFields('ar'), ...$this->publicationFields('ar'), ...$this->centerFields('ar'), ...$this->projectFields('ar'), ...$this->themeFields('ar'), ...$this->expertFields('ar'), ...$this->conferenceFields('ar'), ...$this->libraryFields('ar'), ...$this->officeFields('ar'), ...$this->policyFields('ar')]),
                        Tab::make('English')->schema([...$this->landingFields('en'), ...$this->publicationFields('en'), ...$this->centerFields('en'), ...$this->projectFields('en'), ...$this->themeFields('en'), ...$this->expertFields('en'), ...$this->conferenceFields('en'), ...$this->libraryFields('en'), ...$this->officeFields('en'), ...$this->policyFields('en')]),
                    ])
                    ->persistTabInQueryString('locale')
                    ->columnSpanFull(),
            ])
            ->statePath('data');
    }

    public function loadTarget(string $targetKey): void
    {
        $this->assertResearchTarget($targetKey);

        $draftPayload = $this->cmsWorkflowService->latestEditableDraftPayload($targetKey, (int) auth()->id());
        $payload = is_array($draftPayload) ? $draftPayload : $this->researchPageService->getEditablePayload($targetKey);
        $this->draftVersion = $this->cmsWorkflowService->latestEditableDraftVersion($targetKey, (int) auth()->id());

        $this->form->fill([
            'target_key' => $targetKey,
            'ar_landing' => $targetKey === 'research.index' && is_array($payload['translations']['ar'] ?? null) ? $payload['translations']['ar'] : [],
            'en_landing' => $targetKey === 'research.index' && is_array($payload['translations']['en'] ?? null) ? $payload['translations']['en'] : [],
            'ar_publications' => $targetKey === 'research.publications' && is_array($payload['translations']['ar'] ?? null) ? $payload['translations']['ar'] : [],
            'en_publications' => $targetKey === 'research.publications' && is_array($payload['translations']['en'] ?? null) ? $payload['translations']['en'] : [],
            'ar_centers' => $targetKey === 'research.centers' && is_array($payload['translations']['ar'] ?? null) ? $payload['translations']['ar'] : [],
            'en_centers' => $targetKey === 'research.centers' && is_array($payload['translations']['en'] ?? null) ? $payload['translations']['en'] : [],
            'ar_projects' => $targetKey === 'research.projects' && is_array($payload['translations']['ar'] ?? null) ? $payload['translations']['ar'] : [],
            'en_projects' => $targetKey === 'research.projects' && is_array($payload['translations']['en'] ?? null) ? $payload['translations']['en'] : [],
            'ar_themes' => $targetKey === 'research.themes' && is_array($payload['translations']['ar'] ?? null) ? $payload['translations']['ar'] : [],
            'en_themes' => $targetKey === 'research.themes' && is_array($payload['translations']['en'] ?? null) ? $payload['translations']['en'] : [],
            'ar_experts' => $targetKey === 'research.experts' && is_array($payload['translations']['ar'] ?? null) ? $payload['translations']['ar'] : [],
            'en_experts' => $targetKey === 'research.experts' && is_array($payload['translations']['en'] ?? null) ? $payload['translations']['en'] : [],
            'ar_conferences' => $targetKey === 'research.conferences' && is_array($payload['translations']['ar'] ?? null) ? $payload['translations']['ar'] : [],
            'en_conferences' => $targetKey === 'research.conferences' && is_array($payload['translations']['en'] ?? null) ? $payload['translations']['en'] : [],
            'ar_library' => $targetKey === 'research.library' && is_array($payload['translations']['ar'] ?? null) ? $payload['translations']['ar'] : [],
            'en_library' => $targetKey === 'research.library' && is_array($payload['translations']['en'] ?? null) ? $payload['translations']['en'] : [],
            'ar_office' => $targetKey === 'research.office' && is_array($payload['translations']['ar'] ?? null) ? $payload['translations']['ar'] : [],
            'en_office' => $targetKey === 'research.office' && is_array($payload['translations']['en'] ?? null) ? $payload['translations']['en'] : [],
            'ar_policies' => $targetKey === 'research.policies' && is_array($payload['translations']['ar'] ?? null) ? $payload['translations']['ar'] : [],
            'en_policies' => $targetKey === 'research.policies' && is_array($payload['translations']['en'] ?? null) ? $payload['translations']['en'] : [],
        ]);
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

    public function save(): void
    {
        /** @var User $user */
        $user = auth()->user();

        try {
            $targetKey = $this->currentTargetKey();
            $draft = $this->cmsWorkflowService->saveDraft($targetKey, $this->payloadFromForm($this->currentFormData()), (int) $user->id, $this->draftVersion);
            $this->draftVersion = $draft->version;

            Notification::make()->title('Research draft saved')->success()->send();
        } catch (ConflictException $e) {
            $this->draftVersion = $e->currentVersion;
            Notification::make()->title('Draft conflict detected')->body('Reload this research target before saving again.')->danger()->persistent()->send();
        } catch (\Throwable $e) {
            report($e);
            Notification::make()->title('Failed to save research draft')->body($e->getMessage())->danger()->send();
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
            Notification::make()->title('Draft conflict detected')->body('Reload this research target before previewing again.')->danger()->persistent()->send();
        } catch (\Throwable $e) {
            report($e);
            Notification::make()->title('Failed to create research preview')->body($e->getMessage())->danger()->send();
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

            Notification::make()->title('Research content published')->success()->send();
        } catch (ValidationException $e) {
            Notification::make()->title('Publish failed')->body($this->formatValidationErrors($e->errors()))->danger()->persistent()->send();
        } catch (\Throwable $e) {
            report($e);
            Notification::make()->title('Failed to publish research content')->body($e->getMessage())->danger()->send();
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

            Notification::make()->title('Research content scheduled')->success()->send();
        } catch (ValidationException $e) {
            Notification::make()->title('Schedule failed')->body($this->formatValidationErrors($e->errors()))->danger()->persistent()->send();
        } catch (\Throwable $e) {
            report($e);
            Notification::make()->title('Failed to schedule research content')->body($e->getMessage())->danger()->send();
        }
    }

    public function unpublish(): void
    {
        /** @var User $user */
        $user = auth()->user();
        $result = $this->cmsWorkflowService->unpublish($this->currentTargetKey(), (int) $user->id);
        $notification = Notification::make()->title($result ? 'Research content unpublished' : 'No published research content found');

        ($result ? $notification->success() : $notification->warning())->send();
    }

    /** @return array<string, string> */
    private function targetOptions(): array
    {
        return [
            'research.index' => __('admin.cms.targets.research.index'),
            'research.publications' => __('admin.cms.targets.research.publications'),
            'research.centers' => __('admin.cms.targets.research.centers'),
            'research.projects' => __('admin.cms.targets.research.projects'),
            'research.themes' => __('admin.cms.targets.research.themes'),
            'research.experts' => __('admin.cms.targets.research.experts'),
            'research.conferences' => __('admin.cms.targets.research.conferences'),
            'research.library' => __('admin.cms.targets.research.library'),
            'research.office' => __('admin.cms.targets.research.office'),
            'research.policies' => __('admin.cms.targets.research.policies'),
        ];
    }

    private function currentTargetKey(): string
    {
        $targetKey = (string) ($this->data['target_key'] ?? 'research.publications');
        $this->assertResearchTarget($targetKey);

        return $targetKey;
    }

    private function assertResearchTarget(string $targetKey): void
    {
        if (! in_array($targetKey, ['research.index', 'research.publications', 'research.centers', 'research.projects', 'research.themes', 'research.experts', 'research.conferences', 'research.library', 'research.office', 'research.policies'], true)) {
            throw new \InvalidArgumentException('Unsupported research target.');
        }
    }

    /** @return array<int, Section> */
    private function landingFields(string $locale): array
    {
        $prefix = $locale.'_landing';
        $sections = [
            Section::make('Landing Hero')->schema([
                TextInput::make($prefix.'.hero.eyebrow')->label('Eyebrow')->required()->maxLength(160),
                TextInput::make($prefix.'.hero.title')->label('Title')->required()->maxLength(180),
                MediaPicker::image($prefix.'.hero.backgroundImage', 'Background Image', true),
                Textarea::make($prefix.'.hero.summary')->label('Summary')->required()->rows(2)->columnSpanFull(),
                TextInput::make($prefix.'.hero.cta1')->label('Primary CTA')->required()->maxLength(120),
                TextInput::make($prefix.'.hero.cta1Url')->label('Primary CTA URL')->required()->maxLength(255),
                TextInput::make($prefix.'.hero.cta2')->label('Secondary CTA')->required()->maxLength(120),
                TextInput::make($prefix.'.hero.cta2Url')->label('Secondary CTA URL')->required()->maxLength(255),
            ])->columns(2),
            Section::make('Landing Stats')->schema([
                Repeater::make($prefix.'.stats')
                    ->schema([
                        TextInput::make('value')->required()->maxLength(60),
                        TextInput::make('label')->required()->maxLength(140),
                    ])
                    ->columns(2)
                    ->defaultItems(0)
                    ->reorderable()
                    ->collapsible()
                    ->columnSpanFull(),
            ]),
            Section::make('Featured Publication')->schema([
                TextInput::make($prefix.'.featuredPublication.sectionTitle')->required()->maxLength(160),
                TextInput::make($prefix.'.featuredPublication.eyebrow')->required()->maxLength(160),
                TextInput::make($prefix.'.featuredPublication.title')->required()->maxLength(240)->columnSpanFull(),
                Textarea::make($prefix.'.featuredPublication.summary')->required()->rows(2)->columnSpanFull(),
                TextInput::make($prefix.'.featuredPublication.slug')->required()->maxLength(160),
                MediaPicker::image($prefix.'.featuredPublication.image', 'Featured Image', true),
                TextInput::make($prefix.'.featuredPublication.authorLabel')->required()->maxLength(80),
                TextInput::make($prefix.'.featuredPublication.authorName')->required()->maxLength(160),
                TextInput::make($prefix.'.featuredPublication.affiliationLabel')->required()->maxLength(80),
                TextInput::make($prefix.'.featuredPublication.affiliation')->required()->maxLength(160),
                TextInput::make($prefix.'.featuredPublication.publishedLabel')->required()->maxLength(80),
                TextInput::make($prefix.'.featuredPublication.date')->required()->maxLength(120),
                TextInput::make($prefix.'.featuredPublication.viewCta')->required()->maxLength(120),
                TextInput::make($prefix.'.featuredPublication.downloadCta')->required()->maxLength(120),
                TextInput::make($prefix.'.featuredPublication.doiLabel')->required()->maxLength(120),
                TextInput::make($prefix.'.featuredPublication.doi')->maxLength(180),
            ])->columns(2),
            Section::make('Research Gateway')->schema([
                TextInput::make($prefix.'.gateway.sectionTitle')->required()->maxLength(180),
                Repeater::make($prefix.'.gateway.cards')
                    ->schema([
                        TextInput::make('number')->required()->maxLength(20),
                        TextInput::make('title')->required()->maxLength(160),
                        Textarea::make('summary')->required()->rows(2)->columnSpanFull(),
                        TextInput::make('cta')->required()->maxLength(120),
                        TextInput::make('url')->required()->maxLength(255),
                    ])
                    ->columns(2)
                    ->defaultItems(0)
                    ->reorderable()
                    ->collapsible()
                    ->columnSpanFull(),
            ]),
        ];

        return array_map(
            static fn (Section $section): Section => $section->visible(static fn (Get $get): bool => $get('target_key') === 'research.index'),
            $sections,
        );
    }

    /** @return array<int, Section> */
    private function publicationFields(string $locale): array
    {
        $prefix = $locale.'_publications';

        $sections = [
            Section::make('Hero')->schema([
                TextInput::make($prefix.'.hero.eyebrow')->label('Eyebrow')->required()->maxLength(160),
                TextInput::make($prefix.'.hero.title')->label('Title')->required()->maxLength(180),
                MediaPicker::image($prefix.'.hero.backgroundImage', 'Background Image', true),
                Textarea::make($prefix.'.hero.summary')->label('Summary')->required()->rows(2)->columnSpanFull(),
            ])->columns(2),

            Section::make('Filters')->schema([
                TextInput::make($prefix.'.filters.facultyLabel')->required()->maxLength(80),
                TextInput::make($prefix.'.filters.typeLabel')->required()->maxLength(80),
                TextInput::make($prefix.'.filters.yearLabel')->required()->maxLength(80),
                TextInput::make($prefix.'.filters.searchPlaceholder')->required()->maxLength(160),
                Repeater::make($prefix.'.filters.faculties')->schema($this->optionFields())->columns(2)->defaultItems(0)->reorderable()->collapsible()->columnSpanFull(),
                Repeater::make($prefix.'.filters.publicationTypes')->schema($this->optionFields())->columns(2)->defaultItems(0)->reorderable()->collapsible()->columnSpanFull(),
                Repeater::make($prefix.'.filters.years')->schema($this->optionFields())->columns(2)->defaultItems(0)->reorderable()->collapsible()->columnSpanFull(),
            ])->columns(2),

            Section::make('Publication Items')->schema([
                Repeater::make($prefix.'.items')
                    ->schema($this->publicationItemFields())
                    ->defaultItems(0)
                    ->reorderable()
                    ->cloneable()
                    ->collapsible()
                    ->itemLabel(fn (array $state): ?string => $state['title'] ?? $state['slug'] ?? null)
                    ->columnSpanFull(),
            ]),
        ];

        return array_map(
            static fn (Section $section): Section => $section->visible(static fn (Get $get): bool => $get('target_key') === 'research.publications'),
            $sections,
        );
    }

    /** @return array<int, Section> */
    private function centerFields(string $locale): array
    {
        $prefix = $locale.'_centers';
        $sections = [
            Section::make('Centers Hero')->schema([
                TextInput::make($prefix.'.hero.title')->required()->maxLength(180),
                MediaPicker::image($prefix.'.hero.backgroundImage', 'Background Image', true),
                Textarea::make($prefix.'.hero.summary')->required()->rows(2)->columnSpanFull(),
                TextInput::make($prefix.'.hero.primaryCta')->required()->maxLength(120),
                TextInput::make($prefix.'.hero.secondaryCta')->required()->maxLength(120),
                TextInput::make($prefix.'.hero.secondaryCtaUrl')->required()->maxLength(255)->columnSpanFull(),
                Repeater::make($prefix.'.hero.breadcrumbs')
                    ->schema([
                        TextInput::make('label')->required()->maxLength(120),
                        TextInput::make('url')->required()->maxLength(255),
                    ])
                    ->columns(2)
                    ->defaultItems(0)
                    ->reorderable()
                    ->collapsible()
                    ->columnSpanFull(),
            ])->columns(2),
            Section::make('Centers Introduction')->schema([
                TextInput::make($prefix.'.intro.title')->required()->maxLength(180),
                Textarea::make($prefix.'.intro.summary')->required()->rows(3)->columnSpanFull(),
                Repeater::make($prefix.'.intro.highlights')
                    ->schema([
                        TextInput::make('title')->required()->maxLength(180),
                        MediaPicker::image('icon', 'Icon', true),
                        Textarea::make('summary')->required()->rows(2)->columnSpanFull(),
                    ])
                    ->columns(2)
                    ->defaultItems(0)
                    ->reorderable()
                    ->cloneable()
                    ->collapsible()
                    ->itemLabel(fn (array $state): ?string => $state['title'] ?? null)
                    ->columnSpanFull(),
            ])->columns(2),
            Section::make('Research Centers')->schema([
                Repeater::make($prefix.'.items')
                    ->schema($this->centerItemFields())
                    ->defaultItems(0)
                    ->reorderable()
                    ->cloneable()
                    ->collapsible()
                    ->itemLabel(fn (array $state): ?string => $state['name'] ?? $state['slug'] ?? null)
                    ->columnSpanFull(),
            ]),
            Section::make('Research Laboratories')->schema([
                TextInput::make($prefix.'.laboratories.title')->required()->maxLength(180),
                Repeater::make($prefix.'.laboratories.items')
                    ->schema($this->laboratoryItemFields())
                    ->defaultItems(0)
                    ->reorderable()
                    ->cloneable()
                    ->collapsible()
                    ->itemLabel(fn (array $state): ?string => $state['title'] ?? $state['slug'] ?? null)
                    ->columnSpanFull(),
            ]),
        ];

        return array_map(
            static fn (Section $section): Section => $section->visible(static fn (Get $get): bool => $get('target_key') === 'research.centers'),
            $sections,
        );
    }

    /** @return array<int, Section> */
    private function projectFields(string $locale): array
    {
        $prefix = $locale.'_projects';
        $sections = [
            Section::make('Projects Hero')->schema([
                TextInput::make($prefix.'.hero.eyebrow')->required()->maxLength(160),
                TextInput::make($prefix.'.hero.title')->required()->maxLength(180),
                MediaPicker::image($prefix.'.hero.backgroundImage', 'Background Image', true),
                Textarea::make($prefix.'.hero.summary')->required()->rows(2)->columnSpanFull(),
                Repeater::make($prefix.'.hero.breadcrumbs')->schema([
                    TextInput::make('label')->required()->maxLength(120),
                    TextInput::make('url')->required()->maxLength(255),
                ])->columns(2)->defaultItems(0)->reorderable()->collapsible()->columnSpanFull(),
            ])->columns(2),
            Section::make('Project Filters')->schema([
                TextInput::make($prefix.'.filters.statusLabel')->required()->maxLength(100),
                TextInput::make($prefix.'.filters.facultyLabel')->required()->maxLength(100),
                TextInput::make($prefix.'.filters.themeLabel')->required()->maxLength(100),
                TextInput::make($prefix.'.filters.searchPlaceholder')->required()->maxLength(180),
                Repeater::make($prefix.'.filters.statuses')->schema($this->optionFields())->columns(2)->defaultItems(0)->reorderable()->collapsible()->columnSpanFull(),
                Repeater::make($prefix.'.filters.faculties')->schema($this->optionFields())->columns(2)->defaultItems(0)->reorderable()->collapsible()->columnSpanFull(),
                Repeater::make($prefix.'.filters.themes')->schema($this->optionFields())->columns(2)->defaultItems(0)->reorderable()->collapsible()->columnSpanFull(),
            ])->columns(2),
            Section::make('Project Card Labels')->schema([
                TextInput::make($prefix.'.cardLabels.viewProject')->required()->maxLength(120),
                TextInput::make($prefix.'.cardLabels.since')->required()->maxLength(80),
            ])->columns(2),
            Section::make('Research Projects')->schema([
                Repeater::make($prefix.'.items')
                    ->schema($this->projectItemFields())
                    ->defaultItems(0)
                    ->reorderable()
                    ->cloneable()
                    ->collapsible()
                    ->itemLabel(fn (array $state): ?string => $state['title'] ?? $state['slug'] ?? null)
                    ->columnSpanFull(),
            ]),
        ];

        return array_map(
            static fn (Section $section): Section => $section->visible(static fn (Get $get): bool => $get('target_key') === 'research.projects'),
            $sections,
        );
    }

    /** @return array<int, Section> */
    private function themeFields(string $locale): array
    {
        $prefix = $locale.'_themes';
        $sections = [
            Section::make('Themes Hero')->schema([
                TextInput::make($prefix.'.hero.eyebrow')->required()->maxLength(160),
                TextInput::make($prefix.'.hero.title')->required()->maxLength(180),
                MediaPicker::image($prefix.'.hero.backgroundImage', 'Background Image', true),
                Textarea::make($prefix.'.hero.summary')->required()->rows(2)->columnSpanFull(),
                Repeater::make($prefix.'.hero.breadcrumbs')->schema([
                    TextInput::make('label')->required()->maxLength(120),
                    TextInput::make('url')->required()->maxLength(255),
                ])->columns(2)->defaultItems(0)->reorderable()->collapsible()->columnSpanFull(),
            ])->columns(2),
            Section::make('Research Themes')->schema([
                Repeater::make($prefix.'.items')
                    ->schema($this->themeItemFields())
                    ->defaultItems(0)
                    ->reorderable()
                    ->cloneable()
                    ->collapsible()
                    ->itemLabel(fn (array $state): ?string => $state['name'] ?? $state['slug'] ?? null)
                    ->columnSpanFull(),
            ]),
        ];

        return array_map(
            static fn (Section $section): Section => $section->visible(static fn (Get $get): bool => $get('target_key') === 'research.themes'),
            $sections,
        );
    }

    /** @return array<int, Section> */
    private function expertFields(string $locale): array
    {
        $prefix = $locale.'_experts';
        $sections = [
            Section::make('Expert Finder Hero')->schema([
                TextInput::make($prefix.'.hero.eyebrow')->label('Eyebrow')->required()->maxLength(160),
                TextInput::make($prefix.'.hero.title')->label('Title')->required()->maxLength(180),
                MediaPicker::image($prefix.'.hero.backgroundImage', 'Background Image', true),
                Textarea::make($prefix.'.hero.summary')->label('Summary')->required()->rows(2)->columnSpanFull(),
                TextInput::make($prefix.'.searchPlaceholder')->required()->maxLength(180)->columnSpanFull(),
            ])->columns(2),
            Section::make('Expert Filters')->schema([
                TextInput::make($prefix.'.filters.allFaculties')->required()->maxLength(100),
                TextInput::make($prefix.'.filters.allExpertise')->required()->maxLength(100),
                Repeater::make($prefix.'.faculties')->schema([
                    TextInput::make('id')->required()->maxLength(120),
                    TextInput::make('name')->required()->maxLength(160),
                ])->columns(2)->defaultItems(0)->reorderable()->collapsible()->columnSpanFull(),
                Repeater::make($prefix.'.expertiseAreas')->schema([
                    TextInput::make('id')->required()->maxLength(120),
                    TextInput::make('name')->required()->maxLength(160),
                ])->columns(2)->defaultItems(0)->reorderable()->collapsible()->columnSpanFull(),
            ])->columns(2),
            Section::make('Expert Profiles')->schema([
                Repeater::make($prefix.'.researchers')
                    ->schema($this->expertItemFields())
                    ->defaultItems(0)
                    ->reorderable()
                    ->cloneable()
                    ->collapsible()
                    ->itemLabel(fn (array $state): ?string => $state['name'] ?? $state['slug'] ?? null)
                    ->columnSpanFull(),
            ]),
        ];

        return array_map(
            static fn (Section $section): Section => $section->visible(static fn (Get $get): bool => $get('target_key') === 'research.experts'),
            $sections,
        );
    }

    /** @return array<int, Section> */
    private function conferenceFields(string $locale): array
    {
        $prefix = $locale.'_conferences';
        $sections = [
            Section::make('Conferences Hero')->schema([
                TextInput::make($prefix.'.hero.eyebrow')->label('Eyebrow')->required()->maxLength(160),
                TextInput::make($prefix.'.hero.title')->label('Title')->required()->maxLength(180),
                MediaPicker::image($prefix.'.hero.backgroundImage', 'Background Image', true),
                Textarea::make($prefix.'.hero.summary')->label('Summary')->required()->rows(2)->columnSpanFull(),
            ])->columns(2),
            Section::make('Upcoming Events')->schema([
                TextInput::make($prefix.'.upcomingSection.title')->required()->maxLength(180),
                TextInput::make($prefix.'.upcomingSection.viewAll')->maxLength(120),
                Repeater::make($prefix.'.upcoming')
                    ->schema($this->conferenceEventFields(true))
                    ->defaultItems(0)
                    ->reorderable()
                    ->cloneable()
                    ->collapsible()
                    ->itemLabel(fn (array $state): ?string => $state['title'] ?? $state['id'] ?? null)
                    ->columnSpanFull(),
            ])->columns(2),
            Section::make('Past Conferences')->schema([
                TextInput::make($prefix.'.pastSection.title')->required()->maxLength(180),
                TextInput::make($prefix.'.pastSection.proceedings')->maxLength(120),
                Repeater::make($prefix.'.past')
                    ->schema($this->conferenceEventFields(false))
                    ->defaultItems(0)
                    ->reorderable()
                    ->cloneable()
                    ->collapsible()
                    ->itemLabel(fn (array $state): ?string => $state['title'] ?? $state['id'] ?? null)
                    ->columnSpanFull(),
            ])->columns(2),
        ];

        return array_map(
            static fn (Section $section): Section => $section->visible(static fn (Get $get): bool => $get('target_key') === 'research.conferences'),
            $sections,
        );
    }

    /** @return array<int, Section> */
    private function libraryFields(string $locale): array
    {
        $prefix = $locale.'_library';
        $sections = [
            Section::make('Library Hero')->schema([
                TextInput::make($prefix.'.hero.eyebrow')->label('Eyebrow')->required()->maxLength(160),
                TextInput::make($prefix.'.hero.title')->label('Title')->required()->maxLength(180),
                MediaPicker::image($prefix.'.hero.backgroundImage', 'Background Image', true),
                Textarea::make($prefix.'.hero.summary')->label('Summary')->required()->rows(2)->columnSpanFull(),
            ])->columns(2),
            Section::make('Digital Resources')->schema([
                TextInput::make($prefix.'.resourcesSection.title')->required()->maxLength(180),
                TextInput::make($prefix.'.resourcesSection.subtitle')->maxLength(220)->columnSpanFull(),
                Repeater::make($prefix.'.databases')
                    ->schema([
                        TextInput::make('name')->required()->maxLength(180),
                        TextInput::make('accessType')->required()->maxLength(120),
                        TextInput::make('url')->required()->maxLength(255)->columnSpanFull(),
                        Textarea::make('description')->required()->rows(2)->columnSpanFull(),
                    ])
                    ->columns(2)
                    ->defaultItems(0)
                    ->reorderable()
                    ->cloneable()
                    ->collapsible()
                    ->itemLabel(fn (array $state): ?string => $state['name'] ?? null)
                    ->columnSpanFull(),
            ])->columns(2),
            Section::make('Borrowing Rules')->schema([
                TextInput::make($prefix.'.borrowingSection.title')->required()->maxLength(180),
                Repeater::make($prefix.'.borrowingSection.rules')
                    ->schema($this->titleDescriptionFields())
                    ->columns(2)
                    ->defaultItems(0)
                    ->reorderable()
                    ->cloneable()
                    ->collapsible()
                    ->itemLabel(fn (array $state): ?string => $state['title'] ?? null)
                    ->columnSpanFull(),
            ]),
            Section::make('Special Collections')->schema([
                TextInput::make($prefix.'.specialCollections.title')->required()->maxLength(180),
                Repeater::make($prefix.'.specialCollections.items')
                    ->schema($this->titleDescriptionFields())
                    ->columns(2)
                    ->defaultItems(0)
                    ->reorderable()
                    ->cloneable()
                    ->collapsible()
                    ->itemLabel(fn (array $state): ?string => $state['title'] ?? null)
                    ->columnSpanFull(),
            ]),
            Section::make('Librarian Contact')->schema([
                TextInput::make($prefix.'.librarianSection.title')->required()->maxLength(180),
                TextInput::make($prefix.'.librarianSection.name')->required()->maxLength(180),
                TextInput::make($prefix.'.librarianSection.hours')->required()->maxLength(180),
                TextInput::make($prefix.'.librarianSection.email')->email()->required()->maxLength(180),
                TextInput::make($prefix.'.librarianSection.phone')->required()->maxLength(80),
            ])->columns(2),
        ];

        return array_map(
            static fn (Section $section): Section => $section->visible(static fn (Get $get): bool => $get('target_key') === 'research.library'),
            $sections,
        );
    }

    /** @return array<int, Section> */
    private function officeFields(string $locale): array
    {
        $prefix = $locale.'_office';
        $sections = [
            Section::make('Office Hero')->schema([
                TextInput::make($prefix.'.hero.eyebrow')->label('Eyebrow')->required()->maxLength(160),
                TextInput::make($prefix.'.hero.title')->label('Title')->required()->maxLength(180),
                MediaPicker::image($prefix.'.hero.backgroundImage', 'Background Image', true),
                Textarea::make($prefix.'.hero.summary')->label('Summary')->required()->rows(2)->columnSpanFull(),
            ])->columns(2),
            Section::make('Research Leadership')->schema([
                TextInput::make($prefix.'.leadership.title')->required()->maxLength(180),
                Repeater::make($prefix.'.leadership.items')
                    ->schema([
                        TextInput::make('name')->required()->maxLength(180),
                        TextInput::make('role')->required()->maxLength(180),
                        TextInput::make('email')->email()->required()->maxLength(180),
                        MediaPicker::image('image', 'Profile Image', true)->columnSpanFull(),
                    ])
                    ->columns(2)
                    ->defaultItems(0)
                    ->reorderable()
                    ->cloneable()
                    ->collapsible()
                    ->itemLabel(fn (array $state): ?string => $state['name'] ?? null)
                    ->columnSpanFull(),
            ]),
            Section::make('Office Services')->schema([
                TextInput::make($prefix.'.services.title')->required()->maxLength(180),
                TextInput::make($prefix.'.services.subtitle')->maxLength(220)->columnSpanFull(),
                Repeater::make($prefix.'.services.items')
                    ->schema($this->titleDescriptionFields())
                    ->columns(2)
                    ->defaultItems(0)
                    ->reorderable()
                    ->cloneable()
                    ->collapsible()
                    ->itemLabel(fn (array $state): ?string => $state['title'] ?? null)
                    ->columnSpanFull(),
            ])->columns(2),
            Section::make('Office Statistics')->schema([
                TextInput::make($prefix.'.statistics.title')->required()->maxLength(180),
                Repeater::make($prefix.'.statistics.items')
                    ->schema([
                        TextInput::make('value')->required()->maxLength(80),
                        TextInput::make('label')->required()->maxLength(160),
                    ])
                    ->columns(2)
                    ->defaultItems(0)
                    ->reorderable()
                    ->cloneable()
                    ->collapsible()
                    ->itemLabel(fn (array $state): ?string => $state['label'] ?? null)
                    ->columnSpanFull(),
            ]),
            Section::make('Office Contact')->schema([
                TextInput::make($prefix.'.contact.title')->required()->maxLength(180),
                TextInput::make($prefix.'.contact.address')->required()->maxLength(180),
                TextInput::make($prefix.'.contact.addressDetail')->required()->maxLength(255)->columnSpanFull(),
                TextInput::make($prefix.'.contact.email')->email()->required()->maxLength(180),
                TextInput::make($prefix.'.contact.phone')->required()->maxLength(80),
                TextInput::make($prefix.'.contact.hours')->required()->maxLength(180),
            ])->columns(2),
        ];

        return array_map(
            static fn (Section $section): Section => $section->visible(static fn (Get $get): bool => $get('target_key') === 'research.office'),
            $sections,
        );
    }

    /** @return array<int, Section> */
    private function policyFields(string $locale): array
    {
        $prefix = $locale.'_policies';
        $sections = [
            Section::make('Policies Hero')->schema([
                TextInput::make($prefix.'.hero.eyebrow')->label('Eyebrow')->required()->maxLength(160),
                TextInput::make($prefix.'.hero.title')->label('Title')->required()->maxLength(180),
                MediaPicker::image($prefix.'.hero.backgroundImage', 'Background Image', true),
                Textarea::make($prefix.'.hero.summary')->label('Summary')->required()->rows(2)->columnSpanFull(),
            ])->columns(2),
            Section::make('Policy Sections')->schema([
                Repeater::make($prefix.'.sections')
                    ->schema([
                        TextInput::make('id')->required()->maxLength(120),
                        TextInput::make('title')->required()->maxLength(180),
                        Textarea::make('description')->required()->rows(2)->columnSpanFull(),
                        Repeater::make('documents')
                            ->schema([
                                TextInput::make('title')->required()->maxLength(180),
                                TextInput::make('fileType')->required()->maxLength(40),
                                MediaPicker::document('url', 'Document File', true)->columnSpanFull(),
                            ])
                            ->columns(2)
                            ->defaultItems(0)
                            ->reorderable()
                            ->cloneable()
                            ->collapsible()
                            ->itemLabel(fn (array $state): ?string => $state['title'] ?? null)
                            ->columnSpanFull(),
                    ])
                    ->columns(2)
                    ->defaultItems(0)
                    ->reorderable()
                    ->cloneable()
                    ->collapsible()
                    ->itemLabel(fn (array $state): ?string => $state['title'] ?? $state['id'] ?? null)
                    ->columnSpanFull(),
            ]),
            Section::make('Policy Contact')->schema([
                TextInput::make($prefix.'.contactSection.title')->required()->maxLength(180),
                Textarea::make($prefix.'.contactSection.description')->required()->rows(2)->columnSpanFull(),
                TextInput::make($prefix.'.contactSection.email')->email()->required()->maxLength(180),
                TextInput::make($prefix.'.contactSection.phone')->required()->maxLength(80),
                TextInput::make($prefix.'.contactSection.location')->required()->maxLength(220)->columnSpanFull(),
            ])->columns(2),
        ];

        return array_map(
            static fn (Section $section): Section => $section->visible(static fn (Get $get): bool => $get('target_key') === 'research.policies'),
            $sections,
        );
    }

    /** @return array<int, TextInput> */
    private function optionFields(): array
    {
        return [
            TextInput::make('value')->maxLength(120),
            TextInput::make('label')->required()->maxLength(160),
        ];
    }

    /** @return array<int, mixed> */
    private function titleDescriptionFields(): array
    {
        return [
            TextInput::make('title')->required()->maxLength(180),
            Textarea::make('description')->required()->rows(2)->columnSpanFull(),
        ];
    }

    /** @return array<int, mixed> */
    private function centerItemFields(): array
    {
        return [
            TextInput::make('id')->required()->maxLength(120),
            TextInput::make('slug')->required()->maxLength(160),
            TextInput::make('name')->required()->maxLength(180)->columnSpanFull(),
            Textarea::make('mission')->required()->rows(3)->columnSpanFull(),
            TextInput::make('faculty')->required()->maxLength(180),
            TextInput::make('facultySlug')->required()->maxLength(120),
            TextInput::make('directorName')->required()->maxLength(180),
            TextInput::make('contactEmail')->email()->required()->maxLength(180),
            TextInput::make('contactPhone')->maxLength(80),
            TextInput::make('externalWebsite')->url()->maxLength(255)->columnSpanFull(),
            MediaPicker::image('image', 'Center Image', true)->columnSpanFull(),
            TextInput::make('labs')->numeric()->minValue(0)->required(),
            TextInput::make('researchers')->numeric()->minValue(0)->required(),
            TextInput::make('projects')->numeric()->minValue(0)->required(),
            TextInput::make('publications')->numeric()->minValue(0)->required(),
            TagsInput::make('publicationSlugs')->label('Related Publication Slugs')->columnSpanFull(),
            TagsInput::make('projectSlugs')->label('Related Project Slugs')->columnSpanFull(),
            TagsInput::make('researcherSlugs')->label('Affiliated Researcher Slugs')->columnSpanFull(),
        ];
    }

    /** @return array<int, mixed> */
    private function laboratoryItemFields(): array
    {
        return [
            TextInput::make('id')->required()->maxLength(120),
            TextInput::make('slug')->required()->maxLength(160),
            TextInput::make('title')->required()->maxLength(180)->columnSpanFull(),
            TextInput::make('faculty')->required()->maxLength(180),
            TextInput::make('director')->required()->maxLength(180),
            Textarea::make('summary')->required()->rows(2)->columnSpanFull(),
            TextInput::make('projects')->required()->maxLength(180),
            TextInput::make('publications')->required()->maxLength(180),
            TextInput::make('contact')->required()->maxLength(180),
            TextInput::make('cta')->required()->maxLength(120),
            MediaPicker::image('image', 'Laboratory Image', true)->columnSpanFull(),
        ];
    }

    /** @return array<int, mixed> */
    private function projectItemFields(): array
    {
        return [
            TextInput::make('id')->required()->maxLength(120),
            TextInput::make('slug')->required()->maxLength(160),
            TextInput::make('title')->required()->maxLength(240)->columnSpanFull(),
            Textarea::make('summary')->required()->rows(3)->columnSpanFull(),
            TextInput::make('faculty')->required()->maxLength(180),
            TextInput::make('facultySlug')->required()->maxLength(120),
            TextInput::make('theme')->required()->maxLength(180),
            TextInput::make('themeSlug')->required()->maxLength(120),
            Select::make('status')->required()->options([
                'ongoing' => 'Ongoing',
                'completed' => 'Completed',
                'paused' => 'Paused',
            ]),
            TextInput::make('startYear')->required()->numeric()->minValue(1900)->maxValue(2200),
            TextInput::make('endYear')->numeric()->minValue(1900)->maxValue(2200),
            TextInput::make('funding')->required()->maxLength(180)->columnSpanFull(),
            MediaPicker::image('image', 'Project Image', true)->columnSpanFull(),
        ];
    }

    /** @return array<int, mixed> */
    private function themeItemFields(): array
    {
        return [
            TextInput::make('id')->required()->maxLength(120),
            TextInput::make('slug')->required()->maxLength(160),
            TextInput::make('name')->required()->maxLength(180)->columnSpanFull(),
            Textarea::make('description')->required()->rows(3)->columnSpanFull(),
            MediaPicker::image('icon', 'Theme Icon', true)->columnSpanFull(),
            TextInput::make('publicationCount')->required()->numeric()->minValue(0),
            TextInput::make('projectCount')->required()->numeric()->minValue(0),
        ];
    }

    /** @return array<int, mixed> */
    private function publicationItemFields(): array
    {
        return [
            TextInput::make('id')->required()->maxLength(80),
            TextInput::make('slug')->required()->maxLength(160),
            TextInput::make('title')->required()->maxLength(240)->columnSpanFull(),
            Textarea::make('summary')->required()->rows(2)->columnSpanFull(),
            TextInput::make('type')->required()->maxLength(120),
            TextInput::make('typeSlug')->required()->maxLength(120),
            TextInput::make('faculty')->required()->maxLength(160),
            TextInput::make('facultySlug')->required()->maxLength(120),
            TextInput::make('author')->required()->maxLength(160),
            TextInput::make('authorSlug')->required()->maxLength(160),
            TextInput::make('year')->required()->maxLength(20),
            TextInput::make('doi')->maxLength(180),
            TextInput::make('journalTitle')->label('Journal / Proceedings')->maxLength(180),
            TextInput::make('volume')->maxLength(40),
            TextInput::make('issue')->maxLength(40),
            TextInput::make('pages')->maxLength(40),
            TextInput::make('issn')->label('ISSN')->maxLength(40),
            TextInput::make('license')->maxLength(180)->columnSpanFull(),
            MediaPicker::image('image', 'Publication Image', true)->columnSpanFull(),
            Textarea::make('lead')->label('Detail Lead')->rows(2)->columnSpanFull(),
            TagsInput::make('paragraphs')->label('Detail Paragraphs')->columnSpanFull(),
            Textarea::make('keyStatement')->rows(2)->columnSpanFull(),
            TagsInput::make('keywords')->columnSpanFull(),
            TagsInput::make('themes')->columnSpanFull(),
            Repeater::make('resolvedThemes')
                ->schema([
                    TextInput::make('slug')->required()->maxLength(120),
                    TextInput::make('label')->required()->maxLength(160),
                ])
                ->columns(2)
                ->defaultItems(0)
                ->reorderable()
                ->collapsible()
                ->columnSpanFull(),
            TextInput::make('scholarUrl')->maxLength(255)->columnSpanFull(),
            TextInput::make('scopusUrl')->maxLength(255)->columnSpanFull(),
            TextInput::make('category')->maxLength(120),
            TextInput::make('rate')->maxLength(80),
            Toggle::make('isOpenAccess')->label('Open Access'),
            Toggle::make('gsIndexed')->label('Google Scholar Indexed'),
            Repeater::make('downloads')
                ->schema([
                    TextInput::make('label')->required()->maxLength(180),
                    TextInput::make('type')->maxLength(40),
                    MediaPicker::document('url', 'Publication File', true)->columnSpanFull(),
                ])
                ->columns(2)
                ->defaultItems(0)
                ->reorderable()
                ->collapsible()
                ->columnSpanFull(),
        ];
    }

    /** @return array<int, mixed> */
    private function expertItemFields(): array
    {
        return [
            TextInput::make('id')->required()->maxLength(120),
            TextInput::make('slug')->required()->maxLength(160),
            TextInput::make('name')->required()->maxLength(180),
            TextInput::make('title')->required()->maxLength(180),
            TextInput::make('faculty')->required()->maxLength(180),
            TextInput::make('facultyId')->required()->maxLength(120),
            TextInput::make('facultySlug')->required()->maxLength(120),
            TextInput::make('department')->maxLength(180),
            Textarea::make('bio')->rows(2)->columnSpanFull(),
            TagsInput::make('biography')->columnSpanFull(),
            TagsInput::make('expertise')->columnSpanFull(),
            TextInput::make('email')->email()->maxLength(180),
            MediaPicker::image('image', 'Profile Image', true)->columnSpanFull(),
            TextInput::make('orcidUrl')->maxLength(255)->columnSpanFull(),
            TextInput::make('scholarUrl')->maxLength(255)->columnSpanFull(),
            TextInput::make('publications')->numeric(),
            TextInput::make('citations')->numeric(),
            TextInput::make('office.fullAddress')->label('Office')->maxLength(255)->columnSpanFull(),
            Repeater::make('education')->schema([
                TextInput::make('degree')->required()->maxLength(180),
                TextInput::make('institution')->maxLength(180),
                TextInput::make('year')->maxLength(40),
            ])->columns(3)->defaultItems(0)->reorderable()->collapsible()->columnSpanFull(),
            Repeater::make('courses')->schema([
                TextInput::make('id')->required()->maxLength(120),
                TextInput::make('code')->required()->maxLength(40),
                TextInput::make('name')->required()->maxLength(180),
                TextInput::make('departmentId')->required()->maxLength(120),
            ])->columns(2)->defaultItems(0)->reorderable()->collapsible()->columnSpanFull(),
            Repeater::make('profilePublications')->schema([
                TextInput::make('id')->required()->maxLength(120),
                TextInput::make('title')->required()->maxLength(240)->columnSpanFull(),
                TextInput::make('journal')->maxLength(180),
                TextInput::make('year')->maxLength(40),
                TextInput::make('links.local')->label('Local URL')->maxLength(255)->columnSpanFull(),
                TextInput::make('links.scholar')->label('Scholar URL')->maxLength(255)->columnSpanFull(),
            ])->columns(2)->defaultItems(0)->reorderable()->collapsible()->columnSpanFull(),
        ];
    }

    /** @return array<int, mixed> */
    private function conferenceEventFields(bool $upcoming): array
    {
        $fields = [
            TextInput::make('id')->required()->maxLength(120),
            TextInput::make('title')->required()->maxLength(240)->columnSpanFull(),
            TextInput::make('date')->required()->maxLength(120),
            TextInput::make('location')->required()->maxLength(180),
            MediaPicker::image('image', 'Event Image', true)->columnSpanFull(),
            Textarea::make('description')->required()->rows(2)->columnSpanFull(),
        ];

        if ($upcoming) {
            $fields[] = TextInput::make('eventType')->required()->maxLength(160);
            $fields[] = TextInput::make('registrationUrl')->maxLength(255)->columnSpanFull();

            return $fields;
        }

        $fields[] = TextInput::make('participants')->maxLength(120);
        $fields[] = Toggle::make('hasProceedings')->label('Proceedings Available');
        $fields[] = MediaPicker::document('proceedingsUrl', 'Proceedings File')->columnSpanFull();

        return $fields;
    }

    /** @param array<string, mixed> $state @return array<string, mixed> */
    private function payloadFromForm(array $state): array
    {
        if (($state['target_key'] ?? null) === 'research.index') {
            return [
                'translations' => [
                    'ar' => $this->normalizeLandingContent(is_array($state['ar_landing'] ?? null) ? $state['ar_landing'] : []),
                    'en' => $this->normalizeLandingContent(is_array($state['en_landing'] ?? null) ? $state['en_landing'] : []),
                ],
            ];
        }

        if (($state['target_key'] ?? null) === 'research.experts') {
            return [
                'translations' => [
                    'ar' => $this->normalizeExpertContent(is_array($state['ar_experts'] ?? null) ? $state['ar_experts'] : []),
                    'en' => $this->normalizeExpertContent(is_array($state['en_experts'] ?? null) ? $state['en_experts'] : []),
                ],
            ];
        }

        if (($state['target_key'] ?? null) === 'research.centers') {
            return [
                'translations' => [
                    'ar' => $this->normalizeCenterContent(is_array($state['ar_centers'] ?? null) ? $state['ar_centers'] : []),
                    'en' => $this->normalizeCenterContent(is_array($state['en_centers'] ?? null) ? $state['en_centers'] : []),
                ],
            ];
        }

        if (($state['target_key'] ?? null) === 'research.projects') {
            return [
                'translations' => [
                    'ar' => $this->normalizeProjectContent(is_array($state['ar_projects'] ?? null) ? $state['ar_projects'] : []),
                    'en' => $this->normalizeProjectContent(is_array($state['en_projects'] ?? null) ? $state['en_projects'] : []),
                ],
            ];
        }

        if (($state['target_key'] ?? null) === 'research.themes') {
            return [
                'translations' => [
                    'ar' => $this->normalizeThemeContent(is_array($state['ar_themes'] ?? null) ? $state['ar_themes'] : []),
                    'en' => $this->normalizeThemeContent(is_array($state['en_themes'] ?? null) ? $state['en_themes'] : []),
                ],
            ];
        }

        if (($state['target_key'] ?? null) === 'research.conferences') {
            return [
                'translations' => [
                    'ar' => $this->normalizeConferenceContent(is_array($state['ar_conferences'] ?? null) ? $state['ar_conferences'] : []),
                    'en' => $this->normalizeConferenceContent(is_array($state['en_conferences'] ?? null) ? $state['en_conferences'] : []),
                ],
            ];
        }

        if (($state['target_key'] ?? null) === 'research.library') {
            return [
                'translations' => [
                    'ar' => $this->normalizeLibraryContent(is_array($state['ar_library'] ?? null) ? $state['ar_library'] : []),
                    'en' => $this->normalizeLibraryContent(is_array($state['en_library'] ?? null) ? $state['en_library'] : []),
                ],
            ];
        }

        if (($state['target_key'] ?? null) === 'research.office') {
            return [
                'translations' => [
                    'ar' => $this->normalizeOfficeContent(is_array($state['ar_office'] ?? null) ? $state['ar_office'] : []),
                    'en' => $this->normalizeOfficeContent(is_array($state['en_office'] ?? null) ? $state['en_office'] : []),
                ],
            ];
        }

        if (($state['target_key'] ?? null) === 'research.policies') {
            return [
                'translations' => [
                    'ar' => $this->normalizePolicyContent(is_array($state['ar_policies'] ?? null) ? $state['ar_policies'] : []),
                    'en' => $this->normalizePolicyContent(is_array($state['en_policies'] ?? null) ? $state['en_policies'] : []),
                ],
            ];
        }

        return [
            'translations' => [
                'ar' => $this->normalizePublicationContent(is_array($state['ar_publications'] ?? null) ? $state['ar_publications'] : []),
                'en' => $this->normalizePublicationContent(is_array($state['en_publications'] ?? null) ? $state['en_publications'] : []),
            ],
        ];
    }

    /** @param array<string, mixed> $content @return array<string, mixed> */
    private function normalizePublicationContent(array $content): array
    {
        $content['filters']['faculties'] = $this->listOfArrays($content['filters']['faculties'] ?? []);
        $content['filters']['publicationTypes'] = $this->listOfArrays($content['filters']['publicationTypes'] ?? []);
        $content['filters']['years'] = $this->listOfArrays($content['filters']['years'] ?? []);
        $content['items'] = array_map(function (array $item): array {
            $slug = trim((string) ($item['slug'] ?? ''));
            $item['links'] = ['local' => $slug !== '' ? '/research/publications/'.$slug.'/' : '#'];
            $item['paragraphs'] = $this->listOfStrings($item['paragraphs'] ?? []);
            $item['keywords'] = $this->listOfStrings($item['keywords'] ?? []);
            $item['themes'] = $this->listOfStrings($item['themes'] ?? []);
            $item['resolvedThemes'] = $this->listOfArrays($item['resolvedThemes'] ?? []);
            $item['isOpenAccess'] = (bool) ($item['isOpenAccess'] ?? false);
            $item['gsIndexed'] = (bool) ($item['gsIndexed'] ?? false);

            return $item;
        }, $this->listOfArrays($content['items'] ?? []));

        return $content;
    }

    /** @param array<string, mixed> $content @return array<string, mixed> */
    private function normalizeCenterContent(array $content): array
    {
        $content['hero']['breadcrumbs'] = $this->listOfArrays($content['hero']['breadcrumbs'] ?? []);
        $content['intro']['highlights'] = $this->listOfArrays($content['intro']['highlights'] ?? []);
        $content['items'] = array_map(function (array $item): array {
            foreach (['labs', 'researchers', 'projects', 'publications'] as $field) {
                $item[$field] = is_numeric($item[$field] ?? null) ? max(0, (int) $item[$field]) : 0;
            }

            $item['publicationSlugs'] = $this->listOfStrings($item['publicationSlugs'] ?? []);
            $item['projectSlugs'] = $this->listOfStrings($item['projectSlugs'] ?? []);
            $item['researcherSlugs'] = $this->listOfStrings($item['researcherSlugs'] ?? []);

            return $item;
        }, $this->listOfArrays($content['items'] ?? []));
        $content['laboratories']['items'] = array_map(function (array $item): array {
            $slug = trim((string) ($item['slug'] ?? ''));
            $item['ctaUrl'] = $slug !== '' ? '/research/centers/'.$slug.'/' : '';

            return $item;
        }, $this->listOfArrays($content['laboratories']['items'] ?? []));

        return $content;
    }

    /** @param array<string, mixed> $content @return array<string, mixed> */
    private function normalizeProjectContent(array $content): array
    {
        $content['hero']['breadcrumbs'] = $this->listOfArrays($content['hero']['breadcrumbs'] ?? []);
        foreach (['statuses', 'faculties', 'themes'] as $optionGroup) {
            $content['filters'][$optionGroup] = $this->listOfArrays($content['filters'][$optionGroup] ?? []);
        }
        $content['items'] = array_map(function (array $item): array {
            $item['id'] = strtolower(trim((string) ($item['id'] ?? '')));
            $item['slug'] = strtolower(trim((string) ($item['slug'] ?? '')));
            $item['facultySlug'] = $this->canonicalFacultySlug(strtolower(trim((string) ($item['facultySlug'] ?? ''))));
            $item['themeSlug'] = strtolower(trim((string) ($item['themeSlug'] ?? '')));
            $item['status'] = strtolower(trim((string) ($item['status'] ?? '')));
            $item['startYear'] = trim((string) ($item['startYear'] ?? ''));
            $item['endYear'] = trim((string) ($item['endYear'] ?? ''));

            return $item;
        }, $this->listOfArrays($content['items'] ?? []));

        return $content;
    }

    /** @param array<string, mixed> $content @return array<string, mixed> */
    private function normalizeThemeContent(array $content): array
    {
        $content['hero']['breadcrumbs'] = $this->listOfArrays($content['hero']['breadcrumbs'] ?? []);
        $content['items'] = array_map(static function (array $item): array {
            $item['id'] = strtolower(trim((string) ($item['id'] ?? '')));
            $item['slug'] = strtolower(trim((string) ($item['slug'] ?? '')));
            $item['publicationCount'] = is_numeric($item['publicationCount'] ?? null) ? max(0, (int) $item['publicationCount']) : 0;
            $item['projectCount'] = is_numeric($item['projectCount'] ?? null) ? max(0, (int) $item['projectCount']) : 0;

            return $item;
        }, $this->listOfArrays($content['items'] ?? []));

        return $content;
    }

    /** @param array<string, mixed> $content @return array<string, mixed> */
    private function normalizeConferenceContent(array $content): array
    {
        $content['upcoming'] = array_map(function (array $event): array {
            $event['registrationUrl'] = $event['registrationUrl'] ?? '#';

            return $event;
        }, $this->listOfArrays($content['upcoming'] ?? []));

        $content['past'] = array_map(function (array $event): array {
            $event['hasProceedings'] = (bool) ($event['hasProceedings'] ?? false);
            $event['proceedingsUrl'] = $event['proceedingsUrl'] ?? '#';

            return $event;
        }, $this->listOfArrays($content['past'] ?? []));

        return $content;
    }

    /** @param array<string, mixed> $content @return array<string, mixed> */
    private function normalizeLibraryContent(array $content): array
    {
        $content['databases'] = $this->listOfArrays($content['databases'] ?? []);
        $content['borrowingSection']['rules'] = $this->listOfArrays($content['borrowingSection']['rules'] ?? []);
        $content['specialCollections']['items'] = $this->listOfArrays($content['specialCollections']['items'] ?? []);

        return $content;
    }

    /** @param array<string, mixed> $content @return array<string, mixed> */
    private function normalizeOfficeContent(array $content): array
    {
        $content['leadership']['items'] = $this->listOfArrays($content['leadership']['items'] ?? []);
        $content['services']['items'] = $this->listOfArrays($content['services']['items'] ?? []);
        $content['statistics']['items'] = $this->listOfArrays($content['statistics']['items'] ?? []);

        return $content;
    }

    /** @param array<string, mixed> $content @return array<string, mixed> */
    private function normalizePolicyContent(array $content): array
    {
        $content['sections'] = array_map(function (array $section): array {
            $section['documents'] = $this->listOfArrays($section['documents'] ?? []);

            return $section;
        }, $this->listOfArrays($content['sections'] ?? []));

        return $content;
    }

    /** @param array<string, mixed> $content @return array<string, mixed> */
    private function normalizeLandingContent(array $content): array
    {
        $featuredSlug = trim((string) ($content['featuredPublication']['slug'] ?? ''));
        $content['stats'] = $this->listOfArrays($content['stats'] ?? []);
        $content['featuredPublication']['links'] = ['local' => $featuredSlug !== '' ? '/research/publications/'.$featuredSlug.'/' : '#'];
        $content['gateway']['cards'] = $this->listOfArrays($content['gateway']['cards'] ?? []);

        return $content;
    }

    /** @param array<string, mixed> $content @return array<string, mixed> */
    private function normalizeExpertContent(array $content): array
    {
        $content['faculties'] = $this->listOfArrays($content['faculties'] ?? []);
        $content['expertiseAreas'] = $this->listOfArrays($content['expertiseAreas'] ?? []);
        $content['resultsLabel'] = $content['resultsLabel'] ?? 'results found';
        $content['viewProfileLabel'] = $content['viewProfileLabel'] ?? 'View Profile';
        $content['publicationsLabel'] = $content['publicationsLabel'] ?? 'Publications';
        $content['citationsLabel'] = $content['citationsLabel'] ?? 'Citations';
        $content['researchers'] = array_map(function (array $item): array {
            $item['role'] = $item['title'] ?? $item['role'] ?? '';
            $item['description'] = $item['bio'] ?? $item['description'] ?? '';
            $item['biography'] = $this->listOfStrings($item['biography'] ?? []);
            $item['expertise'] = $this->listOfStrings($item['expertise'] ?? []);
            $item['education'] = $this->listOfArrays($item['education'] ?? []);
            $item['courses'] = $this->listOfArrays($item['courses'] ?? []);
            $item['profilePublications'] = $this->listOfArrays($item['profilePublications'] ?? []);
            $item['publications'] = is_numeric($item['publications'] ?? null) ? (int) $item['publications'] : 0;
            $item['citations'] = is_numeric($item['citations'] ?? null) ? (int) $item['citations'] : 0;

            return $item;
        }, $this->listOfArrays($content['researchers'] ?? []));
        $content['items'] = $content['researchers'];

        return $content;
    }

    /** @return array<string, mixed> */
    private function currentFormData(): array
    {
        return is_array($this->data) ? $this->data : [];
    }

    /** @return array<int, array<string, mixed>> */
    private function listOfArrays(mixed $items): array
    {
        return array_values(array_filter(is_array($items) ? $items : [], static fn (mixed $item): bool => is_array($item)));
    }

    /** @return array<int, string> */
    private function listOfStrings(mixed $items): array
    {
        return array_values(array_filter(is_array($items) ? $items : [], static fn (mixed $item): bool => is_string($item) && trim($item) !== ''));
    }

    private function canonicalFacultySlug(string $slug): string
    {
        return match ($slug) {
            'ai', 'ai-engineering' => 'artificial-intelligence',
            'construction' => 'building-construction-engineering',
            'business' => 'business-administration',
            default => $slug,
        };
    }

    /** @param array<string, array<int, string>> $errors */
    private function formatValidationErrors(array $errors): string
    {
        return collect($errors)->flatten()->implode(PHP_EOL);
    }
}
