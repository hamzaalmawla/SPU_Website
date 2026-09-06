<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Contracts\Cms\CmsWorkflowServiceInterface;
use App\Contracts\Research\ResearchPageServiceInterface;
use App\Exceptions\ConflictException;
use App\Filament\Components\PageUrlSelect;
use App\Filament\Support\MediaPicker;
use App\Models\User\User;
use Filament\Actions\Action;
use Filament\Forms\Components\Component;
use Filament\Forms\Components\DateTimePicker;
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
use Filament\Forms\Components\Toggle;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
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

    /** @var array<string, mixed> */
    public array $sourcePayload = [];

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
        $requestedTarget = request()->query('target', 'research.index');
        $targetKey = is_string($requestedTarget) && array_key_exists($requestedTarget, $this->targetOptions())
            ? $requestedTarget
            : 'research.index';

        $this->loadTarget($targetKey);
    }

    public function form(Form $form): Form
    {
        $targetKey = $this->formTargetKey();

        return $form
            ->schema([
                Hidden::make('target_key')->required(),
                Tabs::make('research_locales')
                    ->tabs([
                        Tab::make(__('admin.research_workspace.locales.ar'))
                            ->extraAttributes(['dir' => 'rtl', 'lang' => 'ar'])
                            ->schema($this->targetFields($targetKey, 'ar')),
                        Tab::make(__('admin.research_workspace.locales.en'))
                            ->extraAttributes(['dir' => 'ltr', 'lang' => 'en'])
                            ->schema($this->targetFields($targetKey, 'en')),
                    ])
                    ->persistTabInQueryString('locale')
                    ->columnSpanFull(),
            ])
            ->statePath('data');
    }

    private function formTargetKey(): string
    {
        $stateTarget = $this->data['target_key'] ?? null;
        if (is_string($stateTarget) && array_key_exists($stateTarget, $this->targetOptions())) {
            return $stateTarget;
        }

        $requestedTarget = request()->query('target', 'research.index');

        return is_string($requestedTarget) && array_key_exists($requestedTarget, $this->targetOptions())
            ? $requestedTarget
            : 'research.index';
    }

    /** @return array<int, Component> */
    private function targetFields(string $targetKey, string $locale): array
    {
        return match ($targetKey) {
            'research.index' => $this->landingFields($locale),
            'research.publications' => $this->publicationFields($locale),
            'research.centers' => $this->centerFields($locale),
            'research.projects' => $this->projectFields($locale),
            'research.themes' => $this->themeFields($locale),
            'research.experts' => $this->expertFields($locale),
            'research.conferences' => $this->conferenceFields($locale),
            'research.library' => $this->libraryFields($locale),
            'research.office' => $this->officeFields($locale),
            'research.policies' => $this->policyFields($locale),
            default => [],
        };
    }

    public function loadTarget(string $targetKey): void
    {
        $this->assertResearchTarget($targetKey);

        $draftPayload = $this->cmsWorkflowService->latestEditableDraftPayload($targetKey, (int) auth()->id());
        $payload = is_array($draftPayload) ? $draftPayload : $this->researchPageService->getEditablePayload($targetKey);
        $this->sourcePayload = $payload;
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
            Action::make('load_reviewed')
                ->label(__('admin.research_workspace.actions.load_reviewed', [], 'ar') !== 'admin.research_workspace.actions.load_reviewed' ? __('admin.research_workspace.actions.load_reviewed') : 'تحميل المحتوى المعتمد')
                ->icon('heroicon-o-arrow-path')
                ->color('warning')
                ->visible(fn (): bool => $this->formTargetKey() === 'research.index')
                ->requiresConfirmation()
                ->modalHeading('تحميل المحتوى المعتمد')
                ->modalDescription('سيتم ملء النموذج بالمحتوى البحثي المعتمد في database/content/research-landing.json.')
                ->action(function (): void {
                    $path = database_path('content/research-landing.json');
                    if (is_file($path)) {
                        $payload = json_decode((string) file_get_contents($path), true);
                        if (is_array($payload) && is_array($payload['translations'] ?? null)) {
                            $this->data['ar_landing'] = $payload['translations']['ar'] ?? [];
                            $this->data['en_landing'] = $payload['translations']['en'] ?? [];
                            $this->form->fill($this->data);
                            Notification::make()
                                ->title('تم تحميل المحتوى المعتمد بنجاح')
                                ->success()
                                ->send();
                        }
                    }
                }),
            Action::make('save')->label(__('admin.research_workspace.actions.save_draft'))->icon('heroicon-o-check')->color('gray')->action(function (): void {
                $this->save();
            }),
            Action::make('preview_ar')->label(__('admin.research_workspace.actions.preview_ar'))->icon('heroicon-o-eye')->color('info')->action(function (): void {
                $this->openPreview('ar');
            }),
            Action::make('preview_en')->label(__('admin.research_workspace.actions.preview_en'))->icon('heroicon-o-eye')->color('info')->action(function (): void {
                $this->openPreview('en');
            }),
            Action::make('publish')
                ->label(__('admin.research_workspace.actions.publish'))
                ->icon('heroicon-o-paper-airplane')
                ->color('success')
                ->requiresConfirmation()
                ->modalHeading(__('admin.research_workspace.confirm.publish_heading'))
                ->modalDescription(__('admin.research_workspace.confirm.publish_description'))
                ->visible(fn (): bool => Gate::allows('publish-content'))
                ->action(function (): void {
                    $this->publish();
                }),
            Action::make('schedule')
                ->label(__('admin.research_workspace.actions.schedule'))
                ->icon('heroicon-o-clock')
                ->color('warning')
                ->form([
                    DateTimePicker::make('publish_at')->label(__('admin.research_workspace.fields.publish_at'))->required()->minDate(now())->native(false),
                ])
                ->visible(fn (): bool => Gate::allows('publish-content'))
                ->action(function (array $data): void {
                    $this->schedule((string) $data['publish_at']);
                }),
            Action::make('unpublish')
                ->label(__('admin.research_workspace.actions.unpublish'))
                ->icon('heroicon-o-x-circle')
                ->color('danger')
                ->requiresConfirmation()
                ->modalHeading(__('admin.research_workspace.confirm.unpublish_heading'))
                ->modalDescription(__('admin.research_workspace.confirm.unpublish_description'))
                ->visible(fn (): bool => Gate::allows('publish-content'))
                ->action(function (): void {
                    $this->unpublish();
                }),
        ];
    }

    /** @return list<array{key: string, label: string, description: string, url: string, active: bool}> */
    public function getWorkspaceTasks(): array
    {
        $currentTarget = $this->currentTargetKey();

        return collect($this->targetOptions())
            ->map(fn (string $label, string $key): array => [
                'key' => $key,
                'label' => $label,
                'description' => __('admin.research_workspace.descriptions.'.str_replace(['research.', '.'], ['', '_'], $key)),
                'url' => static::getUrl(['target' => $key]),
                'active' => $key === $currentTarget,
            ])
            ->values()
            ->all();
    }

    public function save(): void
    {
        /** @var User $user */
        $user = auth()->user();

        try {
            $targetKey = $this->currentTargetKey();
            $draft = $this->cmsWorkflowService->saveDraft($targetKey, $this->payloadFromForm($this->currentFormData()), (int) $user->id, $this->draftVersion);
            $this->draftVersion = $draft->version;

            Notification::make()->title(__('admin.research_workspace.notifications.draft_saved'))->success()->send();
        } catch (ConflictException $e) {
            $this->draftVersion = $e->currentVersion;
            Notification::make()->title(__('admin.research_workspace.notifications.conflict'))->body(__('admin.research_workspace.notifications.conflict_description'))->danger()->persistent()->send();
        } catch (\Throwable $e) {
            report($e);
            Notification::make()->title(__('admin.research_workspace.notifications.save_failed'))->body(__('admin.research_workspace.notifications.safe_error'))->danger()->send();
        }
    }

    public function openPreview(string $locale): void
    {
        if (! in_array($locale, ['ar', 'en'], true)) {
            Notification::make()->title(__('admin.research_workspace.notifications.preview_failed'))->body(__('admin.research_workspace.notifications.safe_error'))->danger()->send();

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
            Notification::make()->title(__('admin.research_workspace.notifications.conflict'))->body(__('admin.research_workspace.notifications.conflict_description'))->danger()->persistent()->send();
        } catch (\Throwable $e) {
            report($e);
            Notification::make()->title(__('admin.research_workspace.notifications.preview_failed'))->body(__('admin.research_workspace.notifications.safe_error'))->danger()->send();
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

            Notification::make()->title(__('admin.research_workspace.notifications.published'))->success()->send();
        } catch (ValidationException $e) {
            Notification::make()->title(__('admin.research_workspace.notifications.publish_failed'))->body($this->formatValidationErrors($e->errors()))->danger()->persistent()->send();
        } catch (\Throwable $e) {
            report($e);
            Notification::make()->title(__('admin.research_workspace.notifications.publish_failed'))->body(__('admin.research_workspace.notifications.safe_error'))->danger()->send();
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

            Notification::make()->title(__('admin.research_workspace.notifications.scheduled'))->success()->send();
        } catch (ValidationException $e) {
            Notification::make()->title(__('admin.research_workspace.notifications.schedule_failed'))->body($this->formatValidationErrors($e->errors()))->danger()->persistent()->send();
        } catch (\Throwable $e) {
            report($e);
            Notification::make()->title(__('admin.research_workspace.notifications.schedule_failed'))->body(__('admin.research_workspace.notifications.safe_error'))->danger()->send();
        }
    }

    public function unpublish(): void
    {
        /** @var User $user */
        $user = auth()->user();

        try {
            $result = $this->cmsWorkflowService->unpublish($this->currentTargetKey(), (int) $user->id);
            $notification = Notification::make()->title($result
                ? __('admin.research_workspace.notifications.unpublished')
                : __('admin.research_workspace.notifications.nothing_published'));

            ($result ? $notification->success() : $notification->warning())->send();
        } catch (\Throwable $e) {
            report($e);
            Notification::make()->title(__('admin.research_workspace.notifications.unpublish_failed'))->body(__('admin.research_workspace.notifications.safe_error'))->danger()->send();
        }
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
        $targetKey = (string) ($this->data['target_key'] ?? 'research.index');
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
            Section::make($this->sectionLabel('landing_hero'))->schema([
                TextInput::make($prefix.'.hero.eyebrow')->label($this->fieldLabel('eyebrow'))->required()->maxLength(160),
                TextInput::make($prefix.'.hero.title')->label($this->fieldLabel('title'))->required()->maxLength(180),
                MediaPicker::image($prefix.'.hero.backgroundImage', $this->fieldLabel('background_image'), true),
                Textarea::make($prefix.'.hero.summary')->label($this->fieldLabel('summary'))->required()->rows(2)->columnSpanFull(),
                TextInput::make($prefix.'.hero.cta1')->label($this->fieldLabel('primary_cta'))->required()->maxLength(120),
                PageUrlSelect::make($prefix.'.hero.cta1Url', $this->fieldLabel('primary_cta_url'), $locale, true),
                TextInput::make($prefix.'.hero.cta2')->label($this->fieldLabel('secondary_cta'))->required()->maxLength(120),
                PageUrlSelect::make($prefix.'.hero.cta2Url', $this->fieldLabel('secondary_cta_url'), $locale, true),
            ])->columns(2),
            Section::make($this->sectionLabel('landing_stats'))->schema([
                Repeater::make($prefix.'.stats')
                    ->label($this->fieldLabel('statistics'))
                    ->schema([
                        TextInput::make('value')->label($this->fieldLabel('value'))->required()->maxLength(60),
                        TextInput::make('label')->label($this->fieldLabel('label'))->required()->maxLength(140),
                    ])
                    ->columns(2)
                    ->defaultItems(0)
                    ->reorderable()
                    ->collapsible()
                    ->columnSpanFull(),
            ]),
            Section::make($this->sectionLabel('featured_publication'))->schema([
                TextInput::make($prefix.'.featuredPublication.sectionTitle')->label($this->fieldLabel('section_title'))->required()->maxLength(160),
                TextInput::make($prefix.'.featuredPublication.eyebrow')->label($this->fieldLabel('eyebrow'))->required()->maxLength(160),
                TextInput::make($prefix.'.featuredPublication.title')->label($this->fieldLabel('title'))->required()->maxLength(240)->columnSpanFull(),
                Textarea::make($prefix.'.featuredPublication.summary')->label($this->fieldLabel('summary'))->required()->rows(2)->columnSpanFull(),
                MediaPicker::image($prefix.'.featuredPublication.image', $this->fieldLabel('featured_image'), true),
                TextInput::make($prefix.'.featuredPublication.authorLabel')->label($this->fieldLabel('author_label'))->required()->maxLength(80),
                TextInput::make($prefix.'.featuredPublication.authorName')->label($this->fieldLabel('author_name'))->required()->maxLength(160),
                TextInput::make($prefix.'.featuredPublication.affiliationLabel')->label($this->fieldLabel('affiliation_label'))->required()->maxLength(80),
                TextInput::make($prefix.'.featuredPublication.affiliation')->label($this->fieldLabel('affiliation'))->required()->maxLength(160),
                TextInput::make($prefix.'.featuredPublication.publishedLabel')->label($this->fieldLabel('published_label'))->required()->maxLength(80),
                TextInput::make($prefix.'.featuredPublication.date')->label($this->fieldLabel('date'))->required()->maxLength(120),
                TextInput::make($prefix.'.featuredPublication.viewCta')->label($this->fieldLabel('view_cta'))->required()->maxLength(120),
                TextInput::make($prefix.'.featuredPublication.downloadCta')->label($this->fieldLabel('download_cta'))->required()->maxLength(120),
                Section::make($this->sectionLabel('advanced'))->schema([
                    TextInput::make($prefix.'.featuredPublication.slug')->label($this->fieldLabel('publication_slug'))->required()->maxLength(160),
                    TextInput::make($prefix.'.featuredPublication.doiLabel')->label($this->fieldLabel('doi_label'))->required()->maxLength(120),
                    TextInput::make($prefix.'.featuredPublication.doi')->label($this->fieldLabel('doi'))->maxLength(180),
                ])->columns(2)->collapsible()->collapsed()->columnSpanFull(),
            ])->columns(2),
            Section::make($this->sectionLabel('research_gateway'))->schema([
                TextInput::make($prefix.'.gateway.sectionTitle')->label($this->fieldLabel('section_title'))->required()->maxLength(180),
                Repeater::make($prefix.'.gateway.cards')
                    ->label($this->fieldLabel('gateway_cards'))
                    ->schema([
                        TextInput::make('number')->label($this->fieldLabel('number'))->required()->maxLength(20),
                        TextInput::make('title')->label($this->fieldLabel('title'))->required()->maxLength(160),
                        Textarea::make('summary')->label($this->fieldLabel('summary'))->required()->rows(2)->columnSpanFull(),
                        TextInput::make('cta')->label($this->fieldLabel('cta_label'))->required()->maxLength(120),
                        PageUrlSelect::make('url', $this->fieldLabel('url'), $locale, true),
                    ])
                    ->columns(2)
                    ->defaultItems(0)
                    ->reorderable()
                    ->collapsible()
                    ->columnSpanFull(),
            ]),
        ];

        return $this->targetSections($sections, 'research.index', $locale);
    }

    /** @return array<int, Section> */
    private function publicationFields(string $locale): array
    {
        $prefix = $locale.'_publications';

        $sections = [
            Section::make($this->sectionLabel('publications_hero'))->schema([
                TextInput::make($prefix.'.hero.eyebrow')->label($this->fieldLabel('eyebrow'))->required()->maxLength(160),
                TextInput::make($prefix.'.hero.title')->label($this->fieldLabel('title'))->required()->maxLength(180),
                MediaPicker::image($prefix.'.hero.backgroundImage', $this->fieldLabel('background_image'), true),
                Textarea::make($prefix.'.hero.summary')->label($this->fieldLabel('summary'))->required()->rows(2)->columnSpanFull(),
            ])->columns(2),

            Section::make($this->sectionLabel('filters'))->schema([
                TextInput::make($prefix.'.filters.facultyLabel')->label($this->fieldLabel('faculty_filter_label'))->required()->maxLength(80),
                TextInput::make($prefix.'.filters.typeLabel')->label($this->fieldLabel('type_filter_label'))->required()->maxLength(80),
                TextInput::make($prefix.'.filters.yearLabel')->label($this->fieldLabel('year_filter_label'))->required()->maxLength(80),
                TextInput::make($prefix.'.filters.searchPlaceholder')->label($this->fieldLabel('search_placeholder'))->required()->maxLength(160),
                Repeater::make($prefix.'.filters.faculties')->label($this->fieldLabel('faculty_options'))->schema($this->optionFields())->columns(2)->defaultItems(0)->reorderable()->collapsible()->columnSpanFull(),
                Repeater::make($prefix.'.filters.publicationTypes')->label($this->fieldLabel('publication_type_options'))->schema($this->optionFields())->columns(2)->defaultItems(0)->reorderable()->collapsible()->columnSpanFull(),
                Repeater::make($prefix.'.filters.years')->label($this->fieldLabel('year_options'))->schema($this->optionFields())->columns(2)->defaultItems(0)->reorderable()->collapsible()->columnSpanFull(),
            ])->columns(2)->collapsible()->collapsed(),

            Section::make($this->sectionLabel('publication_items'))->schema([
                Repeater::make($prefix.'.items')
                    ->label($this->fieldLabel('publications'))
                    ->schema($this->publicationItemFields())
                    ->defaultItems(0)
                    ->reorderable()
                    ->cloneable()
                    ->collapsible()
                    ->itemLabel(fn (array $state): ?string => $state['title'] ?? $state['slug'] ?? null)
                    ->columnSpanFull(),
            ])->collapsible()->collapsed(),
        ];

        return $this->targetSections($sections, 'research.publications', $locale);
    }

    /** @return array<int, Section> */
    private function centerFields(string $locale): array
    {
        $prefix = $locale.'_centers';
        $sections = [
            Section::make($this->sectionLabel('centers_hero'))->schema([
                TextInput::make($prefix.'.hero.title')->label($this->fieldLabel('title'))->required()->maxLength(180),
                MediaPicker::image($prefix.'.hero.backgroundImage', $this->fieldLabel('background_image'), true),
                Textarea::make($prefix.'.hero.summary')->label($this->fieldLabel('summary'))->required()->rows(2)->columnSpanFull(),
                TextInput::make($prefix.'.hero.primaryCta')->label($this->fieldLabel('primary_cta'))->required()->maxLength(120),
                TextInput::make($prefix.'.hero.secondaryCta')->label($this->fieldLabel('secondary_cta'))->required()->maxLength(120),
                PageUrlSelect::make($prefix.'.hero.secondaryCtaUrl', $this->fieldLabel('secondary_cta_url'), $locale, true)->columnSpanFull(),
                Repeater::make($prefix.'.hero.breadcrumbs')
                    ->label($this->fieldLabel('breadcrumbs'))
                    ->schema([
                        TextInput::make('label')->label($this->fieldLabel('label'))->required()->maxLength(120),
                        PageUrlSelect::make('url', $this->fieldLabel('url'), $locale, true),
                    ])
                    ->columns(2)
                    ->defaultItems(0)
                    ->reorderable()
                    ->collapsible()
                    ->columnSpanFull(),
            ])->columns(2),
            Section::make($this->sectionLabel('centers_introduction'))->schema([
                TextInput::make($prefix.'.intro.title')->label($this->fieldLabel('title'))->required()->maxLength(180),
                Textarea::make($prefix.'.intro.summary')->label($this->fieldLabel('summary'))->required()->rows(3)->columnSpanFull(),
                Repeater::make($prefix.'.intro.highlights')
                    ->label($this->fieldLabel('highlights'))
                    ->schema([
                        TextInput::make('title')->label($this->fieldLabel('title'))->required()->maxLength(180),
                        MediaPicker::image('icon', $this->fieldLabel('icon'), true),
                        Textarea::make('summary')->label($this->fieldLabel('summary'))->required()->rows(2)->columnSpanFull(),
                    ])
                    ->columns(2)
                    ->defaultItems(0)
                    ->reorderable()
                    ->cloneable()
                    ->collapsible()
                    ->itemLabel(fn (array $state): ?string => $state['title'] ?? null)
                    ->columnSpanFull(),
            ])->columns(2),
            Section::make($this->sectionLabel('research_centers'))->schema([
                Repeater::make($prefix.'.items')
                    ->label($this->fieldLabel('centers'))
                    ->schema($this->centerItemFields())
                    ->defaultItems(0)
                    ->reorderable()
                    ->cloneable()
                    ->collapsible()
                    ->itemLabel(fn (array $state): ?string => $state['name'] ?? $state['slug'] ?? null)
                    ->columnSpanFull(),
            ])->collapsible()->collapsed(),
            Section::make($this->sectionLabel('research_laboratories'))->schema([
                TextInput::make($prefix.'.laboratories.title')->label($this->fieldLabel('title'))->required()->maxLength(180),
                Repeater::make($prefix.'.laboratories.items')
                    ->label($this->fieldLabel('laboratories'))
                    ->schema($this->laboratoryItemFields())
                    ->defaultItems(0)
                    ->reorderable()
                    ->cloneable()
                    ->collapsible()
                    ->itemLabel(fn (array $state): ?string => $state['title'] ?? $state['slug'] ?? null)
                    ->columnSpanFull(),
            ])->collapsible()->collapsed(),
        ];

        return $this->targetSections($sections, 'research.centers', $locale);
    }

    /** @return array<int, Section> */
    private function projectFields(string $locale): array
    {
        $prefix = $locale.'_projects';
        $sections = [
            Section::make($this->sectionLabel('projects_hero'))->schema([
                TextInput::make($prefix.'.hero.eyebrow')->label($this->fieldLabel('eyebrow'))->required()->maxLength(160),
                TextInput::make($prefix.'.hero.title')->label($this->fieldLabel('title'))->required()->maxLength(180),
                MediaPicker::image($prefix.'.hero.backgroundImage', $this->fieldLabel('background_image'), true),
                Textarea::make($prefix.'.hero.summary')->label($this->fieldLabel('summary'))->required()->rows(2)->columnSpanFull(),
                Repeater::make($prefix.'.hero.breadcrumbs')->label($this->fieldLabel('breadcrumbs'))->schema([
                    TextInput::make('label')->label($this->fieldLabel('label'))->required()->maxLength(120),
                    PageUrlSelect::make('url', $this->fieldLabel('url'), $locale, true),
                ])->columns(2)->defaultItems(0)->reorderable()->collapsible()->columnSpanFull(),
            ])->columns(2),
            Section::make($this->sectionLabel('project_filters'))->schema([
                TextInput::make($prefix.'.filters.statusLabel')->label($this->fieldLabel('status_filter_label'))->required()->maxLength(100),
                TextInput::make($prefix.'.filters.facultyLabel')->label($this->fieldLabel('faculty_filter_label'))->required()->maxLength(100),
                TextInput::make($prefix.'.filters.themeLabel')->label($this->fieldLabel('theme_filter_label'))->required()->maxLength(100),
                TextInput::make($prefix.'.filters.searchPlaceholder')->label($this->fieldLabel('search_placeholder'))->required()->maxLength(180),
                Repeater::make($prefix.'.filters.statuses')->label($this->fieldLabel('status_options'))->schema($this->optionFields())->columns(2)->defaultItems(0)->reorderable()->collapsible()->columnSpanFull(),
                Repeater::make($prefix.'.filters.faculties')->label($this->fieldLabel('faculty_options'))->schema($this->optionFields())->columns(2)->defaultItems(0)->reorderable()->collapsible()->columnSpanFull(),
                Repeater::make($prefix.'.filters.themes')->label($this->fieldLabel('theme_options'))->schema($this->optionFields())->columns(2)->defaultItems(0)->reorderable()->collapsible()->columnSpanFull(),
            ])->columns(2)->collapsible()->collapsed(),
            Section::make($this->sectionLabel('project_card_labels'))->schema([
                TextInput::make($prefix.'.cardLabels.viewProject')->label($this->fieldLabel('view_project_label'))->required()->maxLength(120),
                TextInput::make($prefix.'.cardLabels.since')->label($this->fieldLabel('since_label'))->required()->maxLength(80),
            ])->columns(2),
            Section::make($this->sectionLabel('research_projects'))->schema([
                Repeater::make($prefix.'.items')
                    ->label($this->fieldLabel('projects'))
                    ->schema($this->projectItemFields())
                    ->defaultItems(0)
                    ->reorderable()
                    ->cloneable()
                    ->collapsible()
                    ->itemLabel(fn (array $state): ?string => $state['title'] ?? $state['slug'] ?? null)
                    ->columnSpanFull(),
            ])->collapsible()->collapsed(),
        ];

        return $this->targetSections($sections, 'research.projects', $locale);
    }

    /** @return array<int, Section> */
    private function themeFields(string $locale): array
    {
        $prefix = $locale.'_themes';
        $sections = [
            Section::make($this->sectionLabel('themes_hero'))->schema([
                TextInput::make($prefix.'.hero.eyebrow')->label($this->fieldLabel('eyebrow'))->required()->maxLength(160),
                TextInput::make($prefix.'.hero.title')->label($this->fieldLabel('title'))->required()->maxLength(180),
                MediaPicker::image($prefix.'.hero.backgroundImage', $this->fieldLabel('background_image'), true),
                Textarea::make($prefix.'.hero.summary')->label($this->fieldLabel('summary'))->required()->rows(2)->columnSpanFull(),
                Repeater::make($prefix.'.hero.breadcrumbs')->label($this->fieldLabel('breadcrumbs'))->schema([
                    TextInput::make('label')->label($this->fieldLabel('label'))->required()->maxLength(120),
                    PageUrlSelect::make('url', $this->fieldLabel('url'), $locale, true),
                ])->columns(2)->defaultItems(0)->reorderable()->collapsible()->columnSpanFull(),
            ])->columns(2),
            Section::make($this->sectionLabel('research_themes'))->schema([
                Repeater::make($prefix.'.items')
                    ->label($this->fieldLabel('themes'))
                    ->schema($this->themeItemFields())
                    ->defaultItems(0)
                    ->reorderable()
                    ->cloneable()
                    ->collapsible()
                    ->itemLabel(fn (array $state): ?string => $state['name'] ?? $state['slug'] ?? null)
                    ->columnSpanFull(),
            ])->collapsible()->collapsed(),
        ];

        return $this->targetSections($sections, 'research.themes', $locale);
    }

    /** @return array<int, Section> */
    private function expertFields(string $locale): array
    {
        $prefix = $locale.'_experts';
        $sections = [
            Section::make($this->sectionLabel('expert_finder_hero'))->schema([
                TextInput::make($prefix.'.hero.eyebrow')->label($this->fieldLabel('eyebrow'))->required()->maxLength(160),
                TextInput::make($prefix.'.hero.title')->label($this->fieldLabel('title'))->required()->maxLength(180),
                MediaPicker::image($prefix.'.hero.backgroundImage', $this->fieldLabel('background_image'), true),
                Textarea::make($prefix.'.hero.summary')->label($this->fieldLabel('summary'))->required()->rows(2)->columnSpanFull(),
                TextInput::make($prefix.'.searchPlaceholder')->label($this->fieldLabel('search_placeholder'))->required()->maxLength(180)->columnSpanFull(),
            ])->columns(2),
            Section::make($this->sectionLabel('expert_filters'))->schema([
                TextInput::make($prefix.'.filters.allFaculties')->label($this->fieldLabel('all_faculties_label'))->required()->maxLength(100),
                TextInput::make($prefix.'.filters.allExpertise')->label($this->fieldLabel('all_expertise_label'))->required()->maxLength(100),
                Repeater::make($prefix.'.faculties')->label($this->fieldLabel('faculty_options'))->schema([
                    Hidden::make('id')->dehydrated(),
                    TextInput::make('name')->label($this->fieldLabel('faculty_name'))->required()->maxLength(160),
                ])->columns(2)->defaultItems(0)->reorderable()->collapsible()->columnSpanFull(),
                Repeater::make($prefix.'.expertiseAreas')->label($this->fieldLabel('expertise_filters'))->schema([
                    Hidden::make('id')->dehydrated(),
                    TextInput::make('name')->label($this->fieldLabel('expertise_name'))->required()->maxLength(160),
                ])->columns(2)->defaultItems(0)->reorderable()->collapsible()->columnSpanFull(),
            ])->columns(2)->collapsible()->collapsed(),
            Section::make($this->sectionLabel('expert_profiles'))->schema([
                Repeater::make($prefix.'.researchers')
                    ->label($this->fieldLabel('researchers'))
                    ->schema($this->expertItemFields($locale))
                    ->defaultItems(0)
                    ->reorderable()
                    ->cloneable()
                    ->collapsible()
                    ->itemLabel(fn (array $state): ?string => $state['name'] ?? $state['slug'] ?? null)
                    ->columnSpanFull(),
            ])->collapsible()->collapsed(),
        ];

        return $this->targetSections($sections, 'research.experts', $locale);
    }

    /** @return array<int, Section> */
    private function conferenceFields(string $locale): array
    {
        $prefix = $locale.'_conferences';
        $sections = [
            Section::make($this->sectionLabel('conferences_hero'))->schema([
                TextInput::make($prefix.'.hero.eyebrow')->label($this->fieldLabel('eyebrow'))->required()->maxLength(160),
                TextInput::make($prefix.'.hero.title')->label($this->fieldLabel('title'))->required()->maxLength(180),
                MediaPicker::image($prefix.'.hero.backgroundImage', $this->fieldLabel('background_image'), true),
                Textarea::make($prefix.'.hero.summary')->label($this->fieldLabel('summary'))->required()->rows(2)->columnSpanFull(),
            ])->columns(2),
            Section::make($this->sectionLabel('upcoming_events'))->schema([
                TextInput::make($prefix.'.upcomingSection.title')->label($this->fieldLabel('section_title'))->required()->maxLength(180),
                Repeater::make($prefix.'.upcoming')
                    ->label($this->fieldLabel('upcoming_events'))
                    ->schema($this->conferenceEventFields(true))
                    ->defaultItems(0)
                    ->reorderable()
                    ->cloneable()
                    ->collapsible()
                    ->itemLabel(fn (array $state): ?string => $state['title'] ?? $state['id'] ?? null)
                    ->columnSpanFull(),
            ])->columns(2)->collapsible()->collapsed(),
            Section::make($this->sectionLabel('past_conferences'))->schema([
                TextInput::make($prefix.'.pastSection.title')->label($this->fieldLabel('section_title'))->required()->maxLength(180),
                TextInput::make($prefix.'.pastSection.proceedings')->label($this->fieldLabel('proceedings_label'))->maxLength(120),
                Repeater::make($prefix.'.past')
                    ->label($this->fieldLabel('past_conferences'))
                    ->schema($this->conferenceEventFields(false))
                    ->defaultItems(0)
                    ->reorderable()
                    ->cloneable()
                    ->collapsible()
                    ->itemLabel(fn (array $state): ?string => $state['title'] ?? $state['id'] ?? null)
                    ->columnSpanFull(),
            ])->columns(2)->collapsible()->collapsed(),
        ];

        return $this->targetSections($sections, 'research.conferences', $locale);
    }

    /** @return array<int, Section> */
    private function libraryFields(string $locale): array
    {
        $prefix = $locale.'_library';
        $sections = [
            Section::make($this->sectionLabel('library_hero'))->schema([
                TextInput::make($prefix.'.hero.eyebrow')->label($this->fieldLabel('eyebrow'))->required()->maxLength(160),
                TextInput::make($prefix.'.hero.title')->label($this->fieldLabel('title'))->required()->maxLength(180),
                MediaPicker::image($prefix.'.hero.backgroundImage', $this->fieldLabel('background_image'), true),
                Textarea::make($prefix.'.hero.summary')->label($this->fieldLabel('summary'))->required()->rows(2)->columnSpanFull(),
            ])->columns(2),
            Section::make($this->sectionLabel('digital_resources'))->schema([
                TextInput::make($prefix.'.resourcesSection.title')->label($this->fieldLabel('title'))->required()->maxLength(180),
                TextInput::make($prefix.'.resourcesSection.subtitle')->label($this->fieldLabel('subtitle'))->maxLength(220)->columnSpanFull(),
                Repeater::make($prefix.'.databases')
                    ->label($this->fieldLabel('databases'))
                    ->schema([
                        TextInput::make('name')->label($this->fieldLabel('name'))->required()->maxLength(180),
                        TextInput::make('accessType')->label($this->fieldLabel('access_type'))->required()->maxLength(120),
                        PageUrlSelect::make('url', $this->fieldLabel('url'), null, true)->columnSpanFull(),
                        Textarea::make('description')->label($this->fieldLabel('description'))->required()->rows(2)->columnSpanFull(),
                    ])
                    ->columns(2)
                    ->defaultItems(0)
                    ->reorderable()
                    ->cloneable()
                    ->collapsible()
                    ->itemLabel(fn (array $state): ?string => $state['name'] ?? null)
                    ->columnSpanFull(),
            ])->columns(2)->collapsible()->collapsed(),
            Section::make($this->sectionLabel('borrowing_rules'))->schema([
                TextInput::make($prefix.'.borrowingSection.title')->label($this->fieldLabel('title'))->required()->maxLength(180),
                Repeater::make($prefix.'.borrowingSection.rules')
                    ->label($this->fieldLabel('rules'))
                    ->schema($this->titleDescriptionFields())
                    ->columns(2)
                    ->defaultItems(0)
                    ->reorderable()
                    ->cloneable()
                    ->collapsible()
                    ->itemLabel(fn (array $state): ?string => $state['title'] ?? null)
                    ->columnSpanFull(),
            ])->collapsible()->collapsed(),
            Section::make($this->sectionLabel('special_collections'))->schema([
                TextInput::make($prefix.'.specialCollections.title')->label($this->fieldLabel('title'))->required()->maxLength(180),
                Repeater::make($prefix.'.specialCollections.items')
                    ->label($this->fieldLabel('collections'))
                    ->schema($this->titleDescriptionFields())
                    ->columns(2)
                    ->defaultItems(0)
                    ->reorderable()
                    ->cloneable()
                    ->collapsible()
                    ->itemLabel(fn (array $state): ?string => $state['title'] ?? null)
                    ->columnSpanFull(),
            ])->collapsible()->collapsed(),
            Section::make($this->sectionLabel('librarian_contact'))->schema([
                TextInput::make($prefix.'.librarianSection.title')->label($this->fieldLabel('title'))->required()->maxLength(180),
                TextInput::make($prefix.'.librarianSection.name')->label($this->fieldLabel('name'))->required()->maxLength(180),
                TextInput::make($prefix.'.librarianSection.hours')->label($this->fieldLabel('hours'))->required()->maxLength(180),
                TextInput::make($prefix.'.librarianSection.email')->label($this->fieldLabel('email'))->email()->required()->maxLength(180),
                TextInput::make($prefix.'.librarianSection.phone')->label($this->fieldLabel('phone'))->required()->maxLength(80),
            ])->columns(2),
        ];

        return $this->targetSections($sections, 'research.library', $locale);
    }

    /** @return array<int, Section> */
    private function officeFields(string $locale): array
    {
        $prefix = $locale.'_office';
        $sections = [
            Section::make($this->sectionLabel('office_hero'))->schema([
                TextInput::make($prefix.'.hero.eyebrow')->label($this->fieldLabel('eyebrow'))->required()->maxLength(160),
                TextInput::make($prefix.'.hero.title')->label($this->fieldLabel('title'))->required()->maxLength(180),
                MediaPicker::image($prefix.'.hero.backgroundImage', $this->fieldLabel('background_image'), true),
                Textarea::make($prefix.'.hero.summary')->label($this->fieldLabel('summary'))->required()->rows(2)->columnSpanFull(),
            ])->columns(2),
            Section::make($this->sectionLabel('research_leadership'))->schema([
                TextInput::make($prefix.'.leadership.title')->label($this->fieldLabel('title'))->required()->maxLength(180),
                Repeater::make($prefix.'.leadership.items')
                    ->label($this->fieldLabel('leaders'))
                    ->schema([
                        TextInput::make('name')->label($this->fieldLabel('name'))->required()->maxLength(180),
                        TextInput::make('role')->label($this->fieldLabel('role'))->required()->maxLength(180),
                        TextInput::make('email')->label($this->fieldLabel('email'))->email()->required()->maxLength(180),
                        MediaPicker::image('image', $this->fieldLabel('profile_image'), true)->columnSpanFull(),
                    ])
                    ->columns(2)
                    ->defaultItems(0)
                    ->reorderable()
                    ->cloneable()
                    ->collapsible()
                    ->itemLabel(fn (array $state): ?string => $state['name'] ?? null)
                    ->columnSpanFull(),
            ])->collapsible()->collapsed(),
            Section::make($this->sectionLabel('office_services'))->schema([
                TextInput::make($prefix.'.services.title')->label($this->fieldLabel('title'))->required()->maxLength(180),
                TextInput::make($prefix.'.services.subtitle')->label($this->fieldLabel('subtitle'))->maxLength(220)->columnSpanFull(),
                Repeater::make($prefix.'.services.items')
                    ->label($this->fieldLabel('services'))
                    ->schema($this->titleDescriptionFields())
                    ->columns(2)
                    ->defaultItems(0)
                    ->reorderable()
                    ->cloneable()
                    ->collapsible()
                    ->itemLabel(fn (array $state): ?string => $state['title'] ?? null)
                    ->columnSpanFull(),
            ])->columns(2)->collapsible()->collapsed(),
            Section::make($this->sectionLabel('office_statistics'))->schema([
                TextInput::make($prefix.'.statistics.title')->label($this->fieldLabel('title'))->required()->maxLength(180),
                Repeater::make($prefix.'.statistics.items')
                    ->label($this->fieldLabel('statistics'))
                    ->schema([
                        TextInput::make('value')->label($this->fieldLabel('value'))->required()->maxLength(80),
                        TextInput::make('label')->label($this->fieldLabel('label'))->required()->maxLength(160),
                    ])
                    ->columns(2)
                    ->defaultItems(0)
                    ->reorderable()
                    ->cloneable()
                    ->collapsible()
                    ->itemLabel(fn (array $state): ?string => $state['label'] ?? null)
                    ->columnSpanFull(),
            ])->collapsible()->collapsed(),
            Section::make($this->sectionLabel('office_contact'))->schema([
                TextInput::make($prefix.'.contact.title')->label($this->fieldLabel('title'))->required()->maxLength(180),
                TextInput::make($prefix.'.contact.address')->label($this->fieldLabel('address'))->required()->maxLength(180),
                TextInput::make($prefix.'.contact.addressDetail')->label($this->fieldLabel('address_detail'))->required()->maxLength(255)->columnSpanFull(),
                TextInput::make($prefix.'.contact.email')->label($this->fieldLabel('email'))->email()->required()->maxLength(180),
                TextInput::make($prefix.'.contact.phone')->label($this->fieldLabel('phone'))->required()->maxLength(80),
                TextInput::make($prefix.'.contact.hours')->label($this->fieldLabel('hours'))->required()->maxLength(180),
            ])->columns(2),
        ];

        return $this->targetSections($sections, 'research.office', $locale);
    }

    /** @return array<int, Section> */
    private function policyFields(string $locale): array
    {
        $prefix = $locale.'_policies';
        $sections = [
            Section::make($this->sectionLabel('policies_hero'))->schema([
                TextInput::make($prefix.'.hero.eyebrow')->label($this->fieldLabel('eyebrow'))->required()->maxLength(160),
                TextInput::make($prefix.'.hero.title')->label($this->fieldLabel('title'))->required()->maxLength(180),
                MediaPicker::image($prefix.'.hero.backgroundImage', $this->fieldLabel('background_image'), true),
                Textarea::make($prefix.'.hero.summary')->label($this->fieldLabel('summary'))->required()->rows(2)->columnSpanFull(),
            ])->columns(2),
            Section::make($this->sectionLabel('policy_sections'))->schema([
                Repeater::make($prefix.'.sections')
                    ->label($this->fieldLabel('policy_sections'))
                    ->schema([
                        Hidden::make('id')->dehydrated(),
                        TextInput::make('title')->label($this->fieldLabel('title'))->required()->maxLength(180),
                        Textarea::make('description')->label($this->fieldLabel('description'))->required()->rows(2)->columnSpanFull(),
                        Toggle::make('documentsUnavailable')
                            ->label($this->fieldLabel('documents_unavailable'))
                            ->helperText($this->fieldLabel('documents_unavailable_help'))
                            ->live(),
                        Repeater::make('documents')
                            ->label($this->fieldLabel('documents'))
                            ->schema([
                                TextInput::make('title')->label($this->fieldLabel('title'))->required()->maxLength(180),
                                TextInput::make('fileType')->label($this->fieldLabel('file_type'))->required()->maxLength(40),
                                MediaPicker::document('url', $this->fieldLabel('document_file'))->columnSpanFull(),
                            ])
                            ->columns(2)
                            ->defaultItems(0)
                            ->visible(fn (Get $get): bool => ! (bool) $get('documentsUnavailable'))
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
            ])->collapsible()->collapsed(),
            Section::make($this->sectionLabel('policy_contact'))->schema([
                TextInput::make($prefix.'.contactSection.title')->label($this->fieldLabel('title'))->required()->maxLength(180),
                Textarea::make($prefix.'.contactSection.description')->label($this->fieldLabel('description'))->required()->rows(2)->columnSpanFull(),
                TextInput::make($prefix.'.contactSection.email')->label($this->fieldLabel('email'))->email()->required()->maxLength(180),
                TextInput::make($prefix.'.contactSection.phone')->label($this->fieldLabel('phone'))->required()->maxLength(80),
                TextInput::make($prefix.'.contactSection.location')->label($this->fieldLabel('location'))->required()->maxLength(220)->columnSpanFull(),
            ])->columns(2),
        ];

        return $this->targetSections($sections, 'research.policies', $locale);
    }

    /** @return array<int, mixed> */
    private function optionFields(): array
    {
        return [
            Hidden::make('value')->dehydrated(),
            TextInput::make('label')->label($this->fieldLabel('label'))->required()->maxLength(160),
        ];
    }

    /** @return array<int, mixed> */
    private function titleDescriptionFields(): array
    {
        return [
            TextInput::make('title')->label($this->fieldLabel('title'))->required()->maxLength(180),
            Textarea::make('description')->label($this->fieldLabel('description'))->required()->rows(2)->columnSpanFull(),
        ];
    }

    /** @return array<int, mixed> */
    private function centerItemFields(): array
    {
        return [
            Hidden::make('id')->dehydrated(),
            Hidden::make('slug')->dehydrated(),
            TextInput::make('name')->label($this->fieldLabel('name'))->required()->maxLength(180)->columnSpanFull(),
            Textarea::make('mission')->label($this->fieldLabel('mission'))->required()->rows(3)->columnSpanFull(),
            TextInput::make('faculty')->label($this->fieldLabel('faculty'))->required()->maxLength(180),
            Select::make('facultySlug')->label($this->fieldLabel('faculty_assignment'))->required()->options($this->facultyOptions()),
            TextInput::make('directorName')->label($this->fieldLabel('director_name'))->required()->maxLength(180),
            TextInput::make('contactEmail')->label($this->fieldLabel('contact_email'))->email()->required()->maxLength(180),
            TextInput::make('contactPhone')->label($this->fieldLabel('contact_phone'))->maxLength(80),
            MediaPicker::image('image', $this->fieldLabel('center_image'), true)->columnSpanFull(),
            Section::make($this->sectionLabel('advanced'))->schema([
                TextInput::make('externalWebsite')->label($this->fieldLabel('external_website'))->url()->maxLength(255)->columnSpanFull(),
                Hidden::make('labs')->dehydrated(),
                Hidden::make('researchers')->dehydrated(),
                Hidden::make('projects')->dehydrated(),
                Hidden::make('publications')->dehydrated(),
                TagsInput::make('publicationSlugs')->label($this->fieldLabel('related_publications'))->columnSpanFull(),
                TagsInput::make('projectSlugs')->label($this->fieldLabel('related_projects'))->columnSpanFull(),
                TagsInput::make('researcherSlugs')->label($this->fieldLabel('affiliated_researchers'))->columnSpanFull(),
            ])->columns(2)->collapsible()->collapsed()->columnSpanFull(),
        ];
    }

    /** @return array<int, mixed> */
    private function laboratoryItemFields(): array
    {
        return [
            Hidden::make('id')->dehydrated(),
            Hidden::make('slug')->dehydrated(),
            TextInput::make('title')->label($this->fieldLabel('title'))->required()->maxLength(180)->columnSpanFull(),
            TextInput::make('faculty')->label($this->fieldLabel('faculty'))->required()->maxLength(180),
            TextInput::make('director')->label($this->fieldLabel('director'))->required()->maxLength(180),
            Textarea::make('summary')->label($this->fieldLabel('summary'))->required()->rows(2)->columnSpanFull(),
            TextInput::make('projects')->label($this->fieldLabel('projects'))->required()->maxLength(180),
            TextInput::make('publications')->label($this->fieldLabel('publications'))->required()->maxLength(180),
            TextInput::make('contact')->label($this->fieldLabel('contact'))->required()->maxLength(180),
            TextInput::make('cta')->label($this->fieldLabel('cta_label'))->required()->maxLength(120),
            MediaPicker::image('image', $this->fieldLabel('laboratory_image'), true)->columnSpanFull(),
        ];
    }

    /** @return array<int, mixed> */
    private function projectItemFields(): array
    {
        return [
            Hidden::make('id')->dehydrated(),
            Hidden::make('slug')->dehydrated(),
            TextInput::make('title')->label($this->fieldLabel('title'))->required()->maxLength(240)->columnSpanFull(),
            Textarea::make('summary')->label($this->fieldLabel('summary'))->required()->rows(3)->columnSpanFull(),
            TextInput::make('faculty')->label($this->fieldLabel('faculty'))->required()->maxLength(180),
            Select::make('facultySlug')->label($this->fieldLabel('faculty_assignment'))->required()->options($this->facultyOptions()),
            TextInput::make('theme')->label($this->fieldLabel('theme'))->required()->maxLength(180),
            Select::make('themeSlug')->label($this->fieldLabel('theme_assignment'))->required()->options($this->themeOptions()),
            Select::make('status')->label($this->fieldLabel('status'))->required()->options([
                'ongoing' => __('admin.research_workspace.options.statuses.ongoing'),
                'completed' => __('admin.research_workspace.options.statuses.completed'),
                'paused' => __('admin.research_workspace.options.statuses.paused'),
            ]),
            TextInput::make('startYear')->label($this->fieldLabel('start_year'))->required()->numeric()->minValue(1900)->maxValue(2200),
            TextInput::make('endYear')->label($this->fieldLabel('end_year'))->numeric()->minValue(1900)->maxValue(2200),
            TextInput::make('funding')->label($this->fieldLabel('funding'))->required()->maxLength(180)->columnSpanFull(),
            MediaPicker::image('image', $this->fieldLabel('project_image'), true)->columnSpanFull(),
        ];
    }

    /** @return array<int, mixed> */
    private function themeItemFields(): array
    {
        return [
            Hidden::make('id')->dehydrated(),
            Hidden::make('slug')->dehydrated(),
            TextInput::make('name')->label($this->fieldLabel('name'))->required()->maxLength(180)->columnSpanFull(),
            Textarea::make('description')->label($this->fieldLabel('description'))->required()->rows(3)->columnSpanFull(),
            MediaPicker::image('icon', $this->fieldLabel('theme_icon'), true)->columnSpanFull(),
            Hidden::make('publicationCount')->dehydrated(),
            Hidden::make('projectCount')->dehydrated(),
        ];
    }

    /** @return array<int, mixed> */
    private function publicationItemFields(): array
    {
        return [
            Hidden::make('id')->dehydrated(),
            Hidden::make('slug')->dehydrated(),
            TextInput::make('title')->label($this->fieldLabel('title'))->required()->maxLength(240)->columnSpanFull(),
            Textarea::make('summary')->label($this->fieldLabel('summary'))->required()->rows(2)->columnSpanFull(),
            TextInput::make('type')->label($this->fieldLabel('publication_type'))->required()->maxLength(120),
            TextInput::make('faculty')->label($this->fieldLabel('faculty'))->required()->maxLength(160),
            TextInput::make('author')->label($this->fieldLabel('author'))->required()->maxLength(160),
            TextInput::make('year')->label($this->fieldLabel('year'))->required()->maxLength(20),
            MediaPicker::image('image', $this->fieldLabel('publication_image'), true)->columnSpanFull(),
            Textarea::make('lead')->label($this->fieldLabel('detail_lead'))->rows(2)->columnSpanFull(),
            TagsInput::make('paragraphs')->label($this->fieldLabel('detail_paragraphs'))->columnSpanFull(),
            Textarea::make('keyStatement')->label($this->fieldLabel('key_statement'))->rows(2)->columnSpanFull(),
            TagsInput::make('keywords')->label($this->fieldLabel('keywords'))->columnSpanFull(),
            Repeater::make('downloads')
                ->label($this->fieldLabel('publication_files'))
                ->schema([
                    TextInput::make('label')->label($this->fieldLabel('label'))->required()->maxLength(180),
                    TextInput::make('type')->label($this->fieldLabel('file_type'))->maxLength(40),
                    MediaPicker::document('url', $this->fieldLabel('publication_file'), true)->columnSpanFull(),
                ])
                ->columns(2)
                ->defaultItems(0)
                ->reorderable()
                ->collapsible()
                ->columnSpanFull(),
            Hidden::make('isOpenAccess')->dehydrated(),
            Placeholder::make('open_access_guidance')->label($this->fieldLabel('open_access'))->content(__('admin.research_workspace.help.open_access'))->columnSpanFull(),
            Section::make($this->sectionLabel('advanced'))->schema([
                TextInput::make('typeSlug')->label($this->fieldLabel('publication_type_slug'))->required()->maxLength(120),
                Select::make('facultySlug')->label($this->fieldLabel('faculty_assignment'))->required()->options($this->facultyOptions()),
                TextInput::make('authorSlug')->label($this->fieldLabel('author_slug'))->required()->maxLength(160),
                TextInput::make('doi')->label($this->fieldLabel('doi'))->maxLength(180),
                TextInput::make('journalTitle')->label($this->fieldLabel('journal_proceedings'))->maxLength(180),
                TextInput::make('volume')->label($this->fieldLabel('volume'))->maxLength(40),
                TextInput::make('issue')->label($this->fieldLabel('issue'))->maxLength(40),
                TextInput::make('pages')->label($this->fieldLabel('pages'))->maxLength(40),
                TextInput::make('issn')->label($this->fieldLabel('issn'))->maxLength(40),
                TextInput::make('license')->label($this->fieldLabel('license'))->maxLength(180)->columnSpanFull(),
                TagsInput::make('themes')->label($this->fieldLabel('theme_slugs'))->columnSpanFull(),
                Repeater::make('resolvedThemes')->label($this->fieldLabel('resolved_themes'))->schema([
                    Hidden::make('slug')->dehydrated(),
                    TextInput::make('label')->label($this->fieldLabel('label'))->required()->maxLength(160),
                ])->columns(2)->defaultItems(0)->reorderable()->collapsible()->columnSpanFull(),
                TextInput::make('scholarUrl')->label($this->fieldLabel('scholar_url'))->maxLength(255)->columnSpanFull(),
                TextInput::make('scopusUrl')->label($this->fieldLabel('scopus_url'))->maxLength(255)->columnSpanFull(),
                TextInput::make('category')->label($this->fieldLabel('category'))->maxLength(120),
                TextInput::make('rate')->label($this->fieldLabel('ranking'))->maxLength(80),
                Toggle::make('gsIndexed')->label($this->fieldLabel('google_scholar_indexed')),
            ])->columns(2)->collapsible()->collapsed()->columnSpanFull(),
        ];
    }

    /** @return array<int, mixed> */
    private function expertItemFields(string $locale): array
    {
        return [
            Hidden::make('id')->dehydrated(),
            Hidden::make('slug')->dehydrated(),
            TextInput::make('name')->label($this->fieldLabel('name'))->required()->maxLength(180),
            TextInput::make('title')->label($this->fieldLabel('role'))->required()->maxLength(180),
            TextInput::make('faculty')->label($this->fieldLabel('faculty'))->required()->maxLength(180),
            Select::make('facultyId')->label($this->fieldLabel('faculty_assignment'))->required()->options($this->facultyOptions(true)),
            Hidden::make('facultySlug')->dehydrated(),
            TextInput::make('department')->label($this->fieldLabel('department'))->maxLength(180),
            Textarea::make('bio')->label($this->fieldLabel('short_biography'))->rows(2)->columnSpanFull(),
            TagsInput::make('biography')->label($this->fieldLabel('biography'))->columnSpanFull(),
            TagsInput::make('expertise')->label($this->fieldLabel('expertise'))->columnSpanFull(),
            Select::make('expertiseSlugs')->label($this->fieldLabel('expertise_filters'))->multiple()->options(fn (): array => $this->expertiseOptions($locale))->columnSpanFull(),
            TextInput::make('email')->label($this->fieldLabel('email'))->email()->maxLength(180),
            MediaPicker::image('image', $this->fieldLabel('profile_image'), true)->columnSpanFull(),
            TextInput::make('office.fullAddress')->label($this->fieldLabel('office'))->maxLength(255)->columnSpanFull(),
            Repeater::make('education')->label($this->fieldLabel('education'))->schema([
                TextInput::make('degree')->label($this->fieldLabel('degree'))->required()->maxLength(180),
                TextInput::make('institution')->label($this->fieldLabel('institution'))->maxLength(180),
                TextInput::make('year')->label($this->fieldLabel('year'))->maxLength(40),
            ])->columns(3)->defaultItems(0)->reorderable()->collapsible()->columnSpanFull(),
            Repeater::make('courses')->label($this->fieldLabel('courses'))->schema([
                Hidden::make('id')->dehydrated(),
                TextInput::make('code')->label($this->fieldLabel('course_code'))->required()->maxLength(40),
                TextInput::make('name')->label($this->fieldLabel('course_name'))->required()->maxLength(180),
                Hidden::make('departmentId')->dehydrated(),
            ])->columns(2)->defaultItems(0)->reorderable()->collapsible()->columnSpanFull(),
            Repeater::make('profilePublications')->label($this->fieldLabel('profile_publications'))->schema([
                Hidden::make('id')->dehydrated(),
                TextInput::make('title')->label($this->fieldLabel('title'))->required()->maxLength(240)->columnSpanFull(),
                TextInput::make('journal')->label($this->fieldLabel('journal'))->maxLength(180),
                TextInput::make('year')->label($this->fieldLabel('year'))->maxLength(40),
                Section::make($this->sectionLabel('advanced'))->schema([
                    TextInput::make('links.local')->label($this->fieldLabel('local_url'))->maxLength(255)->columnSpanFull(),
                    TextInput::make('links.scholar')->label($this->fieldLabel('scholar_url'))->maxLength(255)->columnSpanFull(),
                ])->collapsible()->collapsed()->columnSpanFull(),
            ])->columns(2)->defaultItems(0)->reorderable()->collapsible()->columnSpanFull(),
            Section::make($this->sectionLabel('advanced'))->schema([
                TextInput::make('orcidUrl')->label($this->fieldLabel('orcid_url'))->maxLength(255)->columnSpanFull(),
                TextInput::make('scholarUrl')->label($this->fieldLabel('scholar_url'))->maxLength(255)->columnSpanFull(),
                Hidden::make('publications')->dehydrated(),
                Hidden::make('citations')->dehydrated(),
            ])->collapsible()->collapsed()->columnSpanFull(),
        ];
    }

    /** @return array<int, mixed> */
    private function conferenceEventFields(bool $upcoming): array
    {
        $fields = [
            Hidden::make('id')->dehydrated(),
            TextInput::make('title')->label($this->fieldLabel('title'))->required()->maxLength(240)->columnSpanFull(),
            TextInput::make('date')->label($this->fieldLabel('date'))->required()->maxLength(120),
            TextInput::make('location')->label($this->fieldLabel('location'))->required()->maxLength(180),
            MediaPicker::image('image', $this->fieldLabel('event_image'), true)->columnSpanFull(),
            Textarea::make('description')->label($this->fieldLabel('description'))->required()->rows(2)->columnSpanFull(),
        ];

        if ($upcoming) {
            $fields[] = TextInput::make('eventType')->label($this->fieldLabel('event_type'))->required()->maxLength(160);
            $fields[] = Select::make('formId')->label($this->fieldLabel('registration_form'))->options([
                'conference-registration' => __('admin.research_workspace.options.registration_forms.conference'),
                'symposium-registration' => __('admin.research_workspace.options.registration_forms.symposium'),
            ])->placeholder(__('admin.research_workspace.options.registration_forms.none'));
            $fields[] = Section::make($this->sectionLabel('advanced'))->schema([
                TextInput::make('registrationUrl')->label($this->fieldLabel('external_registration_url'))->maxLength(255)->helperText(__('admin.research_workspace.help.registration_url'))->columnSpanFull(),
            ])->collapsible()->collapsed()->columnSpanFull();

            return $fields;
        }

        $fields[] = TextInput::make('participants')->label($this->fieldLabel('participants'))->maxLength(120);
        $fields[] = Toggle::make('hasProceedings')->label($this->fieldLabel('proceedings_available'));
        $fields[] = Toggle::make('proceedingsUnavailable')
            ->label($this->fieldLabel('proceedings_unavailable'))
            ->helperText($this->fieldLabel('proceedings_unavailable_help'))
            ->visible(fn (Get $get): bool => (bool) $get('hasProceedings'));
        $fields[] = MediaPicker::document('proceedingsUrl', $this->fieldLabel('proceedings_file'))
            ->visible(fn (Get $get): bool => (bool) $get('hasProceedings') && ! (bool) $get('proceedingsUnavailable'))
            ->columnSpanFull();

        return $fields;
    }

    /** @param array<string, mixed> $state @return array<string, mixed> */
    private function payloadFromForm(array $state): array
    {
        $targetKey = (string) ($state['target_key'] ?? 'research.index');
        [$stateKey, $normalizer] = match ($targetKey) {
            'research.index' => ['landing', 'normalizeLandingContent'],
            'research.centers' => ['centers', 'normalizeCenterContent'],
            'research.projects' => ['projects', 'normalizeProjectContent'],
            'research.themes' => ['themes', 'normalizeThemeContent'],
            'research.experts' => ['experts', 'normalizeExpertContent'],
            'research.conferences' => ['conferences', 'normalizeConferenceContent'],
            'research.library' => ['library', 'normalizeLibraryContent'],
            'research.office' => ['office', 'normalizeOfficeContent'],
            'research.policies' => ['policies', 'normalizePolicyContent'],
            default => ['publications', 'normalizePublicationContent'],
        };
        $payload = [
            'translations' => [
                'ar' => $this->{$normalizer}(is_array($state['ar_'.$stateKey] ?? null) ? $state['ar_'.$stateKey] : []),
                'en' => $this->{$normalizer}(is_array($state['en_'.$stateKey] ?? null) ? $state['en_'.$stateKey] : []),
            ],
        ];

        $payload = $this->synchronizeGeneratedIdentity($targetKey, $payload);

        return $this->mergePreservingLegacy($this->sourcePayload, $payload);
    }

    /** @param array<string, mixed> $content @return array<string, mixed> */
    private function normalizePublicationContent(array $content): array
    {
        $content['filters']['faculties'] = $this->listOfArrays($content['filters']['faculties'] ?? []);
        $content['filters']['publicationTypes'] = $this->listOfArrays($content['filters']['publicationTypes'] ?? []);
        $content['filters']['years'] = $this->listOfArrays($content['filters']['years'] ?? []);
        $content['items'] = array_map(function (array $item): array {
            $slug = trim((string) ($item['slug'] ?? ''));
            if ($slug !== '') {
                $item['links']['local'] = '/research/publications/'.$slug.'/';
            }
            $item['paragraphs'] = $this->listOfStrings($item['paragraphs'] ?? []);
            $item['keywords'] = $this->listOfStrings($item['keywords'] ?? []);
            $item['themes'] = $this->listOfStrings($item['themes'] ?? []);
            $item['resolvedThemes'] = $this->listOfArrays($item['resolvedThemes'] ?? []);
            $item['downloads'] = $this->listOfArrays($item['downloads'] ?? []);
            $item['isOpenAccess'] = collect($item['downloads'])->contains(
                static fn (array $download): bool => is_string($download['url'] ?? null) && trim((string) $download['url']) !== '' && $download['url'] !== '#'
            );
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
            $item['facultySlug'] = $this->canonicalFacultySlug(strtolower(trim((string) ($item['facultySlug'] ?? ''))));
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
            $formId = trim((string) ($event['formId'] ?? ''));
            $eventId = trim((string) ($event['id'] ?? ''));

            if (in_array($formId, ['conference-registration', 'symposium-registration'], true) && $eventId !== '') {
                $event['registrationUrl'] = '/research/conferences/register?event='.rawurlencode($eventId);
            } elseif (($event['registrationUrl'] ?? null) === '#') {
                $event['registrationUrl'] = null;
            }

            return $event;
        }, $this->listOfArrays($content['upcoming'] ?? []));

        $content['past'] = array_map(function (array $event): array {
            $event['hasProceedings'] = (bool) ($event['hasProceedings'] ?? false);
            $proceedingsUrl = $event['proceedingsUrl'] ?? null;
            if (! $this->isSafeResourceUrl($proceedingsUrl)) {
                $event['proceedingsUrl'] = null;
                $event['proceedingsUnavailable'] = $event['hasProceedings'];
            }

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
            $section['documentsUnavailable'] = $section['documents'] === [] || collect($section['documents'])
                ->every(fn (array $document): bool => ! $this->isSafeResourceUrl($document['url'] ?? null));

            return $section;
        }, $this->listOfArrays($content['sections'] ?? []));

        return $content;
    }

    /** @param array<string, mixed> $content @return array<string, mixed> */
    private function normalizeLandingContent(array $content): array
    {
        $featuredSlug = trim((string) ($content['featuredPublication']['slug'] ?? ''));
        $content['stats'] = $this->listOfArrays($content['stats'] ?? []);
        if ($featuredSlug !== '') {
            $content['featuredPublication']['links']['local'] = '/research/publications/'.$featuredSlug.'/';
        }
        $content['gateway']['cards'] = $this->listOfArrays($content['gateway']['cards'] ?? []);

        return $content;
    }

    /** @param array<string, mixed> $content @return array<string, mixed> */
    private function normalizeExpertContent(array $content): array
    {
        $content['faculties'] = $this->listOfArrays($content['faculties'] ?? []);
        $content['expertiseAreas'] = $this->listOfArrays($content['expertiseAreas'] ?? []);
        $expertiseIds = collect($content['expertiseAreas'])
            ->filter(static fn (array $area): bool => is_string($area['id'] ?? null) && trim((string) $area['id']) !== '')
            ->mapWithKeys(static fn (array $area): array => [mb_strtolower(trim((string) ($area['name'] ?? ''))) => trim((string) $area['id'])]);
        $content['researchers'] = array_map(function (array $item) use ($expertiseIds): array {
            $item['role'] = $item['title'] ?? $item['role'] ?? '';
            $item['description'] = $item['bio'] ?? $item['description'] ?? '';
            $item['biography'] = $this->listOfStrings($item['biography'] ?? []);
            $item['expertise'] = $this->listOfStrings($item['expertise'] ?? []);
            $item['expertiseSlugs'] = $this->listOfStrings($item['expertiseSlugs'] ?? []);
            if ($item['expertiseSlugs'] === []) {
                $item['expertiseSlugs'] = array_values(array_filter(array_map(
                    static fn (string $name): ?string => $expertiseIds->get(mb_strtolower(trim($name))),
                    $item['expertise'],
                )));
            }
            $facultyId = strtolower(trim((string) ($item['facultyId'] ?? data_get($item, 'faculty.id', ''))));
            if ($facultyId !== '') {
                $item['facultyId'] = $facultyId;
                $item['facultySlug'] = $this->canonicalFacultySlug($facultyId);
            }
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

    /** @param array<int, Section> $sections @return array<int, Section> */
    private function targetSections(array $sections, string $targetKey, string $locale): array
    {
        return array_map(
            static fn (Section $section): Section => $section
                ->extraAttributes(['dir' => $locale === 'ar' ? 'rtl' : 'ltr', 'lang' => $locale])
                ->visible(static fn (Get $get): bool => $get('target_key') === $targetKey),
            $sections,
        );
    }

    private function sectionLabel(string $key): string
    {
        return __('admin.research_workspace.sections.'.$key);
    }

    private function fieldLabel(string $key): string
    {
        return __('admin.research_workspace.editor_fields.'.$key);
    }

    /** @return array<string, string> */
    private function facultyOptions(bool $includeUniversity = false): array
    {
        $options = [];
        foreach (['medicine', 'dentistry', 'pharmacy', 'artificial-intelligence', 'building-construction-engineering', 'petroleum', 'business-administration'] as $slug) {
            $options[$slug] = __('admin.research_workspace.options.faculties.'.$slug);
        }

        if ($includeUniversity) {
            $options['university'] = __('admin.research_workspace.options.faculties.university');
        }

        $options['ai'] = $options['artificial-intelligence'];
        $options['construction'] = $options['building-construction-engineering'];
        $options['business'] = $options['business-administration'];

        return $options;
    }

    /** @return array<string, string> */
    private function expertiseOptions(string $locale): array
    {
        $areas = data_get($this->sourcePayload, 'translations.'.$locale.'.expertiseAreas', []);
        $options = [];

        foreach ($this->listOfArrays($areas) as $area) {
            $id = trim((string) ($area['id'] ?? ''));
            $name = trim((string) ($area['name'] ?? ''));
            if ($id !== '' && $name !== '') {
                $options[$id] = $name;
            }
        }

        return $options;
    }

    /** @return array<string, string> */
    private function themeOptions(): array
    {
        $options = [];
        foreach (['ai-ml', 'pharmaceutical-sciences', 'clinical-medicine', 'dental-sciences', 'petroleum-engineering', 'construction-engineering', 'business-administration', 'medical-education', 'biomedical-engineering', 'energy-systems', 'data-science', 'structural-engineering'] as $slug) {
            $options[$slug] = __('admin.research_workspace.options.themes.'.$slug);
        }

        return $options;
    }

    /** @param array<string, mixed> $payload @return array<string, mixed> */
    private function synchronizeGeneratedIdentity(string $targetKey, array $payload): array
    {
        $paths = match ($targetKey) {
            'research.publications', 'research.projects', 'research.themes' => ['items'],
            'research.centers' => ['items', 'laboratories.items'],
            'research.experts' => ['researchers'],
            'research.conferences' => ['upcoming', 'past'],
            'research.policies' => ['sections'],
            default => [],
        };
        $slugPaths = match ($targetKey) {
            'research.publications', 'research.projects', 'research.themes', 'research.centers', 'research.experts' => [$paths[0] ?? ''],
            default => [],
        };

        foreach ($paths as $path) {
            $arabicItems = $this->listOfArrays(data_get($payload, 'translations.ar.'.$path, []));
            $englishItems = $this->listOfArrays(data_get($payload, 'translations.en.'.$path, []));
            $count = max(count($arabicItems), count($englishItems));

            for ($index = 0; $index < $count; $index++) {
                $arabic = $arabicItems[$index] ?? [];
                $english = $englishItems[$index] ?? [];
                $id = $this->firstFilled([$arabic['id'] ?? null, $english['id'] ?? null]);
                if ($id === '') {
                    $identitySource = $this->firstFilled([$english['slug'] ?? null, $english['title'] ?? null, $english['name'] ?? null, $arabic['slug'] ?? null]);
                    $generated = Str::slug($identitySource);
                    $id = str_replace('research.', '', $targetKey).'-'.($generated !== '' ? $generated : str_pad((string) ($index + 1), 3, '0', STR_PAD_LEFT));
                }

                foreach ([&$arabic, &$english] as &$item) {
                    if (trim((string) ($item['id'] ?? '')) === '') {
                        $item['id'] = $id;
                    }
                }
                unset($item);

                if (in_array($path, $slugPaths, true)) {
                    $slug = $this->firstFilled([$arabic['slug'] ?? null, $english['slug'] ?? null]);
                    if ($slug === '') {
                        $slug = Str::slug($this->firstFilled([$english['title'] ?? null, $english['name'] ?? null, $arabic['title'] ?? null, $arabic['name'] ?? null, $id]));
                    }
                    foreach ([&$arabic, &$english] as &$item) {
                        if (trim((string) ($item['slug'] ?? '')) === '') {
                            $item['slug'] = $slug;
                        }
                    }
                    unset($item);
                }

                $arabicItems[$index] = $arabic;
                $englishItems[$index] = $english;
            }

            data_set($payload, 'translations.ar.'.$path, $arabicItems);
            data_set($payload, 'translations.en.'.$path, $englishItems);
        }

        $filterPaths = match ($targetKey) {
            'research.publications' => ['filters.faculties', 'filters.publicationTypes', 'filters.years'],
            'research.projects' => ['filters.statuses', 'filters.faculties', 'filters.themes'],
            default => [],
        };
        foreach ($filterPaths as $path) {
            $arabicOptions = $this->listOfArrays(data_get($payload, 'translations.ar.'.$path, []));
            $englishOptions = $this->listOfArrays(data_get($payload, 'translations.en.'.$path, []));
            $count = max(count($arabicOptions), count($englishOptions));

            for ($index = 0; $index < $count; $index++) {
                $arabic = $arabicOptions[$index] ?? [];
                $english = $englishOptions[$index] ?? [];
                $value = $this->firstFilled([$arabic['value'] ?? null, $english['value'] ?? null]);
                if ($value === '' && $index > 0) {
                    $value = Str::slug($this->firstFilled([$english['label'] ?? null, $arabic['label'] ?? null]));
                }
                if ($value !== '') {
                    $arabic['value'] = $this->firstFilled([$arabic['value'] ?? null, $value]);
                    $english['value'] = $this->firstFilled([$english['value'] ?? null, $value]);
                }
                $arabicOptions[$index] = $arabic;
                $englishOptions[$index] = $english;
            }

            data_set($payload, 'translations.ar.'.$path, $arabicOptions);
            data_set($payload, 'translations.en.'.$path, $englishOptions);
        }

        if ($targetKey === 'research.experts') {
            data_set($payload, 'translations.ar.items', data_get($payload, 'translations.ar.researchers', []));
            data_set($payload, 'translations.en.items', data_get($payload, 'translations.en.researchers', []));
        }

        return $payload;
    }

    /** @param array<int, mixed> $values */
    private function firstFilled(array $values): string
    {
        foreach ($values as $value) {
            if (is_scalar($value) && trim((string) $value) !== '') {
                return trim((string) $value);
            }
        }

        return '';
    }

    /** @param array<string, mixed> $source @param array<string, mixed> $edited @return array<string, mixed> */
    private function mergePreservingLegacy(array $source, array $edited): array
    {
        $merged = $source;

        foreach ($edited as $key => $value) {
            $existing = $source[$key] ?? null;
            if (is_array($value) && is_array($existing)) {
                if (array_is_list($value)) {
                    $merged[$key] = $this->mergeLegacyList($existing, $value);
                } else {
                    $merged[$key] = $this->mergePreservingLegacy($existing, $value);
                }
            } else {
                $merged[$key] = $value;
            }
        }

        return $merged;
    }

    /** @param array<int, mixed> $source @param array<int, mixed> $edited @return array<int, mixed> */
    private function mergeLegacyList(array $source, array $edited): array
    {
        return array_values(array_map(function (mixed $item, int $index) use ($source): mixed {
            if (! is_array($item)) {
                return $item;
            }

            $identity = $this->firstFilled([$item['id'] ?? null, $item['slug'] ?? null]);
            $indexedSource = is_array($source[$index] ?? null) ? $source[$index] : [];
            $identitylessSource = $this->firstFilled([$indexedSource['id'] ?? null, $indexedSource['slug'] ?? null]) === '' ? $indexedSource : [];
            $existing = $identity === '' ? ($source[$index] ?? []) : collect($source)->first(
                fn (mixed $candidate): bool => is_array($candidate) && $identity === $this->firstFilled([$candidate['id'] ?? null, $candidate['slug'] ?? null]),
                $identitylessSource,
            );

            return is_array($existing) ? $this->mergePreservingLegacy($existing, $item) : $item;
        }, $edited, array_keys($edited)));
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

    private function isSafeResourceUrl(mixed $url): bool
    {
        return is_string($url)
            && trim($url) !== ''
            && $url !== '#'
            && (
                (str_starts_with($url, '/') && ! str_starts_with($url, '//'))
                || (filter_var($url, FILTER_VALIDATE_URL) !== false && parse_url($url, PHP_URL_SCHEME) === 'https')
            );
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
        return collect($errors)->flatten()->map(
            static fn (mixed $message): string => preg_replace('/\b([a-z]+)([A-Z][A-Za-z]*)\b/', '$1 $2', (string) $message) ?? (string) $message,
        )->implode(PHP_EOL);
    }
}
