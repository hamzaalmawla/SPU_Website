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

class ManageEServicesPage extends Page implements HasForms
{
    use InteractsWithForms;

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
        return Gate::allows('manage-pages');
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
        $this->loadTarget('e_services');
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
            Action::make('save')->label('Save Draft')->icon('heroicon-o-check')->color('gray')->action(fn () => $this->save()),
            Action::make('preview_ar')->label('Preview AR')->icon('heroicon-o-eye')->color('info')->action(fn () => $this->openPreview('ar')),
            Action::make('preview_en')->label('Preview EN')->icon('heroicon-o-eye')->color('info')->action(fn () => $this->openPreview('en')),
            Action::make('publish')->label('Publish')->icon('heroicon-o-paper-airplane')->color('success')->requiresConfirmation()->action(fn () => $this->publish()),
            Action::make('schedule')
                ->label('Schedule')
                ->icon('heroicon-o-clock')
                ->color('warning')
                ->form([
                    DateTimePicker::make('publish_at')->label('Publish At')->required()->minDate(now())->native(false),
                ])
                ->action(fn (array $data) => $this->schedule((string) $data['publish_at'])),
            Action::make('unpublish')->label('Unpublish')->icon('heroicon-o-x-circle')->color('danger')->requiresConfirmation()->action(fn () => $this->unpublish()),
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
        ];

        if ($isSuggestions) {
            $payload = is_array($draftPayload) ? $draftPayload : $this->eServicesPageService->getSuggestionsComplaintsEditablePayload();
            $state['ar_suggestions'] = is_array($payload['translations']['ar'] ?? null) ? $payload['translations']['ar'] : [];
            $state['en_suggestions'] = is_array($payload['translations']['en'] ?? null) ? $payload['translations']['en'] : [];
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
            Notification::make()->title('E-Services draft saved')->success()->send();
        } catch (ConflictException $e) {
            $this->draftVersion = $e->currentVersion;
            Notification::make()->title('Draft conflict detected')->body('Reload this E-Services target before saving again.')->danger()->persistent()->send();
        } catch (\Throwable $e) {
            report($e);
            Notification::make()->title('Failed to save E-Services draft')->body($e->getMessage())->danger()->send();
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
            Notification::make()->title('Draft conflict detected')->body('Reload this E-Services target before previewing again.')->danger()->persistent()->send();
        } catch (\Throwable $e) {
            report($e);
            Notification::make()->title('Failed to create E-Services preview')->body($e->getMessage())->danger()->send();
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
            Notification::make()->title('E-Services target published')->success()->send();
        } catch (ValidationException $e) {
            Notification::make()->title('Publish failed')->body($this->formatValidationErrors($e->errors()))->danger()->persistent()->send();
        } catch (\Throwable $e) {
            report($e);
            Notification::make()->title('Failed to publish E-Services target')->body($e->getMessage())->danger()->send();
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
            Notification::make()->title('E-Services target scheduled')->success()->send();
        } catch (ValidationException $e) {
            Notification::make()->title('Schedule failed')->body($this->formatValidationErrors($e->errors()))->danger()->persistent()->send();
        } catch (\Throwable $e) {
            report($e);
            Notification::make()->title('Failed to schedule E-Services target')->body($e->getMessage())->danger()->send();
        }
    }

    public function unpublish(): void
    {
        /** @var User $user */
        $user = auth()->user();
        $result = $this->cmsWorkflowService->unpublish($this->currentTargetKey(), (int) $user->id);
        $notification = Notification::make()->title($result ? 'E-Services target unpublished' : 'No published E-Services target found');
        ($result ? $notification->success() : $notification->warning())->send();
    }

    private function localeTab(string $locale, string $label): Tab
    {
        return Tab::make($label)->schema([
            Section::make('Suggestions & Complaints Page')->schema([
                TextInput::make("{$locale}_suggestions.hero.eyebrow")->label('Eyebrow')->required()->maxLength(160),
                TextInput::make("{$locale}_suggestions.hero.title")->label('Title')->required()->maxLength(180),
                Textarea::make("{$locale}_suggestions.hero.summary")->label('Summary')->required()->rows(3)->columnSpanFull(),
                MediaPicker::image("{$locale}_suggestions.hero.image", 'Hero Image', true),
                TextInput::make("{$locale}_suggestions.form.title")->label('Form Title')->required()->maxLength(180),
                TextInput::make("{$locale}_suggestions.form.infoTitle")->label('Information Title')->required()->maxLength(180),
                Textarea::make("{$locale}_suggestions.form.infoBody")->label('Information Body')->required()->rows(3)->columnSpanFull(),
                Textarea::make("{$locale}_suggestions.form.consentLabel")->label('Consent Label')->required()->rows(2)->columnSpanFull(),
                TextInput::make("{$locale}_suggestions.form.attachmentHelp")->label('Attachment Help')->required()->maxLength(255)->columnSpanFull(),
                Repeater::make("{$locale}_suggestions.form.requestTypes")->label('Request Types')->schema([
                    Select::make('value')->options(['suggestion' => 'Suggestion', 'complaint' => 'Complaint', 'inquiry' => 'Inquiry'])->required(),
                    TextInput::make('label')->required()->maxLength(120),
                ])->columns(2)->minItems(3)->maxItems(3)->reorderable(false)->columnSpanFull(),
                Repeater::make("{$locale}_suggestions.form.cards")->label('Information Cards')->schema([
                    TextInput::make('title')->required()->maxLength(180),
                    Textarea::make('body')->required()->rows(3),
                ])->columns(2)->columnSpanFull(),
                TextInput::make("{$locale}_suggestions.seo.title")->label('SEO Title')->required()->maxLength(180),
                Textarea::make("{$locale}_suggestions.seo.description")->label('SEO Description')->required()->rows(3),
                MediaPicker::image("{$locale}_suggestions.seo.image", 'SEO Image', true),
            ])->columns(2)->visible(fn (): bool => $this->isSuggestionsTarget()),
            Section::make('Landing Hero')->schema([
                TextInput::make("{$locale}_landing.hero_eyebrow")->label('Eyebrow')->required()->maxLength(160),
                TextInput::make("{$locale}_landing.hero_title")->label('Title')->required()->maxLength(180),
                Textarea::make("{$locale}_landing.hero_summary")->label('Summary')->required()->rows(3)->columnSpanFull(),
                MediaPicker::image("{$locale}_landing.image_hero", 'Hero Image', true),
                MediaPicker::image("{$locale}_landing.image_left", 'Left Background Image', true),
                MediaPicker::image("{$locale}_landing.image_right", 'Right Background Image', true),
            ])->columns(2)->visible(fn (): bool => $this->isLandingTarget()),
            Section::make('Digital Services')->schema([
                TextInput::make("{$locale}_landing.digital_title")->label('Section Title')->required()->maxLength(160),
                Repeater::make("{$locale}_landing.services")->schema([
                    TextInput::make('id')->required()->maxLength(80),
                    TextInput::make('title')->required()->maxLength(160),
                    Textarea::make('summary')->required()->rows(3)->columnSpanFull(),
                    MediaPicker::icon('icon', 'Icon', true),
                    TextInput::make('url')->required()->maxLength(500),
                    TextInput::make('button')->required()->maxLength(80),
                ])->columns(2)->reorderable()->defaultItems(0)->columnSpanFull(),
            ])->visible(fn (): bool => $this->isLandingTarget()),
            Section::make('Support Cards')->schema([
                Repeater::make("{$locale}_landing.support_cards")->schema([
                    TextInput::make('id')->required()->maxLength(80),
                    TextInput::make('eyebrow')->required()->maxLength(120),
                    TextInput::make('title')->required()->maxLength(160),
                    Textarea::make('summary')->required()->rows(3)->columnSpanFull(),
                ])->columns(2)->reorderable()->defaultItems(0),
            ])->visible(fn (): bool => $this->isLandingTarget()),
            Section::make('Detail Hero and Introduction')->schema([
                TextInput::make("{$locale}_detail.hero_eyebrow")->label('Eyebrow')->required()->maxLength(160),
                TextInput::make("{$locale}_detail.hero_title")->label('Title')->required()->maxLength(180),
                Textarea::make("{$locale}_detail.hero_summary")->label('Summary')->required()->rows(3)->columnSpanFull(),
                MediaPicker::image("{$locale}_detail.hero_image", 'Hero Image', true),
                TextInput::make("{$locale}_detail.intro_title")->label('Introduction Title')->required()->maxLength(180),
                Textarea::make("{$locale}_detail.intro_body")->label('Introduction Body')->required()->rows(5)->columnSpanFull(),
            ])->columns(2)->visible(fn (): bool => ! $this->isLandingTarget() && ! $this->isSuggestionsTarget()),
            Section::make('Guidance Sections')->schema([
                Repeater::make("{$locale}_detail.sections")->schema([
                    TextInput::make('id')->required()->maxLength(80),
                    TextInput::make('title')->required()->maxLength(180),
                    Textarea::make('body')->required()->rows(4)->columnSpanFull(),
                ])->columns(2)->reorderable()->minItems(1)->defaultItems(0),
            ])->visible(fn (): bool => ! $this->isLandingTarget() && ! $this->isSuggestionsTarget()),
            Section::make('Verified Open Resources')->description('Library resources must use public HTTPS URLs.')->schema([
                TextInput::make("{$locale}_detail.resources_title")->label('Section Title')->required()->maxLength(180),
                Repeater::make("{$locale}_detail.resource_links")->schema([
                    TextInput::make('id')->required()->maxLength(80),
                    TextInput::make('title')->required()->maxLength(180),
                    TextInput::make('url')->label('HTTPS URL')->required()->url()->maxLength(500),
                ])->columns(2)->reorderable()->minItems(1)->defaultItems(0),
            ])->visible(fn (): bool => $this->currentTargetKey() === 'e_services.library'),
            Section::make('Call to Action and Related Links')->schema([
                TextInput::make("{$locale}_detail.cta_title")->label('CTA Title')->required()->maxLength(180),
                Textarea::make("{$locale}_detail.cta_body")->label('CTA Body')->required()->rows(3),
                TextInput::make("{$locale}_detail.cta_label")->label('CTA Label')->required()->maxLength(100),
                PageUrlSelect::make("{$locale}_detail.cta_url", 'CTA URL', $locale, true),
                Repeater::make("{$locale}_detail.related_links")->schema([
                    TextInput::make('id')->required()->maxLength(80),
                    TextInput::make('title')->required()->maxLength(180),
                    TextInput::make('url')->required()->maxLength(500),
                ])->columns(2)->reorderable()->minItems(1)->defaultItems(0)->columnSpanFull(),
            ])->columns(2)->visible(fn (): bool => ! $this->isLandingTarget() && ! $this->isSuggestionsTarget()),
            Section::make('Landing SEO')->schema([
                TextInput::make("{$locale}_landing.seo_title")->label('SEO Title')->required()->maxLength(180),
                Textarea::make("{$locale}_landing.seo_description")->label('SEO Description')->required()->rows(3),
                MediaPicker::image("{$locale}_landing.seo_image", 'SEO Image', true),
            ])->visible(fn (): bool => $this->isLandingTarget()),
            Section::make('Detail SEO')->schema([
                TextInput::make("{$locale}_detail.seo_title")->label('SEO Title')->required()->maxLength(180),
                Textarea::make("{$locale}_detail.seo_description")->label('SEO Description')->required()->rows(3),
                MediaPicker::image("{$locale}_detail.seo_image", 'SEO Image', true),
            ])->visible(fn (): bool => ! $this->isLandingTarget() && ! $this->isSuggestionsTarget()),
        ]);
    }

    /** @return array<string, string> */
    private function targetOptions(): array
    {
        return $this->targetRegistry->forArea('e_services')
            ->mapWithKeys(fn (CmsTargetDTO $target): array => [$target->key => __($target->labelKey)])
            ->all();
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
            throw new \InvalidArgumentException('Unsupported E-Services target.');
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
            return [
                'translations' => [
                    'ar' => is_array($state['ar_suggestions'] ?? null) ? $state['ar_suggestions'] : [],
                    'en' => is_array($state['en_suggestions'] ?? null) ? $state['en_suggestions'] : [],
                ],
            ];
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
