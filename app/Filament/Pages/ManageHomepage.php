<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Contracts\Homepage\HomepagePublishingServiceInterface;
use App\Contracts\Homepage\HomepageSectionServiceInterface;
use App\Contracts\Shared\PreviewServiceInterface;
use App\DTOs\Content\ArticleCardDTO;
use App\DTOs\Contact\ContactLinkDTO;
use App\DTOs\Content\EventCardDTO;
use App\DTOs\Settings\FooterColumnDTO;
use App\DTOs\Homepage\HomepageDraftDataDTO;
use App\DTOs\Homepage\HomepageFeatureItemDTO;
use App\DTOs\Homepage\HomepageSectionDataDTO;
use App\DTOs\Homepage\HomepageSectionDTO;
use App\DTOs\Homepage\HomepageSectionTranslationDTO;
use App\DTOs\Homepage\HomepageStatItemDTO;
use App\DTOs\Homepage\HomepageDraftDTO;
use App\DTOs\Navigation\NavigationActionDTO;
use App\DTOs\Content\ResearchCardDTO;
use App\DTOs\Settings\SocialLinkDTO;
use App\Exceptions\ConflictException;
use App\Filament\Support\HomepageFormSchema;
use App\Models\User\User;
use Filament\Actions\Action;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Tabs;
use Filament\Forms\Components\Tabs\Tab;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;

/**
 * Filament custom page for managing the fixed 11-section homepage.
 *
 * All business logic is delegated to injected service interfaces.
 */
