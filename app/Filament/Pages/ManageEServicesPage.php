<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Contracts\Cms\CmsTargetRegistryInterface;
use App\Contracts\Cms\CmsWorkflowServiceInterface;
use App\Contracts\Page\EServicesPageServiceInterface;
use App\DTOs\Cms\CmsTargetDTO;
use App\DTOs\EServices\EServicesDetailPageDTO;
use App\Exceptions\ConflictException;
use App\Filament\Components\PageUrlSelect;
use App\Filament\Concerns\LimitsCmsTargetsToGrants;
use App\Filament\Support\FormPicker;
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

class ManageEServicesPage extends Page implements HasForms
{
    use InteractsWithForms;
    use LimitsCmsTargetsToGrants;

    protected static ?string $navigationIcon = 'heroicon-o-computer-desktop';

    protected static ?string $slug = 'manage-e-services-page';

    protected static ?int $navigationSort = 1;

    protected static string $view = 'filament.pages.manage-e-services-page';

    /** @var array<string, mixed> */
    public ?array $data = [];

    public ?int $draftVersion = null;

    private EServicesPageServiceInterface $eServicesPageService;

    private CmsTargetRegistryInterface $targetRegistry;

    private CmsWorkflowServiceInterface $cmsWorkflowService;

    public function boot(
        EServicesPageServiceInterface $eServicesPageService,
        CmsTargetRegistryInterface $targetRegistry,
        CmsWorkflowServiceInterface $cmsWorkflowService,
    ): void {
        $this->eServicesPageService = $eServicesPageService;
        $this->targetRegistry = $targetRegistry;
        $this->cmsWorkflowService = $cmsWorkflowService;
    }

    public static function canAccess(): bool
    {
        return static::canAccessCmsArea('manage-pages', 'e_services');
    }

    public static function getNavigationGroup(): ?string
    {
        return __('admin.navigation.groups.e_services');
    }

    public static function getNavigationLabel(): string
    {
        return __('admin.navigation.items.e_services_page');
    }

    public function getTitle(): string
    {
        return __('admin.pages.manage_e_services_page');
    }

