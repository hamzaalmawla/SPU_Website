<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Contracts\HomepagePublishingServiceInterface;
use App\Contracts\HomepageSectionServiceInterface;
use App\Contracts\PreviewServiceInterface;
use App\DTOs\ArticleCardDTO;
use App\DTOs\ContactLinkDTO;
use App\DTOs\EventCardDTO;
use App\DTOs\FooterColumnDTO;
use App\DTOs\HomepageDraftDataDTO;
use App\DTOs\HomepageFeatureItemDTO;
use App\DTOs\HomepageSectionDataDTO;
use App\DTOs\HomepageSectionDTO;
use App\DTOs\HomepageStatItemDTO;
use App\DTOs\SocialLinkDTO;
use Filament\Actions\Action;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Tabs;
use Filament\Forms\Components\Tabs\Tab;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
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

    // ──────────────────────────────────────────────
    // Header Actions
    // ──────────────────────────────────────────────

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
            ->action(function (): void {
                $this->saveDraft();
            });
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
            ->visible(fn (): bool => \App\Models\HomepageDraft::query()
                ->where('target_type', 'homepage')
                ->whereIn('status', ['draft', 'scheduled'])
                ->exists()
            )
            ->action(function (): void {
                $this->discardDraft();
            });
    }

    private function previewArAction(): Action
    {
        return Action::make('previewAr')
            ->label('Preview (AR)')
            ->icon('heroicon-o-eye')
            ->color('info')
            ->action(function (): void {
                $this->openPreview('ar');
            });
    }

    private function previewEnAction(): Action
    {
        return Action::make('previewEn')
            ->label('Preview (EN)')
            ->icon('heroicon-o-eye')
            ->color('info')
            ->action(function (): void {
                $this->openPreview('en');
            });
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
            ->action(function (): void {
                $this->publishHomepage();
            });
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
            ->action(function (array $data): void {
                $this->schedulePublish($data['publish_at']);
            });
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
            ->action(function (): void {
                $this->unpublishHomepage();
            });
    }

    // ──────────────────────────────────────────────
    // Action Handlers (delegate to services)
    // ──────────────────────────────────────────────

    private function saveDraft(): void
    {
        $formData = $this->form->getState();
        $sectionDTOs = $this->buildSectionDTOsFromFormData($formData);

        $draftPayload = new HomepageDraftDataDTO(sections: $sectionDTOs);

        /** @var \App\Models\User $user */
        $user = auth()->user();

        $this->publishingService->saveDraft($draftPayload, $user->id);

        Notification::make()
            ->title('Draft saved successfully')
            ->success()
            ->send();
    }

    private function discardDraft(): void
    {
        $deleted = \App\Models\HomepageDraft::query()
            ->where('target_type', 'homepage')
            ->whereIn('status', ['draft', 'scheduled'])
            ->delete();

        if ($deleted > 0) {
            Notification::make()
                ->title('Draft discarded')
                ->success()
                ->send();
        } else {
            Notification::make()
                ->title('No draft to discard')
                ->warning()
                ->send();
        }
    }

    private function openPreview(string $locale): void
    {
        /** @var \App\Models\User $user */
        $user = auth()->user();

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
        /** @var \App\Models\User $user */
        $user = auth()->user();

        $formData = $this->form->getState();
        $sectionDTOs = $this->buildSectionDTOsFromFormData($formData);
        $draftPayload = new HomepageDraftDataDTO(sections: $sectionDTOs);

        $draft = $this->publishingService->saveDraft($draftPayload, $user->id);
        $result = $this->publishingService->publish($draft->id, $user->id);

        if ($result) {
            Notification::make()
                ->title('Homepage published successfully')
                ->success()
                ->send();
        } else {
            Notification::make()
                ->title('Publish failed')
                ->body('Please ensure all required content is filled in.')
                ->danger()
                ->send();
        }
    }

    private function schedulePublish(string $publishAt): void
    {
        /** @var \App\Models\User $user */
        $user = auth()->user();

        $formData = $this->form->getState();
        $sectionDTOs = $this->buildSectionDTOsFromFormData($formData);
        $draftPayload = new HomepageDraftDataDTO(sections: $sectionDTOs);

        $draft = $this->publishingService->saveDraft($draftPayload, $user->id);
        $result = $this->publishingService->schedulePublish(
            $draft->id,
            new \DateTimeImmutable($publishAt),
            $user->id,
        );

        if ($result) {
            Notification::make()
                ->title('Homepage scheduled for publication')
                ->body("Scheduled for: {$publishAt}")
                ->success()
                ->send();
        } else {
            Notification::make()
                ->title('Schedule failed')
                ->danger()
                ->send();
        }
    }

    private function unpublishHomepage(): void
    {
        /** @var \App\Models\User $user */
        $user = auth()->user();

        $result = $this->publishingService->unpublish('homepage', null, $user->id);

        if ($result) {
            Notification::make()
                ->title('Homepage unpublished')
                ->success()
                ->send();
        } else {
            Notification::make()
                ->title('Unpublish failed')
                ->danger()
                ->send();
        }
    }

    // ──────────────────────────────────────────────
    // State Badge
    // ──────────────────────────────────────────────

    public function getHomepageState(): string
    {
        $latestDraft = \App\Models\HomepageDraft::query()
            ->where('target_type', 'homepage')
            ->latest()
            ->first();

        if ($latestDraft === null) {
            return 'draft';
        }

        return $latestDraft->status ?? 'draft';
    }

    public function getStateBadgeColor(): string
    {
        return match ($this->getHomepageState()) {
            'published' => 'success',
            'scheduled' => 'warning',
            default => 'gray',
        };
    }

    // ──────────────────────────────────────────────
    // Data Loading
    // ──────────────────────────────────────────────

    private function loadSectionData(): void
    {
        $this->sections = $this->sectionService->getSections();

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
            'ar' => $this->payloadToFormArray($section->arabicPayload ?? $section->payload, $section->arabicTranslation),
            'en' => $this->payloadToFormArray($section->englishPayload ?? $section->payload, $section->englishTranslation),
        ];
    }

    private function payloadToFormArray(HomepageSectionDataDTO $payload, \App\DTOs\HomepageSectionTranslationDTO $translation): array
    {
        $toArray = fn ($obj): array => json_decode(json_encode($obj), true) ?? [];

        return [
            'headline' => $translation->headline ?? $payload->title,
            'subheadline' => $translation->body ?? $payload->subtitle,
            'background_image' => $payload->backgroundImageUrl,
            'video_url' => $payload->videoUrl,
            'image' => $payload->imageUrl,
            'primary_cta_label' => $payload->primaryAction?->label ?? $translation->ctaLabel,
            'primary_cta_url' => $payload->primaryAction?->url ?? null,
            'secondary_cta_label' => $payload->secondaryAction?->label ?? null,
            'secondary_cta_url' => $payload->secondaryAction?->url ?? null,
            'section_title' => $payload->title,
            'subtitle' => $payload->subtitle,
            'items' => is_array($payload->items) ? array_map($toArray, $payload->items) : [],
            'stats' => array_map($toArray, $payload->stats),
            'featured_items' => array_map($toArray, $payload->featuredItems),
            'articles' => array_map($toArray, $payload->articles),
            'research_items' => array_map($toArray, $payload->researchItems),
            'events' => array_map($toArray, $payload->events),
            'footer_columns' => array_map($toArray, $payload->footerColumns),
            'contact_links' => array_map($toArray, $payload->contactLinks),
            'social_links' => array_map($toArray, $payload->socialLinks),
            'content' => is_array($payload->content) ? $payload->content : ($toArray($payload->content) ?: []),
            'copyright_text' => is_array($payload->content) ? ($payload->content['copyright_text'] ?? null) : null,
            'logo' => is_array($payload->content) ? ($payload->content['logo'] ?? null) : null,
        ];
    }

    private function emptySectionData(string $key): array
    {
        return [
            'ar' => [],
            'en' => [],
        ];
    }

    // ──────────────────────────────────────────────
    // Form → DTO Conversion
    // ──────────────────────────────────────────────

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
                payload: $this->formArrayToPayload($sectionData['ar'] ?? []),
                arabicTranslation: new \App\DTOs\HomepageSectionTranslationDTO(
                    locale: 'ar',
                    headline: $sectionData['ar']['headline'] ?? null,
                    body: $sectionData['ar']['subheadline'] ?? null,
                    ctaLabel: $sectionData['ar']['primary_cta_label'] ?? null,
                ),
                englishTranslation: new \App\DTOs\HomepageSectionTranslationDTO(
                    locale: 'en',
                    headline: $sectionData['en']['headline'] ?? null,
                    body: $sectionData['en']['subheadline'] ?? null,
                    ctaLabel: $sectionData['en']['primary_cta_label'] ?? null,
                ),
                arabicPayload: $this->formArrayToPayload($sectionData['ar'] ?? []),
                englishPayload: $this->formArrayToPayload($sectionData['en'] ?? []),
            );
        }

        return $dtos;
    }

    private function formArrayToPayload(array $data): HomepageSectionDataDTO
    {
        return new HomepageSectionDataDTO(
            title: $data['section_title'] ?? $data['headline'] ?? null,
            subtitle: $data['subtitle'] ?? $data['subheadline'] ?? null,
            videoUrl: $data['video_url'] ?? null,
            imageUrl: $data['image'] ?? null,
            backgroundImageUrl: $data['background_image'] ?? null,
            primaryAction: isset($data['primary_cta_label'])
                ? new \App\DTOs\NavigationActionDTO(
                    label: $data['primary_cta_label'],
                    url: $data['primary_cta_url'] ?? '#',
                )
                : null,
            secondaryAction: isset($data['secondary_cta_label'])
                ? new \App\DTOs\NavigationActionDTO(
                    label: $data['secondary_cta_label'],
                    url: $data['secondary_cta_url'] ?? '#',
                )
                : null,
            stats: array_values(array_map(
                static fn (array $item): HomepageStatItemDTO => new HomepageStatItemDTO(
                    value: (string) ($item['value'] ?? ''),
                    label: (string) ($item['label'] ?? ''),
                    icon: isset($item['icon']) && $item['icon'] !== '' ? (string) $item['icon'] : null,
                    prefix: isset($item['prefix']) && $item['prefix'] !== '' ? (string) $item['prefix'] : null,
                    suffix: isset($item['suffix']) && $item['suffix'] !== '' ? (string) $item['suffix'] : null,
                ),
                array_filter($data['stats'] ?? [], static fn (mixed $i): bool => is_array($i)),
            )),
            featuredItems: array_values(array_map(
                static fn (array $item): HomepageFeatureItemDTO => new HomepageFeatureItemDTO(
                    title: (string) ($item['title'] ?? ''),
                    summary: isset($item['description']) && $item['description'] !== '' ? (string) $item['description'] : (isset($item['text']) && $item['text'] !== '' ? (string) $item['text'] : null),
                    imageUrl: self::extractFileUploadValue($item['image'] ?? null),
                    url: isset($item['cta_url']) && $item['cta_url'] !== '' ? (string) $item['cta_url'] : null,
                ),
                array_filter($data['featured_items'] ?? [], static fn (mixed $i): bool => is_array($i)),
            )),
            articles: array_values(array_map(
                static fn (array $item): ArticleCardDTO => new ArticleCardDTO(
                    id: 0,
                    locale: 'ar',
                    title: (string) ($item['title'] ?? ''),
                    slug: '',
                    excerpt: isset($item['excerpt']) && $item['excerpt'] !== '' ? (string) $item['excerpt'] : null,
                    imageUrl: self::extractFileUploadValue($item['image'] ?? null),
                    publishedAt: isset($item['publish_date']) && $item['publish_date'] !== '' ? (string) $item['publish_date'] : null,
                    url: isset($item['cta_url']) && $item['cta_url'] !== '' ? (string) $item['cta_url'] : null,
                    categoryLabel: isset($item['category']) && $item['category'] !== '' ? (string) $item['category'] : null,
                ),
                array_filter($data['articles'] ?? [], static fn (mixed $i): bool => is_array($i)),
            )),
            researchItems: array_values(array_map(
                static fn (array $item): \App\DTOs\ResearchCardDTO => new \App\DTOs\ResearchCardDTO(
                    id: 0,
                    locale: 'ar',
                    title: (string) ($item['title'] ?? ''),
                    slug: '',
                    summary: isset($item['excerpt']) && $item['excerpt'] !== '' ? (string) $item['excerpt'] : null,
                    imageUrl: self::extractFileUploadValue($item['image'] ?? null),
                    publishedAt: isset($item['publish_date']) && $item['publish_date'] !== '' ? (string) $item['publish_date'] : null,
                    url: isset($item['cta_url']) && $item['cta_url'] !== '' ? (string) $item['cta_url'] : null,
                    categoryLabel: isset($item['category']) && $item['category'] !== '' ? (string) $item['category'] : null,
                    authors: self::extractAuthors($item['authors'] ?? null),
                ),
                array_filter($data['research_items'] ?? [], static fn (mixed $i): bool => is_array($i)),
            )),
            events: array_values(array_map(
                static fn (array $item): EventCardDTO => new EventCardDTO(
                    id: 0,
                    locale: 'ar',
                    title: (string) ($item['title'] ?? ''),
                    slug: '',
                    summary: isset($item['description']) && $item['description'] !== '' ? (string) $item['description'] : null,
                    startsAt: isset($item['date']) && $item['date'] !== '' ? (string) $item['date'] : null,
                    endsAt: null,
                    location: isset($item['location']) && $item['location'] !== '' ? (string) $item['location'] : null,
                    url: isset($item['cta_url']) && $item['cta_url'] !== '' ? (string) $item['cta_url'] : null,
                    imageUrl: self::extractFileUploadValue($item['image'] ?? null),
                    timeLabel: isset($item['time']) && $item['time'] !== '' ? (string) $item['time'] : null,
                ),
                array_filter($data['events'] ?? [], static fn (mixed $i): bool => is_array($i)),
            )),
            footerColumns: array_values(array_map(
                static fn (array $col): FooterColumnDTO => new FooterColumnDTO(
                    title: (string) ($col['title'] ?? ''),
                    links: array_values(array_filter(array_map(
                        static fn (mixed $link): ?\App\DTOs\NavigationActionDTO => is_array($link) && isset($link['label'], $link['url'])
                            ? new \App\DTOs\NavigationActionDTO(label: (string) $link['label'], url: (string) $link['url'])
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
                array_filter($data['contact_links'] ?? [], static fn (mixed $i): bool => is_array($i)),
            )),
            socialLinks: array_values(array_map(
                static fn (array $item): SocialLinkDTO => new SocialLinkDTO(
                    platform: (string) ($item['platform'] ?? ''),
                    url: (string) ($item['url'] ?? ''),
                    isEnabled: (bool) ($item['is_enabled'] ?? true),
                ),
                array_filter($data['social_links'] ?? [], static fn (mixed $i): bool => is_array($i)),
            )),
            items: array_values(array_filter($data['items'] ?? [], static fn (mixed $i): bool => is_array($i))),
            content: array_filter([
                'copyright_text' => $data['copyright_text'] ?? null,
                'logo' => $data['logo'] ?? null,
            ]),
        );
    }

    // ──────────────────────────────────────────────
    // Section Tab Builders
    // ──────────────────────────────────────────────

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
                                ->schema($this->buildSectionFields($key, 'ar'))
                                ->icon('heroicon-o-language'),
                            Tab::make('English (EN)')
                                ->schema($this->buildSectionFields($key, 'en'))
                                ->icon('heroicon-o-language'),
                        ]),
                ]);
        }

        return $tabs;
    }

    /** @return array<int, \Filament\Forms\Components\Component> */
    private function buildSectionFields(string $sectionKey, string $locale): array
    {
        $prefix = "{$sectionKey}.{$locale}";

        return match ($sectionKey) {
            'hero' => $this->heroFields($prefix),
            'hero_stats' => $this->heroStatsFields($prefix),
            'academic_faculties' => $this->academicFacultiesFields($prefix),
            'achievements_highlights' => $this->achievementsHighlightsFields($prefix),
            'choose_your_path' => $this->chooseYourPathFields($prefix),
            'university_news' => $this->universityNewsFields($prefix),
            'research_studies' => $this->researchStudiesFields($prefix),
            'events_activities' => $this->eventsActivitiesFields($prefix),
            'medical_facilities_services' => $this->medicalFacilitiesFields($prefix),
            'bottom_stats' => $this->bottomStatsFields($prefix),
            'footer' => $this->footerFields($prefix),
        };
    }

    // ──────────────────────────────────────────────
    // Individual Section Field Schemas
    // ──────────────────────────────────────────────

    /** @return array<int, \Filament\Forms\Components\Component> */
    private function heroFields(string $prefix): array
    {
        return [
            Section::make('Hero Content')->schema([
                FileUpload::make("{$prefix}.background_image")
                    ->label('Background Image')
                    ->image()
                    ->directory('homepage/hero')
                    ->maxSize(5120),
                TextInput::make("{$prefix}.video_url")
                    ->label('Video URL')
                    ->maxLength(2048),
                TextInput::make("{$prefix}.headline")
                    ->label('Headline')
                    ->maxLength(255),
                Textarea::make("{$prefix}.subheadline")
                    ->label('Subheadline')
                    ->rows(3)
                    ->maxLength(500),
            ]),
            Section::make('Call to Action')->schema([
                TextInput::make("{$prefix}.primary_cta_label")
                    ->label('Primary CTA Label')
                    ->maxLength(100),
                TextInput::make("{$prefix}.primary_cta_url")
                    ->label('Primary CTA URL')
                    ->maxLength(2048),
                TextInput::make("{$prefix}.secondary_cta_label")
                    ->label('Secondary CTA Label')
                    ->maxLength(100),
                TextInput::make("{$prefix}.secondary_cta_url")
                    ->label('Secondary CTA URL')
                    ->maxLength(2048),
            ]),
        ];
    }

    /** @return array<int, \Filament\Forms\Components\Component> */
    private function heroStatsFields(string $prefix): array
    {
        return [
            Repeater::make("{$prefix}.stats")
                ->label('Statistics')
                ->schema([
                    TextInput::make('value')
                        ->label('Value')
                        ->required()
                        ->maxLength(50),
                    TextInput::make('label')
                        ->label('Label')
                        ->required()
                        ->maxLength(100),
                    TextInput::make('suffix')
                        ->label('Suffix')
                        ->maxLength(20),
                    TextInput::make('prefix')
                        ->label('Prefix')
                        ->maxLength(20),
                    TextInput::make('icon')
                        ->label('Icon')
                        ->maxLength(100),
                ])
                ->columns(3)
                ->collapsible()
                ->defaultItems(0),
        ];
    }

    /** @return array<int, \Filament\Forms\Components\Component> */
    private function academicFacultiesFields(string $prefix): array
    {
        return [
            Section::make('Section Header')->schema([
                TextInput::make("{$prefix}.section_title")
                    ->label('Section Title')
                    ->maxLength(255),
                TextInput::make("{$prefix}.subtitle")
                    ->label('Subtitle')
                    ->maxLength(500),
            ]),
            Repeater::make("{$prefix}.featured_items")
                ->label('Faculty Cards')
                ->schema([
                    TextInput::make('title')
                        ->label('Title')
                        ->required()
                        ->maxLength(255),
                    Textarea::make('description')
                        ->label('Description')
                        ->rows(2)
                        ->maxLength(500),
                    TextInput::make('icon')
                        ->label('Icon')
                        ->maxLength(100),
                    TextInput::make('cta_label')
                        ->label('CTA Label')
                        ->maxLength(100),
                    TextInput::make('cta_url')
                        ->label('CTA URL')
                        ->maxLength(2048),
                ])
                ->columns(2)
                ->collapsible()
                ->defaultItems(0),
        ];
    }

    /** @return array<int, \Filament\Forms\Components\Component> */
    private function achievementsHighlightsFields(string $prefix): array
    {
        return [
            Section::make('Section Header')->schema([
                TextInput::make("{$prefix}.section_title")
                    ->label('Section Title')
                    ->maxLength(255),
                TextInput::make("{$prefix}.subtitle")
                    ->label('Subtitle')
                    ->maxLength(500),
            ]),
            Repeater::make("{$prefix}.featured_items")
                ->label('Highlight Cards')
                ->schema([
                    TextInput::make('title')
                        ->label('Title')
                        ->required()
                        ->maxLength(255),
                    Textarea::make('text')
                        ->label('Text')
                        ->rows(2)
                        ->maxLength(500),
                    TextInput::make('icon')
                        ->label('Icon')
                        ->maxLength(100),
                    TextInput::make('metric')
                        ->label('Metric')
                        ->maxLength(100),
                    TextInput::make('cta_label')
                        ->label('CTA Label')
                        ->maxLength(100),
                    TextInput::make('cta_url')
                        ->label('CTA URL')
                        ->maxLength(2048),
                ])
                ->columns(2)
                ->collapsible()
                ->defaultItems(0),
        ];
    }

    /** @return array<int, \Filament\Forms\Components\Component> */
    private function chooseYourPathFields(string $prefix): array
    {
        return [
            Section::make('Section Header')->schema([
                TextInput::make("{$prefix}.section_title")
                    ->label('Section Title')
                    ->maxLength(255),
            ]),
            Repeater::make("{$prefix}.path_items")
                ->label('Path Cards')
                ->schema([
                    TextInput::make('title')
                        ->label('Title')
                        ->required()
                        ->maxLength(255),
                    TextInput::make('icon')
                        ->label('Icon Path')
                        ->maxLength(500),
                    Repeater::make('links')
                        ->label('Quick Links')
                        ->schema([
                            TextInput::make('label')
                                ->label('Link Label')
                                ->required()
                                ->maxLength(255),
                        ])
                        ->defaultItems(0)
                        ->collapsible(),
                    TextInput::make('cta_label')
                        ->label('CTA Label')
                        ->maxLength(100),
                    TextInput::make('cta_url')
                        ->label('CTA URL')
                        ->maxLength(2048),
                ])
                ->columns(2)
                ->collapsible()
                ->defaultItems(0),
        ];
    }

    /** @return array<int, \Filament\Forms\Components\Component> */
    private function universityNewsFields(string $prefix): array
    {
        return [
            Section::make('Section Header')->schema([
                TextInput::make("{$prefix}.section_title")
                    ->label('Section Title')
                    ->maxLength(255),
            ]),
            Repeater::make("{$prefix}.articles")
                ->label('News Cards')
                ->schema([
                    FileUpload::make('image')
                        ->label('Image')
                        ->image()
                        ->directory('homepage/news'),
                    TextInput::make('title')
                        ->label('Title')
                        ->required()
                        ->maxLength(255),
                    Textarea::make('excerpt')
                        ->label('Excerpt')
                        ->rows(2)
                        ->maxLength(500),
                    TextInput::make('publish_date')
                        ->label('Publish Date')
                        ->maxLength(50),
                    TextInput::make('category')
                        ->label('Category')
                        ->maxLength(100),
                    TextInput::make('cta_url')
                        ->label('CTA URL')
                        ->maxLength(2048),
                ])
                ->columns(2)
                ->collapsible()
                ->defaultItems(0),
        ];
    }

    /** @return array<int, \Filament\Forms\Components\Component> */
    private function researchStudiesFields(string $prefix): array
    {
        return [
            Section::make('Section Header')->schema([
                TextInput::make("{$prefix}.section_title")
                    ->label('Section Title')
                    ->maxLength(255),
            ]),
            Repeater::make("{$prefix}.research_items")
                ->label('Research Cards')
                ->schema([
                    FileUpload::make('image')
                        ->label('Image')
                        ->image()
                        ->directory('homepage/research'),
                    TextInput::make('title')
                        ->label('Title')
                        ->required()
                        ->maxLength(255),
                    Textarea::make('excerpt')
                        ->label('Excerpt')
                        ->rows(2)
                        ->maxLength(500),
                    TextInput::make('publish_date')
                        ->label('Publish Date')
                        ->maxLength(50),
                    TextInput::make('category')
                        ->label('Category')
                        ->maxLength(100),
                    TextInput::make('authors')
                        ->label('Authors')
                        ->maxLength(500),
                    TextInput::make('cta_url')
                        ->label('CTA URL')
                        ->maxLength(2048),
                ])
                ->columns(2)
                ->collapsible()
                ->defaultItems(0),
        ];
    }

    /** @return array<int, \Filament\Forms\Components\Component> */
    private function eventsActivitiesFields(string $prefix): array
    {
        return [
            Section::make('Section Header')->schema([
                TextInput::make("{$prefix}.section_title")
                    ->label('Section Title')
                    ->maxLength(255),
            ]),
            Repeater::make("{$prefix}.events")
                ->label('Event Cards')
                ->schema([
                    FileUpload::make('image')
                        ->label('Image')
                        ->image()
                        ->directory('homepage/events'),
                    TextInput::make('title')
                        ->label('Title')
                        ->required()
                        ->maxLength(255),
                    TextInput::make('date')
                        ->label('Date')
                        ->maxLength(50),
                    TextInput::make('time')
                        ->label('Time')
                        ->maxLength(50),
                    TextInput::make('location')
                        ->label('Location')
                        ->maxLength(255),
                    Textarea::make('description')
                        ->label('Description')
                        ->rows(2)
                        ->maxLength(500),
                    TextInput::make('cta_url')
                        ->label('CTA URL')
                        ->maxLength(2048),
                ])
                ->columns(2)
                ->collapsible()
                ->defaultItems(0),
        ];
    }

    /** @return array<int, \Filament\Forms\Components\Component> */
    private function medicalFacilitiesFields(string $prefix): array
    {
        return [
            Section::make('Section Header')->schema([
                TextInput::make("{$prefix}.section_title")
                    ->label('Section Title')
                    ->maxLength(255),
            ]),
            Repeater::make("{$prefix}.items")
                ->label('Service Cards')
                ->schema([
                    TextInput::make('title')
                        ->label('Title')
                        ->required()
                        ->maxLength(255),
                    Textarea::make('description')
                        ->label('Description')
                        ->rows(2)
                        ->maxLength(500),
                    FileUpload::make('image')
                        ->label('Image')
                        ->image()
                        ->directory('homepage/medical'),
                    TextInput::make('cta_label')
                        ->label('CTA Label')
                        ->maxLength(100),
                    TextInput::make('cta_url')
                        ->label('CTA URL')
                        ->maxLength(2048),
                ])
                ->columns(2)
                ->collapsible()
                ->defaultItems(0),
        ];
    }

    /** @return array<int, \Filament\Forms\Components\Component> */
    private function bottomStatsFields(string $prefix): array
    {
        return [
            Repeater::make("{$prefix}.stats")
                ->label('Statistics')
                ->schema([
                    TextInput::make('value')
                        ->label('Value')
                        ->required()
                        ->maxLength(50),
                    TextInput::make('label')
                        ->label('Label')
                        ->required()
                        ->maxLength(100),
                    TextInput::make('suffix')
                        ->label('Suffix')
                        ->maxLength(20),
                    TextInput::make('prefix')
                        ->label('Prefix')
                        ->maxLength(20),
                ])
                ->columns(2)
                ->collapsible()
                ->defaultItems(0),
        ];
    }

    /** @return array<int, \Filament\Forms\Components\Component> */
    private function footerFields(string $prefix): array
    {
        return [
            Section::make('Brand & Contact')->schema([
                FileUpload::make("{$prefix}.logo")
                    ->label('Footer Logo')
                    ->image()
                    ->directory('homepage/footer'),
                TextInput::make("{$prefix}.content.contact_phone")
                    ->label('Contact Phone')
                    ->tel()
                    ->maxLength(50),
                TextInput::make("{$prefix}.content.contact_email")
                    ->label('Contact Email')
                    ->email()
                    ->maxLength(255),
                Textarea::make("{$prefix}.content.contact_address")
                    ->label('Contact Address')
                    ->rows(2)
                    ->maxLength(500),
            ]),
            Repeater::make("{$prefix}.social_links")
                ->label('Social Links')
                ->schema([
                    TextInput::make('platform')
                        ->label('Platform')
                        ->required()
                        ->maxLength(50),
                    TextInput::make('url')
                        ->label('URL')
                        ->required()
                        ->maxLength(2048),
                    TextInput::make('icon')
                        ->label('Icon')
                        ->maxLength(100),
                ])
                ->columns(3)
                ->collapsible()
                ->defaultItems(0),
            Repeater::make("{$prefix}.footer_columns")
                ->label('Navigation Groups')
                ->schema([
                    TextInput::make('title')
                        ->label('Group Title')
                        ->required()
                        ->maxLength(255),
                    Repeater::make('links')
                        ->label('Links')
                        ->schema([
                            TextInput::make('label')
                                ->label('Label')
                                ->required()
                                ->maxLength(255),
                            TextInput::make('url')
                                ->label('URL')
                                ->required()
                                ->maxLength(2048),
                        ])
                        ->columns(2)
                        ->defaultItems(0),
                ])
                ->collapsible()
                ->defaultItems(0),
            Repeater::make("{$prefix}.content.legal_links")
                ->label('Legal Links')
                ->schema([
                    TextInput::make('label')
                        ->label('Label')
                        ->required()
                        ->maxLength(255),
                    TextInput::make('url')
                        ->label('URL')
                        ->required()
                        ->maxLength(2048),
                ])
                ->columns(2)
                ->collapsible()
                ->defaultItems(0),
            Section::make('Copyright')->schema([
                TextInput::make("{$prefix}.copyright_text")
                    ->label('Copyright Text')
                    ->maxLength(500),
            ]),
        ];
    }

    // ──────────────────────────────────────────────
    // Static Helpers
    // ──────────────────────────────────────────────

    /**
     * Filament FileUpload returns an array of paths inside repeaters.
     * Extract the first path as a string, or null if empty.
     */
    private static function extractFileUploadValue(mixed $value): ?string
    {
        if (is_array($value)) {
            $first = array_values(array_filter($value, static fn (mixed $v): bool => is_string($v) && $v !== ''))[0] ?? null;

            return $first;
        }

        return is_string($value) && $value !== '' ? $value : null;
    }

    /**
     * Authors field is a plain TextInput (comma-separated string) but may arrive
     * as an array after Livewire re-hydration. Normalise to string[] either way.
     *
     * @return array<int, string>
     */
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