class ManageHomepage extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-home';

    protected static ?string $navigationLabel = 'Homepage';

    protected static ?string $title = 'Manage Homepage';

    protected static ?string $slug = 'manage-homepage';

    protected static ?int $navigationSort = 1;

    protected static string $view = 'filament.pages.manage-homepage';

    /** @var array<string, mixed> */
    public ?array $data = [];

    private HomepageSectionServiceInterface $sectionService;

    private HomepagePublishingServiceInterface $publishingService;

    private PreviewServiceInterface $previewService;

    /** @var Collection<int, HomepageSectionDTO>|null */
    private ?Collection $sections = null;

    public ?int $draftVersion = null;

    public function boot(
        HomepageSectionServiceInterface $sectionService,
        HomepagePublishingServiceInterface $publishingService,
        PreviewServiceInterface $previewService,
    ): void {
        $this->sectionService = $sectionService;
        $this->publishingService = $publishingService;
        $this->previewService = $previewService;
    }

    public static function canAccess(): bool
    {
        return Gate::allows('manage-homepage');
    }

    public function mount(): void
    {
        $this->loadSectionData();
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Tabs::make('homepage_sections')
                    ->tabs($this->buildSectionTabs())
                    ->persistTabInQueryString('section')
                    ->columnSpanFull(),
            ])
            ->statePath('data');
    }

    protected function getHeaderActions(): array
    {
        return [
            $this->saveDraftAction(),
            $this->discardDraftAction(),
            $this->previewArAction(),
            $this->previewEnAction(),
            $this->publishAction(),
            $this->scheduleAction(),
            $this->unpublishAction(),
        ];
    }

    private function saveDraftAction(): Action
    {
        return Action::make('saveDraft')
            ->label('Save Draft')
            ->icon('heroicon-o-document')
            ->color('gray')
            ->action(fn () => $this->saveDraft());
    }

    private function discardDraftAction(): Action
    {
        return Action::make('discardDraft')
            ->label('Discard Draft')
            ->icon('heroicon-o-trash')
            ->color('danger')
            ->requiresConfirmation()
            ->modalHeading('Discard Draft')
            ->modalDescription('This will permanently delete the current draft. The live homepage (if published) will not be affected.')
            ->visible(fn (): bool => $this->publishingService->hasEditableDraft())
            ->action(fn () => $this->discardDraft());
    }

    private function previewArAction(): Action
    {
        return Action::make('previewAr')
            ->label('Preview (AR)')
            ->icon('heroicon-o-eye')
            ->color('info')
            ->action(fn () => $this->openPreview('ar'));
    }

    private function previewEnAction(): Action
    {
        return Action::make('previewEn')
            ->label('Preview (EN)')
            ->icon('heroicon-o-eye')
            ->color('info')
            ->action(fn () => $this->openPreview('en'));
    }

    private function publishAction(): Action
    {
        return Action::make('publish')
            ->label('Publish')
            ->icon('heroicon-o-check-circle')
            ->color('success')
            ->requiresConfirmation()
            ->modalHeading('Publish Homepage')
            ->modalDescription('Are you sure you want to publish the current draft? This will make it live immediately.')
            ->action(fn () => $this->publishHomepage());
    }

    private function scheduleAction(): Action
    {
        return Action::make('schedule')
            ->label('Schedule')
            ->icon('heroicon-o-clock')
            ->color('warning')
            ->form([
                DateTimePicker::make('publish_at')
                    ->label('Publish At')
                    ->required()
                    ->minDate(now())
                    ->native(false),
            ])
            ->action(fn (array $data) => $this->schedulePublish($data['publish_at']));
    }

    private function unpublishAction(): Action
    {
        return Action::make('unpublish')
            ->label('Unpublish')
            ->icon('heroicon-o-x-circle')
            ->color('danger')
            ->requiresConfirmation()
            ->modalHeading('Unpublish Homepage')
            ->modalDescription('Are you sure you want to unpublish the homepage? It will no longer be visible to the public.')
            ->action(fn () => $this->unpublishHomepage());
    }

    private function saveDraft(): void
    {
        Gate::authorize('manage-homepage');

        $formData = $this->form->getState();
        try {
            $this->saveCurrentDraft($formData);
        } catch (ConflictException $exception) {
            $this->notifyDraftConflict($exception);

            return;
        }

        Notification::make()->title('Draft saved successfully')->success()->send();
    }

    public function save(): void
    {
        $this->saveDraft();
    }

    private function discardDraft(): void
    {
        Gate::authorize('manage-homepage');

        /** @var User $user */
        $user = auth()->user();
        $deleted = $this->publishingService->discardEditableDraft((int) $user->id);
        if ($deleted > 0) {
            Notification::make()->title('Draft discarded')->success()->send();
        } else {
            Notification::make()->title('No draft to discard')->warning()->send();
        }
    }

    private function openPreview(string $locale): void
    {
        Gate::authorize('manage-homepage');

        /** @var User $user */
        $user = auth()->user();
        try {
            $this->saveCurrentDraft($this->form->getState());
        } catch (ConflictException $exception) {
            $this->notifyDraftConflict($exception);

            return;
        }

        $preview = $this->previewService->createToken(
            targetType: 'homepage',
            targetId: null,
            locale: $locale,
            userId: $user->id,
        );
        $this->redirect($preview->previewUrl);
    }

    private function publishHomepage(): void
    {
        Gate::authorize('manage-homepage');

        /** @var User $user */
        $user = auth()->user();
        $formData = $this->form->getState();
        $validationErrors = $this->publishValidationErrors($formData);

        if ($validationErrors !== []) {
            Notification::make()
                ->title('Publish failed')
                ->body($this->formatValidationErrors($validationErrors))
                ->danger()
                ->persistent()
                ->send();

            return;
        }

        try {
            $draft = $this->saveCurrentDraft($formData);
        } catch (ConflictException $exception) {
            $this->notifyDraftConflict($exception);

            return;
        }

        $result = $this->publishingService->publish($draft->id, $user->id);
        if ($result) {
            Notification::make()->title('Homepage published successfully')->success()->send();
        } else {
            Notification::make()->title('Publish failed')
                ->body('The homepage draft did not pass publish validation. Review required fields in Arabic and English.')
                ->danger()
                ->persistent()
                ->send();
        }
    }

    private function schedulePublish(string $publishAt): void
    {
        Gate::authorize('manage-homepage');

        /** @var User $user */
        $user = auth()->user();
        $formData = $this->form->getState();
        $validationErrors = $this->publishValidationErrors($formData);

        if ($validationErrors !== []) {
            Notification::make()
                ->title('Schedule failed')
                ->body($this->formatValidationErrors($validationErrors))
                ->danger()
                ->persistent()
                ->send();

            return;
        }

        try {
            $draft = $this->saveCurrentDraft($formData);
        } catch (ConflictException $exception) {
            $this->notifyDraftConflict($exception);

            return;
        }

        $result = $this->publishingService->schedulePublish(
            $draft->id,
            new \DateTimeImmutable($publishAt),
            $user->id,
        );
        if ($result) {
            Notification::make()->title('Homepage scheduled for publication')
                ->body("Scheduled for: {$publishAt}")->success()->send();
        } else {
            Notification::make()->title('Schedule failed')->danger()->send();
        }
    }

    private function unpublishHomepage(): void
    {
        Gate::authorize('manage-homepage');

        /** @var User $user */
        $user = auth()->user();
        $result = $this->publishingService->unpublish('homepage', null, $user->id);
        if ($result) {
            Notification::make()->title('Homepage unpublished')->success()->send();
        } else {
            Notification::make()->title('Unpublish failed')->danger()->send();
        }
    }

    public function getHomepageState(): string
    {
        return $this->publishingService->latestHomepageState() ?? 'draft';
    }

    public function getStateBadgeColor(): string
    {
        return match ($this->getHomepageState()) {
            'published' => 'success',
            'scheduled' => 'warning',
            default => 'gray',
        };
    }

    private function loadSectionData(): void
    {
        $this->sections = $this->sectionService->getSections();
        $this->draftVersion = $this->publishingService->latestEditableDraftVersion();

        $formData = [];

        foreach (HomepageSectionServiceInterface::SECTION_KEYS as $key) {
            $section = $this->sections->first(fn (HomepageSectionDTO $s) => $s->key === $key);

            $formData[$key] = $section !== null
                ? $this->sectionToFormData($section)
                : $this->emptySectionData($key);
        }

        $this->form->fill($formData);
    }

    private function sectionToFormData(HomepageSectionDTO $section): array
    {
        return [
            'ar' => $this->payloadToFormArray($section->arabicPayload ?? $section->payload, $section->arabicTranslation, $section->key),
            'en' => $this->payloadToFormArray($section->englishPayload ?? $section->payload, $section->englishTranslation, $section->key),
        ];
    }

    private function payloadToFormArray(HomepageSectionDataDTO $payload, HomepageSectionTranslationDTO $translation, string $sectionKey = ''): array
    {
        $toArray = fn ($obj): array => json_decode(json_encode($obj), true) ?? [];
        $content = is_array($payload->content) ? $payload->content : ($toArray($payload->content) ?: []);

        if (isset($content['legalLinks']) && ! isset($content['legal_links'])) {
            $content['legal_links'] = $content['legalLinks'];
        }

        $content = $this->withContactContentAliases($content, $payload->contactLinks);
        $formContent = $content;
        $formContent['images'] = self::imagesToFormArray($content['images'] ?? []);

        $items = is_array($payload->items) ? array_map($toArray, $payload->items) : [];
        $featuredItems = $sectionKey === 'academic_faculties'
            ? self::itemsToFacultyFormArray($items, $payload->featuredItems)
            : ($sectionKey === 'achievements_highlights'
                ? self::itemsToHighlightFormArray($items, $payload->featuredItems)
                : self::featureItemsToFormArray($payload->featuredItems));

        return [
            'headline' => $translation->headline ?? $payload->title,
            'subheadline' => $translation->body ?? $payload->subtitle,
            'background_image' => $payload->backgroundImageUrl ?? $payload->imageUrl,
            'hero_carousel_uploads' => [],
            'video_url' => $payload->videoUrl,
            'image' => $payload->imageUrl,
            'primary_cta_label' => $payload->primaryAction?->label ?? $translation->ctaLabel,
            'primary_cta_url' => $payload->primaryAction?->url ?? null,
            'secondary_cta_label' => $payload->secondaryAction?->label ?? null,
            'secondary_cta_url' => $payload->secondaryAction?->url ?? null,
            'section_cta_label' => $payload->sectionAction?->label ?? null,
            'section_cta_url' => $payload->sectionAction?->url ?? null,
            'section_title' => $payload->title,
            'subtitle' => $payload->subtitle,
            'items' => self::itemsToFormArray($items),
            'path_items' => $sectionKey === 'choose_your_path' ? self::pathItemsToFormArray($items) : [],
            'stats' => array_map($toArray, $payload->stats),
            'featured_items' => $featuredItems,
            'articles' => self::articlesToFormArray($payload->articles),
            'research_items' => self::researchItemsToFormArray($payload->researchItems),
            'events' => self::eventsToFormArray($payload->events),
            'footer_columns' => array_map($toArray, $payload->footerColumns),
            'contact_links' => array_map($toArray, $payload->contactLinks),
            'social_links' => array_map($toArray, $payload->socialLinks),
            'content' => $formContent,
            'copyright_text' => $content['copyrightText'] ?? ($content['copyright_text'] ?? null),
            'logo' => $content['brandBlock']['logoUrl'] ?? ($content['brand_block']['logo_url'] ?? ($content['logo'] ?? null)),
            'brand_title' => $content['brandBlock']['title'] ?? ($content['brand_block']['title'] ?? null),
        ];
    }

    private function emptySectionData(string $key): array
    {
        return [
            'ar' => [],
            'en' => [],
        ];
    }

    /**
     * @return array<int, HomepageSectionDTO>
     */
    private function buildSectionDTOsFromFormData(array $formData): array
    {
        $dtos = [];
        $sortOrder = 0;

        foreach (HomepageSectionServiceInterface::SECTION_KEYS as $key) {
            $sectionData = $formData[$key] ?? ['ar' => [], 'en' => []];
            $sortOrder++;

            $existingSection = $this->sections?->first(fn (HomepageSectionDTO $s) => $s->key === $key);

            $dtos[] = new HomepageSectionDTO(
                id: $existingSection?->id ?? 0,
                key: $key,
                sortOrder: $sortOrder,
                isEnabled: $existingSection?->isEnabled ?? true,
                payload: $this->formArrayToPayload($sectionData['ar'] ?? [], $key),
                arabicTranslation: new HomepageSectionTranslationDTO(
                    locale: 'ar',
                    headline: $sectionData['ar']['headline'] ?? null,
                    body: $sectionData['ar']['subheadline'] ?? null,
                    ctaLabel: $sectionData['ar']['primary_cta_label'] ?? null,
                ),
                englishTranslation: new HomepageSectionTranslationDTO(
                    locale: 'en',
                    headline: $sectionData['en']['headline'] ?? null,
                    body: $sectionData['en']['subheadline'] ?? null,
                    ctaLabel: $sectionData['en']['primary_cta_label'] ?? null,
                ),
                arabicPayload: $this->formArrayToPayload($sectionData['ar'] ?? [], $key),
                englishPayload: $this->formArrayToPayload($sectionData['en'] ?? [], $key),
            );
        }

        return $dtos;
    }

    private function formArrayToPayload(array $data, string $sectionKey = ''): HomepageSectionDataDTO
    {
        $content = is_array($data['content'] ?? null) ? $data['content'] : [];
        $logo = self::extractFileUploadValue($data['logo'] ?? null);

        if ($logo !== null) {
            $content['logo'] = $logo;
        }

        if (($data['copyright_text'] ?? null) !== null && $data['copyright_text'] !== '') {
            $content['copyrightText'] = (string) $data['copyright_text'];
        }

        if (($data['brand_title'] ?? null) !== null && $data['brand_title'] !== '') {
            $content['brandBlock']['title'] = (string) $data['brand_title'];
        }

        if (! isset($content['brandBlock']) && isset($content['brand_block']) && is_array($content['brand_block'])) {
            $content['brandBlock'] = $content['brand_block'];
        }

        if ($logo !== null) {
            $content['brandBlock']['logoUrl'] = $logo;
        }

        if (! isset($content['legalLinks']) && isset($content['legal_links']) && is_array($content['legal_links'])) {
            $content['legalLinks'] = $content['legal_links'];
        }

        $legalLinks = $data['content']['legal_links'] ?? $data['content']['legalLinks'] ?? null;

        if (is_array($legalLinks)) {
            $content['legalLinks'] = $legalLinks;
        }

        $contentImages = self::imagePathsFromFormValue($content['images'] ?? []);
        $uploadedImages = self::imagePathsFromFormValue($data['hero_carousel_uploads'] ?? []);
        $mergedImages = array_values(array_unique(array_merge($contentImages, $uploadedImages)));

        if ($mergedImages !== []) {
            $content['images'] = $mergedImages;
        }

        $contactLinks = is_array($data['contact_links'] ?? null) ? $data['contact_links'] : [];

        foreach ([
            'phone' => $data['content']['contact_phone'] ?? null,
            'email' => $data['content']['contact_email'] ?? null,
            'address' => $data['content']['contact_address'] ?? null,
        ] as $type => $value) {
            if (is_string($value) && $value !== '') {
                $existingIndex = null;

                foreach ($contactLinks as $index => $link) {
                    if (is_array($link) && strtolower((string) ($link['type'] ?? '')) === $type) {
                        $existingIndex = $index;

                        break;
                    }
                }

                if ($existingIndex !== null) {
                    $contactLinks[$existingIndex]['value'] = $value;
                } else {
                    $contactLinks[] = [
                        'type' => $type,
                        'label' => ucfirst($type),
                        'value' => $value,
                    ];
                }
            }
        }

        return new HomepageSectionDataDTO(
            title: $data['section_title'] ?? $data['headline'] ?? null,
            subtitle: $data['subtitle'] ?? $data['subheadline'] ?? null,
            videoUrl: $data['video_url'] ?? null,
            imageUrl: self::extractFileUploadValue($data['image'] ?? null),
            backgroundImageUrl: self::extractFileUploadValue($data['background_image'] ?? null),
            primaryAction: isset($data['primary_cta_label'])
                ? new NavigationActionDTO(
                    label: $data['primary_cta_label'],
                    url: $data['primary_cta_url'] ?? '#',
                )
                : null,
            secondaryAction: isset($data['secondary_cta_label'])
                ? new NavigationActionDTO(
                    label: $data['secondary_cta_label'],
                    url: $data['secondary_cta_url'] ?? '#',
                )
                : null,
            sectionAction: self::formAction($data, 'section_cta_label', 'section_cta_url'),
            stats: array_values(array_map(
                static fn (array $item): HomepageStatItemDTO => new HomepageStatItemDTO(
                    value: (string) ($item['value'] ?? ''),
                    label: (string) ($item['label'] ?? ''),
                    icon: isset($item['icon']) && $item['icon'] !== '' ? (string) $item['icon'] : null,
                    prefix: isset($item['prefix']) && $item['prefix'] !== '' ? (string) $item['prefix'] : null,
                    suffix: isset($item['suffix']) && $item['suffix'] !== '' ? (string) $item['suffix'] : null,
                    helperText: self::firstString($item, ['helperText', 'helper_text', 'description']),
                    sortOrder: is_numeric($item['sortOrder'] ?? ($item['sort_order'] ?? null))
                        ? (int) ($item['sortOrder'] ?? $item['sort_order'])
                        : null,
                ),
                array_filter($data['stats'] ?? [], static fn (mixed $i): bool => is_array($i)),
            )),
            featuredItems: array_values(array_map(
                static fn (array $item): HomepageFeatureItemDTO => new HomepageFeatureItemDTO(
                    title: (string) ($item['title'] ?? ''),
                    summary: self::firstString($item, ['description', 'text', 'summary']),
                    imageUrl: self::extractFileUploadValue($item['image'] ?? ($item['imageUrl'] ?? null)),
                    url: self::firstString($item, ['cta_url', 'url']),
                ),
                array_filter($data['featured_items'] ?? [], static fn (mixed $i): bool => is_array($i)),
            )),
            articles: array_values(array_map(
                static fn (array $item): ArticleCardDTO => new ArticleCardDTO(
                    id: (int) ($item['id'] ?? 0),
                    locale: (string) ($item['locale'] ?? 'ar'),
                    title: (string) ($item['title'] ?? ''),
                    slug: (string) ($item['slug'] ?? ''),
                    excerpt: self::firstString($item, ['excerpt', 'summary']),
                    imageUrl: self::extractFileUploadValue($item['image'] ?? ($item['imageUrl'] ?? null)),
                    publishedAt: self::firstString($item, ['publish_date', 'publishedAt']),
                    url: self::firstString($item, ['cta_url', 'url']),
                    categoryLabel: self::firstString($item, ['category', 'categoryLabel']),
                ),
                array_filter($data['articles'] ?? [], static fn (mixed $i): bool => is_array($i)),
            )),
            researchItems: array_values(array_map(
                static fn (array $item): ResearchCardDTO => new ResearchCardDTO(
                    id: (int) ($item['id'] ?? 0),
                    locale: (string) ($item['locale'] ?? 'ar'),
                    title: (string) ($item['title'] ?? ''),
                    slug: (string) ($item['slug'] ?? ''),
                    summary: self::firstString($item, ['excerpt', 'summary']),
                    imageUrl: self::extractFileUploadValue($item['image'] ?? ($item['imageUrl'] ?? null)),
                    publishedAt: self::firstString($item, ['publish_date', 'publishedAt']),
                    url: self::firstString($item, ['cta_url', 'url']),
                    categoryLabel: self::firstString($item, ['category', 'categoryLabel']),
                    authors: self::extractAuthors($item['authors'] ?? null),
                ),
                array_filter($data['research_items'] ?? [], static fn (mixed $i): bool => is_array($i)),
            )),
            events: array_values(array_map(
                static fn (array $item): EventCardDTO => new EventCardDTO(
                    id: (int) ($item['id'] ?? 0),
                    locale: (string) ($item['locale'] ?? 'ar'),
                    title: (string) ($item['title'] ?? ''),
                    slug: (string) ($item['slug'] ?? ''),
                    summary: self::firstString($item, ['description', 'summary']),
                    startsAt: self::firstString($item, ['date', 'startsAt']),
                    endsAt: null,
                    location: self::firstString($item, ['location']),
                    url: self::firstString($item, ['cta_url', 'url']),
                    imageUrl: self::extractFileUploadValue($item['image'] ?? ($item['imageUrl'] ?? null)),
                    timeLabel: self::firstString($item, ['time', 'timeLabel']),
                ),
                array_filter($data['events'] ?? [], static fn (mixed $i): bool => is_array($i)),
            )),
            footerColumns: array_values(array_map(
                static fn (array $col): FooterColumnDTO => new FooterColumnDTO(
                    title: (string) ($col['title'] ?? ''),
                    links: array_values(array_filter(array_map(
                        static fn (mixed $link): ?\App\DTOs\NavigationActionDTO => is_array($link) && isset($link['label'], $link['url'])
                            ? new NavigationActionDTO(label: (string) $link['label'], url: (string) $link['url'])
                            : null,
                        $col['links'] ?? [],
                    ))),
                ),
                array_filter($data['footer_columns'] ?? [], static fn (mixed $i): bool => is_array($i)),
            )),
            contactLinks: array_values(array_map(
                static fn (array $item): ContactLinkDTO => new ContactLinkDTO(
                    type: (string) ($item['type'] ?? 'text'),
                    label: (string) ($item['label'] ?? ''),
                    value: (string) ($item['value'] ?? ''),
                ),
                array_filter($contactLinks, static fn (mixed $i): bool => is_array($i)),
            )),
            socialLinks: array_values(array_map(
                static fn (array $item): SocialLinkDTO => new SocialLinkDTO(
                    platform: (string) ($item['platform'] ?? ''),
                    url: (string) ($item['url'] ?? ''),
                    isEnabled: (bool) ($item['isEnabled'] ?? ($item['is_enabled'] ?? true)),
                ),
                array_filter($data['social_links'] ?? [], static fn (mixed $i): bool => is_array($i)),
            )),
            items: self::formItems($data, $sectionKey),
            content: $content,
        );
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private static function formItems(array $data, string $sectionKey = ''): array
    {
        $items = match ($sectionKey) {
            'choose_your_path' => $data['path_items'] ?? [],
            'academic_faculties', 'achievements_highlights' => $data['featured_items'] ?? [],
            default => self::firstFilledItemSource($data),
        };

        return array_values(array_map(
            static function (array $item): array {
                $mapped = $item;

                if (($mapped['imageUrl'] ?? null) === null) {
                    $image = self::extractFileUploadValue($mapped['image'] ?? null);

                    if ($image !== null) {
                        $mapped['imageUrl'] = $image;
                    }
                }

                if (($mapped['summary'] ?? null) === null) {
                    $mapped['summary'] = self::firstString($mapped, ['description', 'text']);
                }

                $ctaLabel = self::firstString($mapped, ['cta_label']) ?? (is_array($mapped['action'] ?? null) ? self::firstString($mapped['action'], ['label']) : null);
                $ctaUrl = self::firstString($mapped, ['cta_url']) ?? (is_array($mapped['action'] ?? null) ? self::firstString($mapped['action'], ['url']) : null);

                if ($ctaLabel !== null || $ctaUrl !== null) {
                    $mapped['action'] = array_filter([
                        'label' => $ctaLabel,
                        'url' => $ctaUrl ?? '#',
                    ]);
                }

                if (isset($mapped['links']) && is_array($mapped['links'])) {
                    $mapped['links'] = array_values(array_map(
                        static fn (mixed $link): mixed => is_array($link) && isset($link['url']) && $link['url'] !== null && $link['url'] !== ''
                            ? array_filter(['label' => $link['label'] ?? ($link['text'] ?? ''), 'url' => $link['url']])
                            : (is_array($link) ? ($link['label'] ?? ($link['text'] ?? '')) : $link),
                        $mapped['links'],
                    ));
                }

                return $mapped;
            },
            array_filter($items, static fn (mixed $i): bool => is_array($i)),
        ));
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private static function firstFilledItemSource(array $data): array
    {
        foreach (['path_items', 'featured_items', 'items'] as $key) {
            if (is_array($data[$key] ?? null) && $data[$key] !== []) {
                return $data[$key];
            }
        }

        return [];
    }

    /**
     * @param  array<int, HomepageFeatureItemDTO>  $items
     * @return array<int, array<string, mixed>>
     */
    private static function featureItemsToFormArray(array $items): array
    {
        return array_values(array_map(
            static fn (HomepageFeatureItemDTO $item): array => [
                'title' => $item->title,
                'description' => $item->summary,
                'summary' => $item->summary,
                'imageUrl' => $item->imageUrl,
                'cta_label' => $item->url !== null ? 'Learn more' : null,
                'cta_url' => $item->url,
                'url' => $item->url,
            ],
            $items,
        ));
    }

    /**
     * @param  array<int, array<string, mixed>>  $items
     * @param  array<int, HomepageFeatureItemDTO>  $fallbackItems
     * @return array<int, array<string, mixed>>
     */
    private static function itemsToFacultyFormArray(array $items, array $fallbackItems): array
    {
        if ($items === []) {
            return self::featureItemsToFormArray($fallbackItems);
        }

        return array_values(array_map(
            static fn (array $item): array => array_filter([
                'title' => $item['title'] ?? '',
                'description' => self::firstString($item, ['description', 'summary', 'text']),
                'summary' => self::firstString($item, ['summary', 'description', 'text']),
                'image' => $item['image'] ?? ($item['imageUrl'] ?? null),
                'imageUrl' => $item['imageUrl'] ?? ($item['image'] ?? null),
                'icon' => $item['icon'] ?? null,
                'accent' => $item['accent'] ?? null,
                'metric' => $item['metric'] ?? null,
                'cta_label' => is_array($item['action'] ?? null) ? ($item['action']['label'] ?? null) : null,
                'cta_url' => is_array($item['action'] ?? null) ? ($item['action']['url'] ?? null) : null,
                'action' => $item['action'] ?? null,
            ], static fn (mixed $value): bool => $value !== null && $value !== []),
            $items,
        ));
    }

    /**
     * @param  array<int, array<string, mixed>>  $items
     * @param  array<int, HomepageFeatureItemDTO>  $fallbackItems
     * @return array<int, array<string, mixed>>
     */
    private static function itemsToHighlightFormArray(array $items, array $fallbackItems): array
    {
        if ($items === []) {
            return self::featureItemsToFormArray($fallbackItems);
        }

        return array_values(array_map(
            static fn (array $item): array => array_filter([
                'title' => $item['title'] ?? '',
                'text' => self::firstString($item, ['text', 'summary', 'description']),
                'description' => self::firstString($item, ['summary', 'description', 'text']),
                'summary' => self::firstString($item, ['summary', 'description', 'text']),
                'image' => $item['image'] ?? ($item['imageUrl'] ?? null),
                'imageUrl' => $item['imageUrl'] ?? ($item['image'] ?? null),
                'icon' => $item['icon'] ?? null,
                'metric' => $item['metric'] ?? null,
                'typeTag' => $item['typeTag'] ?? ($item['type_tag'] ?? null),
                'meta' => $item['meta'] ?? null,
                'cta_label' => is_array($item['action'] ?? null) ? ($item['action']['label'] ?? null) : null,
                'cta_url' => is_array($item['action'] ?? null) ? ($item['action']['url'] ?? null) : null,
                'action' => $item['action'] ?? null,
            ], static fn (mixed $value): bool => $value !== null && $value !== []),
            $items,
        ));
    }

    /**
     * @param  array<int, array<string, mixed>>  $items
     * @return array<int, array<string, mixed>>
     */
    private static function pathItemsToFormArray(array $items): array
    {
        return array_values(array_map(
            static fn (array $item): array => array_filter([
                'title' => $item['title'] ?? '',
                'icon' => $item['icon'] ?? null,
                'links' => array_values(array_map(
                    static fn (mixed $link): array => is_array($link)
                        ? ['label' => (string) ($link['label'] ?? ($link['text'] ?? '')), 'url' => $link['url'] ?? null]
                        : ['label' => (string) $link],
                    is_array($item['links'] ?? null) ? $item['links'] : [],
                )),
                'cta_label' => is_array($item['action'] ?? null) ? ($item['action']['label'] ?? null) : null,
                'cta_url' => is_array($item['action'] ?? null) ? ($item['action']['url'] ?? null) : null,
                'action' => $item['action'] ?? null,
            ], static fn (mixed $value): bool => $value !== null && $value !== []),
            $items,
        ));
    }

    /**
     * @param  array<int, array<string, mixed>>  $items
     * @return array<int, array<string, mixed>>
     */
    private static function itemsToFormArray(array $items): array
    {
        return array_values(array_map(
            static function (array $item): array {
                if (isset($item['imageUrl']) && ! isset($item['image'])) {
                    $item['image'] = $item['imageUrl'];
                }

                if (is_array($item['action'] ?? null)) {
                    $item['cta_label'] = $item['action']['label'] ?? ($item['cta_label'] ?? null);
                    $item['cta_url'] = $item['action']['url'] ?? ($item['cta_url'] ?? null);
                }

                return $item;
            },
            $items,
        ));
    }

    /**
     * @param  array<int, ArticleCardDTO>  $articles
     * @return array<int, array<string, mixed>>
     */
    private static function articlesToFormArray(array $articles): array
    {
        return array_values(array_map(
            static fn (ArticleCardDTO $article): array => [
                'id' => $article->id,
                'locale' => $article->locale,
                'title' => $article->title,
                'slug' => $article->slug,
                'excerpt' => $article->excerpt,
                'image' => $article->imageUrl,
                'imageUrl' => $article->imageUrl,
                'publish_date' => $article->publishedAt,
                'publishedAt' => $article->publishedAt,
                'category' => $article->categoryLabel,
                'categoryLabel' => $article->categoryLabel,
                'cta_url' => $article->url,
                'url' => $article->url,
            ],
            $articles,
        ));
    }

    /**
     * @param  array<int, ResearchCardDTO>  $items
     * @return array<int, array<string, mixed>>
     */
    private static function researchItemsToFormArray(array $items): array
    {
        return array_values(array_map(
            static fn (ResearchCardDTO $item): array => [
                'id' => $item->id,
                'locale' => $item->locale,
                'title' => $item->title,
                'slug' => $item->slug,
                'excerpt' => $item->summary,
                'summary' => $item->summary,
                'image' => $item->imageUrl,
                'imageUrl' => $item->imageUrl,
                'publish_date' => $item->publishedAt,
                'publishedAt' => $item->publishedAt,
                'category' => $item->categoryLabel,
                'categoryLabel' => $item->categoryLabel,
                'authors' => implode(', ', $item->authors),
                'cta_url' => $item->url,
                'url' => $item->url,
            ],
            $items,
        ));
    }

    /**
     * @param  array<int, EventCardDTO>  $events
     * @return array<int, array<string, mixed>>
     */
    private static function eventsToFormArray(array $events): array
    {
        return array_values(array_map(
            static fn (EventCardDTO $event): array => [
                'id' => $event->id,
                'locale' => $event->locale,
                'title' => $event->title,
                'slug' => $event->slug,
                'description' => $event->summary,
                'summary' => $event->summary,
                'date' => $event->startsAt,
                'startsAt' => $event->startsAt,
                'time' => $event->timeLabel,
                'timeLabel' => $event->timeLabel,
                'location' => $event->location,
                'image' => $event->imageUrl,
                'imageUrl' => $event->imageUrl,
                'cta_url' => $event->url,
                'url' => $event->url,
            ],
            $events,
        ));
    }

    /**
     * @param  array<string, mixed>  $content
     * @param  array<int, ContactLinkDTO>  $contactLinks
     * @return array<string, mixed>
     */
    private function withContactContentAliases(array $content, array $contactLinks): array
    {
        foreach ($contactLinks as $link) {
            $key = match (strtolower($link->type)) {
                'phone' => 'contact_phone',
                'email' => 'contact_email',
                'address' => 'contact_address',
                default => null,
            };

            if ($key !== null && ! isset($content[$key])) {
                $content[$key] = $link->value;
            }
        }

        return $content;
    }

    /**
     * @return array<int, array{path: string}>
     */
    private static function imagesToFormArray(mixed $images): array
    {
        return array_values(array_map(
            static fn (string $path): array => ['path' => $path],
            self::imagePathsFromFormValue($images),
        ));
    }

    /**
     * @return array<int, string>
     */
    private static function imagePathsFromFormValue(mixed $value): array
    {
        if (! is_array($value)) {
            return is_string($value) && $value !== '' ? [$value] : [];
        }

        $paths = [];

        foreach ($value as $item) {
            if (is_string($item) && $item !== '') {
                $paths[] = $item;

                continue;
            }

            if (! is_array($item)) {
                continue;
            }

            $path = self::extractFileUploadValue($item['path'] ?? ($item['image'] ?? ($item['url'] ?? null)));

            if ($path !== null) {
                $paths[] = $path;
            }
        }

        return array_values(array_filter($paths, static fn (string $path): bool => $path !== ''));
    }

    /** @param array<string, mixed> $content */
    private static function firstContentImage(array $content): ?string
    {
        $images = $content['images'] ?? null;

        if (! is_array($images)) {
            return null;
        }

        return array_values(array_filter($images, static fn (mixed $image): bool => is_string($image) && $image !== ''))[0] ?? null;
    }

    /** @return array<int, Tab> */
    private function buildSectionTabs(): array
    {
        $sectionLabels = [
            'hero' => 'Hero',
            'hero_stats' => 'Hero Stats',
            'achievements_highlights' => 'Achievements & Highlights',
            'academic_faculties' => 'Academic Faculties',
            'choose_your_path' => 'Choose Your Path',
            'research_studies' => 'Research Studies',
            'university_news' => 'University News',
            'events_activities' => 'Events & Activities',
            'medical_facilities_services' => 'Medical Facilities & Services',
            'bottom_stats' => 'Bottom Stats',
            'footer' => 'Footer',
        ];

        $tabs = [];

        foreach (HomepageSectionServiceInterface::SECTION_KEYS as $key) {
            $tabs[] = Tab::make($sectionLabels[$key] ?? $key)
                ->schema([
                    Tabs::make("{$key}_locales")
                        ->tabs([
                            Tab::make('العربية (AR)')
                                ->schema(HomepageFormSchema::fieldsForSection($key, "{$key}.ar"))
                                ->extraAttributes(['dir' => 'rtl'])
                                ->icon('heroicon-o-language'),
                            Tab::make('English (EN)')
                                ->schema(HomepageFormSchema::fieldsForSection($key, "{$key}.en"))
                                ->extraAttributes(['dir' => 'ltr'])
                                ->icon('heroicon-o-language'),
                        ]),
                ]);
        }

        return $tabs;
    }

    private function saveCurrentDraft(array $formData): HomepageDraftDTO
    {
        /** @var User $user */
        $user = auth()->user();
        $sectionDTOs = $this->buildSectionDTOsFromFormData($formData);
        $draftPayload = new HomepageDraftDataDTO(sections: $sectionDTOs);
        $draft = $this->publishingService->saveDraft($draftPayload, $user->id, $this->draftVersion);
        $this->draftVersion = $draft->version;

        return $draft;
    }

    private function notifyDraftConflict(ConflictException $exception): void
    {
        $this->draftVersion = $exception->currentVersion;

        Notification::make()
            ->title('Draft changed')
            ->body('The homepage draft changed while this page was open. Refresh the admin page, review the latest draft, then save or publish again.')
            ->warning()
            ->persistent()
            ->send();
    }

    /**
     * @return list<string>
     */
    private function publishValidationErrors(array $formData): array
    {
        $errors = [];
        $sections = $this->buildSectionDTOsFromFormData($formData);

        foreach ($sections as $section) {
            foreach (['ar' => $section->arabicPayload ?? $section->payload, 'en' => $section->englishPayload ?? $section->payload] as $locale => $payload) {
                $result = $this->sectionService->validateSectionPayload($section->key, $payload, $locale);

                if ($result->isValid) {
                    continue;
                }

                foreach ($result->errors as $error) {
                    foreach ($error->messages as $message) {
                        $errors[] = strtoupper($locale).' '.$section->key.'.'.$error->field.': '.$message;
                    }
                }
            }
        }

        return array_slice($errors, 0, 12);
    }

    /** @param list<string> $errors */
    private function formatValidationErrors(array $errors): string
    {
        return "Missing or invalid publish fields:\n- ".implode("\n- ", $errors);
    }

    /**
     * Filament FileUpload returns an array of paths inside repeaters.
     * Extract the first path as a string, or null if empty.
     */
    private static function extractFileUploadValue(mixed $value): ?string
    {
        if (is_array($value)) {
            return array_values(array_filter($value, static fn (mixed $v): bool => is_string($v) && $v !== ''))[0] ?? null;
        }

        return is_string($value) && $value !== '' ? $value : null;
    }

    /**
     * @param  array<int, string>  $keys
     */
    private static function firstString(array $item, array $keys): ?string
    {
        foreach ($keys as $key) {
            if (isset($item[$key]) && is_string($item[$key]) && $item[$key] !== '') {
                return $item[$key];
            }
        }

        return null;
    }

    private static function formAction(array $data, string $labelKey, string $urlKey): ?NavigationActionDTO
    {
        $label = self::firstString($data, [$labelKey]);
        $url = self::firstString($data, [$urlKey]);

        if ($label === null && $url === null) {
            return null;
        }

        return new NavigationActionDTO(
            label: $label ?? '',
            url: $url ?? '#',
        );
    }

    /** @return array<int, string> */
    private static function extractAuthors(mixed $value): array
    {
        if (is_array($value)) {
            return array_values(array_filter(
                array_map('trim', array_map('strval', $value)),
                static fn (string $a): bool => $a !== '',
            ));
        }
        if (is_string($value) && $value !== '') {
            return array_values(array_filter(
                array_map('trim', explode(',', $value)),
                static fn (string $a): bool => $a !== '',
            ));
        }

        return [];
    }
}