    public function mount(): void
    {
        $this->loadTarget($this->firstGrantedTarget('e_services', $this->targetOptions()));
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make(__('admin.cms.targets.e_services'))->schema([
                    Select::make('target_key')
                        ->label(__('admin.navigation.items.e_services_page'))
                        ->options($this->targetOptions())
                        ->required()
                        ->live()
                        ->afterStateUpdated(fn (?string $state): mixed => is_string($state) && $state !== '' ? $this->loadTarget($state) : null),
                ]),
                Section::make(__('admin.forms.fields.form'))
                    ->schema([FormPicker::make('suggestions_form_id')->label(__('admin.forms.fields.form'))->required(fn (): bool => $this->isSuggestionsTarget())])
                    ->visible(fn (): bool => $this->isSuggestionsTarget()),
                Tabs::make('e_services_page_locales')
                    ->tabs([
                        $this->localeTab('ar', 'Arabic'),
                        $this->localeTab('en', 'English'),
                    ])
                    ->persistTabInQueryString('locale')
                    ->columnSpanFull(),
            ])
            ->statePath('data');
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('save')->label(__('admin_pages.e_services.save_draft'))->icon('heroicon-o-check')->color('gray')->action(fn () => $this->save()),
            Action::make('preview_ar')->label(__('admin_pages.e_services.preview_ar'))->icon('heroicon-o-eye')->color('info')->action(fn () => $this->openPreview('ar')),
            Action::make('preview_en')->label(__('admin_pages.e_services.preview_en'))->icon('heroicon-o-eye')->color('info')->action(fn () => $this->openPreview('en')),
            Action::make('publish')->label(__('admin_pages.e_services.publish'))->icon('heroicon-o-paper-airplane')->color('success')->requiresConfirmation()->action(fn () => $this->publish()),
            Action::make('schedule')
                ->label(__('admin_pages.e_services.schedule'))
                ->icon('heroicon-o-clock')
                ->color('warning')
                ->form([
                    DateTimePicker::make('publish_at')->label(__('admin_pages.e_services.publish_at'))->required()->minDate(now())->native(false),
                ])
                ->action(fn (array $data) => $this->schedule((string) $data['publish_at'])),
            Action::make('unpublish')->label(__('admin_pages.e_services.unpublish'))->icon('heroicon-o-x-circle')->color('danger')->requiresConfirmation()->action(fn () => $this->unpublish()),
        ];
    }

    public function loadTarget(string $targetKey): void
    {
        $this->assertEServicesTarget($targetKey);
        $userId = (int) auth()->id();
        $draftPayload = $this->cmsWorkflowService->latestEditableDraftPayload($targetKey, $userId);
        $this->draftVersion = $this->cmsWorkflowService->latestEditableDraftVersion($targetKey, $userId);
        $isLanding = $targetKey === 'e_services';
        $isSuggestions = $targetKey === 'e_services.suggestions-complaints';
        $slug = $isLanding ? null : substr($targetKey, strlen('e_services.'));

        $state = [
            'target_key' => $targetKey,
            'ar_landing' => [],
            'en_landing' => [],
            'ar_detail' => [],
            'en_detail' => [],
            'ar_suggestions' => [],
            'en_suggestions' => [],
            'suggestions_form_id' => null,
        ];

        if ($isSuggestions) {
            $payload = is_array($draftPayload) ? $draftPayload : $this->eServicesPageService->getSuggestionsComplaintsEditablePayload();
            $state['ar_suggestions'] = is_array($payload['translations']['ar'] ?? null) ? $payload['translations']['ar'] : [];
            $state['en_suggestions'] = is_array($payload['translations']['en'] ?? null) ? $payload['translations']['en'] : [];
            $state['suggestions_form_id'] = (string) ($payload['translations']['ar']['form']['formId'] ?? 'suggestions-complaints');
            $this->form->fill($state);

            return;
        }

        foreach (['ar', 'en'] as $locale) {
            $draftContent = is_array($draftPayload['translations'][$locale] ?? null)
                ? $draftPayload['translations'][$locale]
                : null;

            if ($isLanding) {
                $state[$locale.'_landing'] = $this->landingFormData($locale, $draftContent);

                continue;
            }

            $state[$locale.'_detail'] = is_array($draftContent)
                ? $this->detailContentToFormData((string) $slug, $draftContent)
                : $this->detailDtoToFormData($this->eServicesPageService->getDetailPage($locale, (string) $slug));
        }

        $this->form->fill($state);
    }

    public function save(): void
    {
        /** @var User $user */
        $user = auth()->user();

        try {
            $draft = $this->cmsWorkflowService->saveDraft(
                $this->currentTargetKey(),
                $this->payloadFromForm($this->currentFormData()),
                (int) $user->id,
                $this->draftVersion,
            );
            $this->draftVersion = $draft->version;
            Notification::make()->title(__('admin_pages.e_services.e_services_draft_saved'))->success()->send();
        } catch (ConflictException $e) {
            $this->draftVersion = $e->currentVersion;
            Notification::make()->title(__('admin_pages.e_services.draft_conflict_detected'))->body(__('admin_pages.e_services.reload_this_e_services_target_before_saving_again'))->danger()->persistent()->send();
        } catch (\Throwable $e) {
            report($e);
            Notification::make()->title(__('admin_pages.e_services.failed_to_save_e_services_draft'))->body($e->getMessage())->danger()->send();
        }
    }

    public function openPreview(string $locale): void
    {
        /** @var User $user */
        $user = auth()->user();

        try {
            $targetKey = $this->currentTargetKey();
            $draft = $this->cmsWorkflowService->saveDraft($targetKey, $this->payloadFromForm($this->form->getState()), (int) $user->id, $this->draftVersion);
            $this->draftVersion = $draft->version;
            $preview = $this->cmsWorkflowService->preview($targetKey, $locale, (int) $user->id);
            $this->redirect($preview->previewUrl);
        } catch (ConflictException $e) {
            $this->draftVersion = $e->currentVersion;
            Notification::make()->title(__('admin_pages.e_services.draft_conflict_detected'))->body(__('admin_pages.e_services.reload_this_e_services_target_before_previewing_again'))->danger()->persistent()->send();
        } catch (\Throwable $e) {
            report($e);
            Notification::make()->title(__('admin_pages.e_services.failed_to_create_e_services_preview'))->body($e->getMessage())->danger()->send();
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
            Notification::make()->title(__('admin_pages.e_services.e_services_target_published'))->success()->send();
        } catch (ValidationException $e) {
            Notification::make()->title(__('admin_pages.e_services.publish_failed'))->body($this->formatValidationErrors($e->errors()))->danger()->persistent()->send();
        } catch (\Throwable $e) {
            report($e);
            Notification::make()->title(__('admin_pages.e_services.failed_to_publish_e_services_target'))->body($e->getMessage())->danger()->send();
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
            Notification::make()->title(__('admin_pages.e_services.e_services_target_scheduled'))->success()->send();
        } catch (ValidationException $e) {
            Notification::make()->title(__('admin_pages.e_services.schedule_failed'))->body($this->formatValidationErrors($e->errors()))->danger()->persistent()->send();
        } catch (\Throwable $e) {
            report($e);
            Notification::make()->title(__('admin_pages.e_services.failed_to_schedule_e_services_target'))->body($e->getMessage())->danger()->send();
        }
    }

    public function unpublish(): void
    {
        /** @var User $user */
        $user = auth()->user();
        $result = $this->cmsWorkflowService->unpublish($this->currentTargetKey(), (int) $user->id);
        $notification = Notification::make()->title($result ? __('admin_pages.e_services.e_services_target_unpublished') : __('admin_pages.e_services.no_published_e_services_target_found'));
        ($result ? $notification->success() : $notification->warning())->send();
    }

    private function localeTab(string $locale, string $label): Tab
    {
        return Tab::make($label)->label(__('admin_pages.e_services.'.($locale === 'ar' ? 'arabic' : 'english')))->schema([
            Section::make(__('admin_pages.e_services.suggestions_complaints_page'))->schema([
                TextInput::make("{$locale}_suggestions.hero.eyebrow")->label(__('admin_pages.e_services.eyebrow'))->required()->maxLength(160),
                TextInput::make("{$locale}_suggestions.hero.title")->label(__('admin_pages.e_services.title'))->required()->maxLength(180),
                Textarea::make("{$locale}_suggestions.hero.summary")->label(__('admin_pages.e_services.summary'))->required()->rows(3)->columnSpanFull(),
                MediaPicker::image("{$locale}_suggestions.hero.image", __('admin_pages.e_services.hero_image'), true),
                TextInput::make("{$locale}_suggestions.form.title")->label(__('admin_pages.e_services.form_title'))->required()->maxLength(180),
                TextInput::make("{$locale}_suggestions.form.infoTitle")->label(__('admin_pages.e_services.information_title'))->required()->maxLength(180),
                Textarea::make("{$locale}_suggestions.form.infoBody")->label(__('admin_pages.e_services.information_body'))->required()->rows(3)->columnSpanFull(),
                Repeater::make("{$locale}_suggestions.form.cards")->label(__('admin_pages.e_services.information_cards'))->schema([
                    TextInput::make('title')->required()->maxLength(180),
                    Textarea::make('body')->required()->rows(3),
                ])->columns(2)->columnSpanFull(),
                TextInput::make("{$locale}_suggestions.seo.title")->label(__('admin_pages.e_services.seo_title'))->required()->maxLength(180),
                Textarea::make("{$locale}_suggestions.seo.description")->label(__('admin_pages.e_services.seo_description'))->required()->rows(3),
                MediaPicker::image("{$locale}_suggestions.seo.image", __('admin_pages.e_services.seo_image'), true),
            ])->columns(2)->visible(fn (): bool => $this->isSuggestionsTarget()),
            Section::make(__('admin_pages.e_services.landing_hero'))->schema([
                TextInput::make("{$locale}_landing.hero_eyebrow")->label(__('admin_pages.e_services.eyebrow'))->required()->maxLength(160),
                TextInput::make("{$locale}_landing.hero_title")->label(__('admin_pages.e_services.title'))->required()->maxLength(180),
                Textarea::make("{$locale}_landing.hero_summary")->label(__('admin_pages.e_services.summary'))->required()->rows(3)->columnSpanFull(),
                MediaPicker::image("{$locale}_landing.image_hero", __('admin_pages.e_services.hero_image'), true),
                MediaPicker::image("{$locale}_landing.image_left", __('admin_pages.e_services.left_background_image'), true),
                MediaPicker::image("{$locale}_landing.image_right", __('admin_pages.e_services.right_background_image'), true),
            ])->columns(2)->visible(fn (): bool => $this->isLandingTarget()),
            Section::make(__('admin_pages.e_services.digital_services'))->schema([
                TextInput::make("{$locale}_landing.digital_title")->label(__('admin_pages.e_services.section_title'))->required()->maxLength(160),
                Repeater::make("{$locale}_landing.services")->schema([
                    TextInput::make('id')->required()->maxLength(80),
                    TextInput::make('title')->required()->maxLength(160),
                    Textarea::make('summary')->required()->rows(3)->columnSpanFull(),
                    MediaPicker::icon('icon', __('admin_pages.e_services.icon'), true),
                    TextInput::make('url')->required()->maxLength(500),
                    TextInput::make('button')->required()->maxLength(80),
                ])->columns(2)->reorderable()->defaultItems(0)->columnSpanFull(),
            ])->visible(fn (): bool => $this->isLandingTarget()),
            Section::make(__('admin_pages.e_services.support_cards'))->schema([
                Repeater::make("{$locale}_landing.support_cards")->schema([
                    TextInput::make('id')->required()->maxLength(80),
                    TextInput::make('eyebrow')->required()->maxLength(120),
                    TextInput::make('title')->required()->maxLength(160),
                    Textarea::make('summary')->required()->rows(3)->columnSpanFull(),
                ])->columns(2)->reorderable()->defaultItems(0),
            ])->visible(fn (): bool => $this->isLandingTarget()),
            Section::make(__('admin_pages.e_services.detail_hero_and_introduction'))->schema([
                TextInput::make("{$locale}_detail.hero_eyebrow")->label(__('admin_pages.e_services.eyebrow'))->required()->maxLength(160),
                TextInput::make("{$locale}_detail.hero_title")->label(__('admin_pages.e_services.title'))->required()->maxLength(180),
                Textarea::make("{$locale}_detail.hero_summary")->label(__('admin_pages.e_services.summary'))->required()->rows(3)->columnSpanFull(),
                MediaPicker::image("{$locale}_detail.hero_image", __('admin_pages.e_services.hero_image'), true),
                TextInput::make("{$locale}_detail.intro_title")->label(__('admin_pages.e_services.introduction_title'))->required()->maxLength(180),
                Textarea::make("{$locale}_detail.intro_body")->label(__('admin_pages.e_services.introduction_body'))->required()->rows(5)->columnSpanFull(),
            ])->columns(2)->visible(fn (): bool => ! $this->isLandingTarget() && ! $this->isSuggestionsTarget()),
            Section::make(__('admin_pages.e_services.guidance_sections'))->schema([
                Repeater::make("{$locale}_detail.sections")->schema([
                    TextInput::make('id')->required()->maxLength(80),
                    TextInput::make('title')->required()->maxLength(180),
                    Textarea::make('body')->required()->rows(4)->columnSpanFull(),
                ])->columns(2)->reorderable()->minItems(1)->defaultItems(0),
            ])->visible(fn (): bool => ! $this->isLandingTarget() && ! $this->isSuggestionsTarget()),
            Section::make(__('admin_pages.e_services.verified_open_resources'))->description(__('admin_pages.e_services.library_resources_must_use_public_https_urls'))->schema([
                TextInput::make("{$locale}_detail.resources_title")->label(__('admin_pages.e_services.section_title'))->required()->maxLength(180),
                Repeater::make("{$locale}_detail.resource_links")->schema([
                    TextInput::make('id')->required()->maxLength(80),
                    TextInput::make('title')->required()->maxLength(180),
                    TextInput::make('url')->label(__('admin_pages.e_services.https_url'))->required()->url()->maxLength(500),
                ])->columns(2)->reorderable()->minItems(1)->defaultItems(0),
            ])->visible(fn (): bool => $this->currentTargetKey() === 'e_services.library'),
            Section::make(__('admin_pages.e_services.call_to_action_and_related_links'))->schema([
                TextInput::make("{$locale}_detail.cta_title")->label(__('admin_pages.e_services.cta_title'))->required()->maxLength(180),
                Textarea::make("{$locale}_detail.cta_body")->label(__('admin_pages.e_services.cta_body'))->required()->rows(3),
                TextInput::make("{$locale}_detail.cta_label")->label(__('admin_pages.e_services.cta_label'))->required()->maxLength(100),
                PageUrlSelect::make("{$locale}_detail.cta_url", __('admin_pages.e_services.cta_url'), $locale, true),
                Repeater::make("{$locale}_detail.related_links")->schema([
                    TextInput::make('id')->required()->maxLength(80),
                    TextInput::make('title')->required()->maxLength(180),
                    TextInput::make('url')->required()->maxLength(500),
                ])->columns(2)->reorderable()->minItems(1)->defaultItems(0)->columnSpanFull(),
            ])->columns(2)->visible(fn (): bool => ! $this->isLandingTarget() && ! $this->isSuggestionsTarget()),
            Section::make(__('admin_pages.e_services.landing_seo'))->schema([
                TextInput::make("{$locale}_landing.seo_title")->label(__('admin_pages.e_services.seo_title'))->required()->maxLength(180),
                Textarea::make("{$locale}_landing.seo_description")->label(__('admin_pages.e_services.seo_description'))->required()->rows(3),
                MediaPicker::image("{$locale}_landing.seo_image", __('admin_pages.e_services.seo_image'), true),
            ])->visible(fn (): bool => $this->isLandingTarget()),
            Section::make(__('admin_pages.e_services.detail_seo'))->schema([
                TextInput::make("{$locale}_detail.seo_title")->label(__('admin_pages.e_services.seo_title'))->required()->maxLength(180),
                Textarea::make("{$locale}_detail.seo_description")->label(__('admin_pages.e_services.seo_description'))->required()->rows(3),
                MediaPicker::image("{$locale}_detail.seo_image", __('admin_pages.e_services.seo_image'), true),
            ])->visible(fn (): bool => ! $this->isLandingTarget() && ! $this->isSuggestionsTarget()),
        ]);
    }

    /** @return array<string, string> */
    private function targetOptions(): array
    {
        return $this->onlyGrantedTargets($this->targetRegistry->forArea('e_services')
            ->mapWithKeys(fn (CmsTargetDTO $target): array => [$target->key => __($target->labelKey)])
            ->all());
    }

    private function currentTargetKey(): string
    {
        $targetKey = (string) ($this->data['target_key'] ?? 'e_services');
        $this->assertEServicesTarget($targetKey);

        return $targetKey;
    }

    private function isLandingTarget(): bool
    {
        return ($this->data['target_key'] ?? 'e_services') === 'e_services';
    }

    private function isSuggestionsTarget(): bool
    {
        return ($this->data['target_key'] ?? null) === 'e_services.suggestions-complaints';
    }

    private function assertEServicesTarget(string $targetKey): void
    {
        $target = $this->targetRegistry->find($targetKey);

        if (! $target instanceof CmsTargetDTO || $target->area !== 'e_services') {
            throw new \InvalidArgumentException(__('admin_pages.e_services.unsupported_e_services_target'));
        }
    }

    /** @param array<string, mixed>|null $draftContent */
    private function landingFormData(string $locale, ?array $draftContent): array
    {
        if (is_array($draftContent)) {
            return $this->landingContentToFormData($draftContent);
        }

        $content = $this->eServicesPageService->getContent($locale);

        return $this->landingContentToFormData([
            'hero' => $content->hero,
            'digitalServices' => $content->digitalServices,
            'supportCards' => $content->supportCards,
            'seo' => ['title' => $content->seoTitle, 'description' => $content->seoDescription, 'image' => $content->seoImage],
        ]);
    }

    /** @param array<string, mixed> $content */
    private function landingContentToFormData(array $content): array
    {
        $hero = is_array($content['hero'] ?? null) ? $content['hero'] : [];
        $digital = is_array($content['digitalServices'] ?? null) ? $content['digitalServices'] : [];
        $seo = is_array($content['seo'] ?? null) ? $content['seo'] : [];

        return [
            'hero_eyebrow' => $this->stringValue($hero, 'eyebrow'),
            'hero_title' => $this->stringValue($hero, 'title'),
            'hero_summary' => $this->stringValue($hero, 'summary'),
            'image_hero' => $this->stringValue($hero, 'imageHero'),
            'image_left' => $this->stringValue($hero, 'imageLeft'),
            'image_right' => $this->stringValue($hero, 'imageRight'),
            'digital_title' => $this->stringValue($digital, 'title'),
            'services' => $this->arrayItems($digital['services'] ?? []),
            'support_cards' => $this->arrayItems($content['supportCards'] ?? []),
            'seo_title' => $this->stringValue($seo, 'title'),
            'seo_description' => $this->stringValue($seo, 'description'),
            'seo_image' => $this->stringValue($seo, 'image'),
        ];
    }

    private function detailDtoToFormData(EServicesDetailPageDTO $page): array
    {
        return [
            'hero_eyebrow' => $page->heroEyebrow,
            'hero_title' => $page->heroTitle,
            'hero_summary' => $page->heroSummary,
            'hero_image' => $page->heroImage,
            'intro_title' => $page->introTitle,
            'intro_body' => $page->introBody,
            'sections' => $page->sections,
            'resources_title' => $page->resourceLinksTitle,
            'resource_links' => $page->resourceLinks,
            'cta_title' => $page->ctaTitle,
            'cta_body' => $page->ctaBody,
            'cta_label' => $page->ctaLabel,
            'cta_url' => $page->ctaUrl,
            'related_links' => $page->relatedLinks,
            'seo_title' => $page->seoTitle,
            'seo_description' => $page->seoDescription,
            'seo_image' => $page->seoImage,
        ];
    }

    /** @param array<string, mixed> $content */
    private function detailContentToFormData(string $slug, array $content): array
    {
        return $this->detailDtoToFormData($this->eServicesPageService->buildDetailPreviewPage('en', $slug, $content));
    }

    /** @param array<string, mixed> $state */
    private function payloadFromForm(array $state): array
    {
        $targetKey = (string) ($state['target_key'] ?? 'e_services');
        $isLanding = $targetKey === 'e_services';

        if ($targetKey === 'e_services.suggestions-complaints') {
            // The fields (request types, consent, attachment) belong to the picked form.
            $translations = [];

            foreach (['ar', 'en'] as $locale) {
                $translation = is_array($state[$locale.'_suggestions'] ?? null) ? $state[$locale.'_suggestions'] : [];
                $form = is_array($translation['form'] ?? null) ? $translation['form'] : [];
                unset($form['consentLabel'], $form['attachmentHelp'], $form['requestTypes']);
                $translation['form'] = ['formId' => (string) ($state['suggestions_form_id'] ?? 'suggestions-complaints')] + $form;
                $translations[$locale] = $translation;
            }

            return ['translations' => $translations];
        }

        return [
            'translations' => [
                'ar' => $isLanding
                    ? $this->landingContentFromForm(is_array($state['ar_landing'] ?? null) ? $state['ar_landing'] : [])
                    : $this->detailContentFromForm(is_array($state['ar_detail'] ?? null) ? $state['ar_detail'] : []),
                'en' => $isLanding
                    ? $this->landingContentFromForm(is_array($state['en_landing'] ?? null) ? $state['en_landing'] : [])
                    : $this->detailContentFromForm(is_array($state['en_detail'] ?? null) ? $state['en_detail'] : []),
            ],
        ];
    }

    /** @param array<string, mixed> $data */
    private function landingContentFromForm(array $data): array
    {
        return [
            'hero' => [
                'eyebrow' => (string) ($data['hero_eyebrow'] ?? ''),
                'title' => (string) ($data['hero_title'] ?? ''),
                'summary' => (string) ($data['hero_summary'] ?? ''),
                'imageHero' => (string) ($data['image_hero'] ?? ''),
                'imageLeft' => (string) ($data['image_left'] ?? ''),
                'imageRight' => (string) ($data['image_right'] ?? ''),
            ],
            'digitalServices' => ['title' => (string) ($data['digital_title'] ?? ''), 'services' => $this->arrayItems($data['services'] ?? [])],
            'supportCards' => $this->arrayItems($data['support_cards'] ?? []),
            'seo' => ['title' => (string) ($data['seo_title'] ?? ''), 'description' => (string) ($data['seo_description'] ?? ''), 'image' => (string) ($data['seo_image'] ?? '')],
        ];
    }

    /** @param array<string, mixed> $data */
    private function detailContentFromForm(array $data): array
    {
        return [
            'hero' => [
                'eyebrow' => (string) ($data['hero_eyebrow'] ?? ''),
                'title' => (string) ($data['hero_title'] ?? ''),
                'summary' => (string) ($data['hero_summary'] ?? ''),
                'image' => (string) ($data['hero_image'] ?? ''),
            ],
            'intro' => ['title' => (string) ($data['intro_title'] ?? ''), 'body' => (string) ($data['intro_body'] ?? '')],
            'sections' => $this->arrayItems($data['sections'] ?? []),
            'resources' => ['title' => (string) ($data['resources_title'] ?? ''), 'links' => $this->arrayItems($data['resource_links'] ?? [])],
            'cta' => [
                'title' => (string) ($data['cta_title'] ?? ''),
                'body' => (string) ($data['cta_body'] ?? ''),
                'label' => (string) ($data['cta_label'] ?? ''),
                'url' => (string) ($data['cta_url'] ?? ''),
            ],
            'relatedLinks' => $this->arrayItems($data['related_links'] ?? []),
            'seo' => ['title' => (string) ($data['seo_title'] ?? ''), 'description' => (string) ($data['seo_description'] ?? ''), 'image' => (string) ($data['seo_image'] ?? '')],
        ];
    }

    /** @return array<string, mixed> */
    private function currentFormData(): array
    {
        return is_array($this->data) ? $this->data : [];
    }

    /** @return array<int, array<string, mixed>> */
    private function arrayItems(mixed $items): array
    {
        return array_values(array_filter(is_array($items) ? $items : [], static fn (mixed $item): bool => is_array($item)));
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
