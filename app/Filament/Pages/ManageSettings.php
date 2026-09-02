<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Contracts\Settings\SettingsServiceInterface;
use App\DTOs\Settings\SettingsDTO;
use App\DTOs\Settings\SettingValueDTO;
use App\Filament\Components\PageUrlSelect;
use App\Filament\Support\MediaPicker;
use App\Models\User\User;
use App\Rules\TrustedPortalUrlRule;
use App\Support\TrustedPortalUrl;
use Filament\Actions\Action;
use Filament\Forms\Components\Component;
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

/**
 * Filament custom page for managing grouped application settings.
 *
 * Groups: Utility Navigation, Footer, Emergency Notice, Contact, Social, SEO Defaults.
 * All business logic is delegated to SettingsServiceInterface.
 */
class ManageSettings extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-cog-6-tooth';

    protected static ?string $slug = 'manage-settings';

    protected static ?int $navigationSort = 5;

    protected static string $view = 'filament.pages.manage-settings';

    /** @var array<string, mixed> */
    public ?array $data = [];

    private SettingsServiceInterface $settingsService;

    public function boot(SettingsServiceInterface $settingsService): void
    {
        $this->settingsService = $settingsService;
    }

    public static function canAccess(): bool
    {
        return Gate::allows('manage-settings');
    }

    public static function getNavigationGroup(): ?string
    {
        return __('admin.navigation.groups.administration');
    }

    public static function getNavigationLabel(): string
    {
        return __('admin.navigation.items.settings');
    }

    public function getTitle(): string
    {
        return __('admin.pages.manage_settings');
    }

    public function mount(): void
    {
        $this->loadSettings();
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Tabs::make('settings_groups')
                    ->tabs([
                        $this->utilityNavigationTab(),
                        $this->footerTab(),
                        $this->emergencyNoticeTab(),
                        $this->contactTab(),
                        $this->socialTab(),
                        $this->seoDefaultsTab(),
                    ])
                    ->persistTabInQueryString('group')
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
            $this->saveAction(),
        ];
    }

    private function saveAction(): Action
    {
        return Action::make('save')
            ->label('Save Settings Group')
            ->icon('heroicon-o-check')
            ->color('success')
            ->form([
                Select::make('group')
                    ->label('Settings Group')
                    ->options([
                        'navigation' => 'Utility Navigation',
                        'footer' => 'Footer',
                        'emergency' => 'Emergency Notice',
                        'contact' => 'Contact',
                        'social' => 'Social',
                        'seo' => 'SEO Defaults',
                    ])
                    ->placeholder('Choose the group you are editing')
                    ->required(),
            ])
            ->action(function (array $data): void {
                $this->saveSettings((string) $data['group']);
            });
    }

    // ──────────────────────────────────────────────
    // Save Handler
    // ──────────────────────────────────────────────

    private function saveSettings(string $group): void
    {
        $formData = $this->form->getState();

        /** @var User $user */
        $user = auth()->user();

        try {
            match ($group) {
                'navigation' => $this->saveUtilityNavigation($formData, $user->id),
                'footer' => $this->saveFooter($formData, $user->id),
                'emergency' => $this->saveEmergencyNotice($formData, $user->id),
                'contact' => $this->saveContact($formData, $user->id),
                'social' => $this->saveSocial($formData, $user->id),
                'seo' => $this->saveSeoDefaults($formData, $user->id),
                default => throw new \InvalidArgumentException('Unsupported settings group.'),
            };

            Notification::make()
                ->title('Settings group saved successfully')
                ->success()
                ->send();
        } catch (\Throwable $e) {
            report($e);

            Notification::make()
                ->title('Failed to save settings')
                ->body('Please review the submitted settings and try again.')
                ->danger()
                ->send();
        }
    }

    public function save(): void
    {
        Notification::make()
            ->title('Use the Save Settings Group action')
            ->body('Choose the settings group to save from the page header action.')
            ->warning()
            ->send();
    }

    // ──────────────────────────────────────────────
    // Group Save Methods
    // ──────────────────────────────────────────────

    private function saveUtilityNavigation(array $formData, int $userId): void
    {
        foreach (['ar', 'en'] as $locale) {
            $prefix = "utility_{$locale}";
            $values = [
                new SettingValueDTO(
                    key: 'apply_cta',
                    type: 'json',
                    jsonValue: [
                        'label' => $formData["{$prefix}_apply_cta_label"] ?? '',
                        'url' => $formData["{$prefix}_apply_cta_url"] ?? '',
                        'is_enabled' => (bool) ($formData["{$prefix}_apply_cta_enabled"] ?? true),
                    ],
                    isPublic: true,
                ),
            ];

            $this->settingsService->updateGroup(
                new SettingsDTO('navigation', $locale, $values),
                $userId,
            );
        }

        // Non-locale settings
        $globalValues = [
            new SettingValueDTO(
                key: 'student_portal_url',
                type: 'text',
                textValue: $formData['utility_student_portal_url'] ?? '',
                isPublic: true,
            ),
            new SettingValueDTO(
                key: 'staff_access_url',
                type: 'text',
                textValue: $formData['utility_staff_access_url'] ?? '',
                isPublic: true,
            ),
        ];

        $this->settingsService->updateGroup(
            new SettingsDTO('navigation', null, $globalValues),
            $userId,
        );
    }

    private function saveFooter(array $formData, int $userId): void
    {
        foreach (['ar', 'en'] as $locale) {
            $prefix = "footer_{$locale}";
            $values = [
                new SettingValueDTO(
                    key: 'footer',
                    type: 'json',
                    jsonValue: [
                        'copyright_text' => $formData["{$prefix}_copyright"] ?? '',
                        'address' => $formData["{$prefix}_address"] ?? '',
                        'phone' => $formData["{$prefix}_phone"] ?? '',
                        'email' => $formData["{$prefix}_email"] ?? '',
                        'brand_block' => [
                            'title' => $formData["{$prefix}_brand_title"] ?? '',
                            'body' => $formData["{$prefix}_brand_summary"] ?? '',
                            'logo_url' => $formData["{$prefix}_logo_url"] ?? '',
                        ],
                        'map_embed' => [
                            'url' => $formData["{$prefix}_map_embed_url"] ?? '',
                        ],
                        'legal_links' => $formData["{$prefix}_legal_links"] ?? [],
                    ],
                    isPublic: true,
                ),
            ];

            $this->settingsService->updateGroup(
                new SettingsDTO('footer', $locale, $values),
                $userId,
            );
        }
    }

    private function saveEmergencyNotice(array $formData, int $userId): void
    {
        foreach (['ar', 'en'] as $locale) {
            $prefix = "emergency_{$locale}";
            $values = [
                new SettingValueDTO(
                    key: 'emergency_notice',
                    type: 'json',
                    jsonValue: [
                        'is_enabled' => (bool) ($formData["{$prefix}_enabled"] ?? false),
                        'title' => $formData["{$prefix}_title"] ?? '',
                        'message' => $formData["{$prefix}_message"] ?? '',
                        'url' => $formData["{$prefix}_url"] ?? '',
                    ],
                    isPublic: true,
                ),
            ];

            $this->settingsService->updateGroup(
                new SettingsDTO('public_shell', $locale, $values),
                $userId,
            );
        }
    }

    private function saveContact(array $formData, int $userId): void
    {
        foreach (['ar', 'en'] as $locale) {
            $prefix = "contact_{$locale}";
            $values = [
                new SettingValueDTO(
                    key: 'contact_links',
                    type: 'json',
                    jsonValue: [
                        'contact_links' => $formData["{$prefix}_links"] ?? [],
                    ],
                    isPublic: true,
                ),
            ];

            $this->settingsService->updateGroup(
                new SettingsDTO('footer', $locale, $values),
                $userId,
            );
        }
    }

    private function saveSocial(array $formData, int $userId): void
    {
        foreach (['ar', 'en'] as $locale) {
            $prefix = "social_{$locale}";
            $values = [
                new SettingValueDTO(
                    key: 'social_contact',
                    type: 'json',
                    jsonValue: [
                        'social_links' => $formData["{$prefix}_links"] ?? [],
                    ],
                    isPublic: true,
                ),
            ];

            $this->settingsService->updateGroup(
                new SettingsDTO('footer', $locale, $values),
                $userId,
            );
        }
    }

    private function saveSeoDefaults(array $formData, int $userId): void
    {
        foreach (['ar', 'en'] as $locale) {
            $prefix = "seo_{$locale}";
            $values = [
                new SettingValueDTO(
                    key: 'default_seo',
                    type: 'json',
                    jsonValue: [
                        'title' => $formData["{$prefix}_title"] ?? '',
                        'meta_description' => $formData["{$prefix}_meta_description"] ?? '',
                        'og_title' => $formData["{$prefix}_og_title"] ?? '',
                        'og_description' => $formData["{$prefix}_og_description"] ?? '',
                        'og_image' => $formData["{$prefix}_og_image"] ?? '',
                        'robots' => $formData["{$prefix}_robots"] ?? 'index,follow',
                    ],
                    isPublic: true,
                ),
            ];

            $this->settingsService->updateGroup(
                new SettingsDTO('seo', $locale, $values),
                $userId,
            );
        }
    }

    // ──────────────────────────────────────────────
    // Data Loading
    // ──────────────────────────────────────────────

    private function loadSettings(): void
    {
        $formData = [];

        $this->loadUtilityNavigation($formData);
        $this->loadFooter($formData);
        $this->loadEmergencyNotice($formData);
        $this->loadContact($formData);
        $this->loadSocial($formData);
        $this->loadSeoDefaults($formData);

        $this->form->fill($formData);
    }

    private function loadUtilityNavigation(array &$formData): void
    {
        foreach (['ar', 'en'] as $locale) {
            $prefix = "utility_{$locale}";
            $cta = $this->settingsService->getApplyCtaTarget($locale);
            $formData["{$prefix}_apply_cta_label"] = $cta->label;
            $formData["{$prefix}_apply_cta_url"] = $cta->url;
            $formData["{$prefix}_apply_cta_enabled"] = $cta->isEnabled;
        }

        // Show a stored value even when the trusted-host policy rejects it.
        // Blanking it hid the exact string that was breaking the public route,
        // and re-saving the blanked form wiped the setting outright - so the one
        // screen able to fix the problem was the one screen that never showed it.
        $formData['utility_student_portal_url'] = $this->portalFieldValue(
            'student_portal_url',
            $this->settingsService->getStudentPortalUrl(),
        );
        $formData['utility_staff_access_url'] = $this->portalFieldValue(
            'staff_access_url',
            $this->settingsService->getStaffAccessUrl(),
        );
    }

    /**
     * The resolved URL when the policy accepts it, otherwise the raw stored
     * value so an editor can see and correct exactly what was rejected.
     */
    private function portalFieldValue(string $key, ?string $resolved): string
    {
        if (is_string($resolved) && $resolved !== '') {
            return $resolved;
        }

        foreach ($this->settingsService->getGroup('navigation')->values as $value) {
            if ($value instanceof SettingValueDTO && $value->key === $key) {
                return (string) ($value->textValue ?? '');
            }
        }

        return '';
    }

    private static function portalHelperText(): string
    {
        $hosts = TrustedPortalUrl::trustedHosts();

        return sprintf(
            'An https:// URL on an approved host (%s), or a site-relative path beginning with "/". Leave empty for "no portal configured".',
            $hosts === [] ? 'none currently configured' : implode(', ', $hosts),
        );
    }

    private function loadFooter(array &$formData): void
    {
        foreach (['ar', 'en'] as $locale) {
            $prefix = "footer_{$locale}";
            $footer = $this->settingsService->getFooterSettings($locale);
            $formData["{$prefix}_copyright"] = $footer->copyrightText;
            $formData["{$prefix}_address"] = $footer->address;
            $formData["{$prefix}_phone"] = $footer->phone;
            $formData["{$prefix}_email"] = $footer->email;
            $formData["{$prefix}_brand_title"] = $footer->brandTitle;
            $formData["{$prefix}_brand_summary"] = $footer->brandSummary;
            $formData["{$prefix}_logo_url"] = $footer->logoUrl;
            $formData["{$prefix}_map_embed_url"] = $footer->mapEmbedUrl;
            $formData["{$prefix}_legal_links"] = array_map(
                fn ($link) => ['label' => $link->label, 'url' => $link->url],
                $footer->legalLinks,
            );
        }
    }

    private function loadEmergencyNotice(array &$formData): void
    {
        foreach (['ar', 'en'] as $locale) {
            $prefix = "emergency_{$locale}";
            $notice = $this->settingsService->getEmergencyNotice($locale);
            $formData["{$prefix}_enabled"] = $notice->isEnabled;
            $formData["{$prefix}_title"] = $notice->title;
            $formData["{$prefix}_message"] = $notice->message;
            $formData["{$prefix}_url"] = $notice->url;
        }
    }

    private function loadContact(array &$formData): void
    {
        foreach (['ar', 'en'] as $locale) {
            $prefix = "contact_{$locale}";
            $social = $this->settingsService->getSocialContactSettings($locale);
            $formData["{$prefix}_links"] = array_map(
                fn ($link) => ['type' => $link->type, 'label' => $link->label, 'value' => $link->value],
                $social->contactLinks,
            );
        }
    }

    private function loadSocial(array &$formData): void
    {
        foreach (['ar', 'en'] as $locale) {
            $prefix = "social_{$locale}";
            $social = $this->settingsService->getSocialContactSettings($locale);
            $formData["{$prefix}_links"] = array_map(
                fn ($link) => ['platform' => $link->platform, 'url' => $link->url, 'is_enabled' => $link->isEnabled],
                $social->socialLinks,
            );
        }
    }

    private function loadSeoDefaults(array &$formData): void
    {
        foreach (['ar', 'en'] as $locale) {
            $prefix = "seo_{$locale}";
            $seo = $this->settingsService->getDefaultSeoSettings($locale);
            $formData["{$prefix}_title"] = $seo->title;
            $formData["{$prefix}_meta_description"] = $seo->metaDescription;
            $formData["{$prefix}_og_title"] = $seo->ogTitle;
            $formData["{$prefix}_og_description"] = $seo->ogDescription;
            $formData["{$prefix}_og_image"] = $seo->ogImage;
            $formData["{$prefix}_robots"] = $seo->robots;
        }
    }

    // ──────────────────────────────────────────────
    // Tab Builders
    // ──────────────────────────────────────────────

    private function utilityNavigationTab(): Tab
    {
        return Tab::make('Utility Navigation')
            ->icon('heroicon-o-link')
            ->schema([
                Tabs::make('utility_locales')
                    ->tabs([
                        Tab::make('العربية (AR)')
                            ->extraAttributes(['dir' => 'rtl'])
                            ->schema($this->utilityNavigationFields('ar')),
                        Tab::make('English (EN)')
                            ->extraAttributes(['dir' => 'ltr'])
                            ->schema($this->utilityNavigationFields('en')),
                    ]),

                // TrustedPortalUrlRule replaces ->url(), which was wrong in both
                // directions: it accepted any host (so an untrusted URL saved
                // cleanly and 503'd the public transport-registration route), and
                // it rejected the site-relative paths the policy explicitly allows
                // - including the seeded default '/e-services/staff-email', which
                // made the whole Portal URLs section impossible to save.
                Section::make('Portal URLs')->schema([
                    TextInput::make('utility_student_portal_url')
                        ->label('Student Portal URL')
                        ->maxLength(2048)
                        ->helperText(self::portalHelperText())
                        ->rule(new TrustedPortalUrlRule),

                    TextInput::make('utility_staff_access_url')
                        ->label('Staff Access URL')
                        ->maxLength(2048)
                        ->helperText(self::portalHelperText())
                        ->rule(new TrustedPortalUrlRule),
                ]),
            ]);
    }

    /** @return array<int, Component> */
    private function utilityNavigationFields(string $locale): array
    {
        $prefix = "utility_{$locale}";

        return [
            Section::make('Apply CTA')->schema([
                TextInput::make("{$prefix}_apply_cta_label")
                    ->label('CTA Label')
                    ->maxLength(100),

                PageUrlSelect::make("{$prefix}_apply_cta_url", 'CTA URL', $locale),

                Toggle::make("{$prefix}_apply_cta_enabled")
                    ->label('Enabled')
                    ->default(true),
            ]),
        ];
    }

    private function footerTab(): Tab
    {
        return Tab::make('Footer')
            ->icon('heroicon-o-bars-3-bottom-left')
            ->schema([
                Tabs::make('footer_locales')
                    ->tabs([
                        Tab::make('العربية (AR)')
                            ->extraAttributes(['dir' => 'rtl'])
                            ->schema($this->footerFields('ar')),
                        Tab::make('English (EN)')
                            ->extraAttributes(['dir' => 'ltr'])
                            ->schema($this->footerFields('en')),
                    ]),
            ]);
    }

    /** @return array<int, Component> */
    private function footerFields(string $locale): array
    {
        $prefix = "footer_{$locale}";

        return [
            Section::make('Brand')->schema([
                TextInput::make("{$prefix}_brand_title")
                    ->label('Brand Title')
                    ->maxLength(255),

                Textarea::make("{$prefix}_brand_summary")
                    ->label('Brand Summary')
                    ->rows(3)
                    ->maxLength(1000),

                MediaPicker::image("{$prefix}_logo_url", 'Logo'),
            ]),

            Section::make('Contact')->schema([
                TextInput::make("{$prefix}_address")
                    ->label('Address')
                    ->maxLength(500),

                TextInput::make("{$prefix}_phone")
                    ->label('Phone')
                    ->maxLength(50),

                TextInput::make("{$prefix}_email")
                    ->label('Email')
                    ->email()
                    ->maxLength(255),
            ]),

            Section::make('Map & Legal')->schema([
                TextInput::make("{$prefix}_map_embed_url")
                    ->label('Map Embed URL')
                    ->url()
                    ->maxLength(2048),

                TextInput::make("{$prefix}_copyright")
                    ->label('Copyright Text')
                    ->maxLength(500),

                Repeater::make("{$prefix}_legal_links")
                    ->label('Legal Links')
                    ->schema([
                        TextInput::make('label')
                            ->label('Label')
                            ->required()
                            ->maxLength(100),
                        TextInput::make('url')
                            ->label('URL')
                            ->url()
                            ->required()
                            ->maxLength(2048),
                    ])
                    ->columns(2)
                    ->collapsible()
                    ->collapsed()
                    ->defaultItems(0),
            ]),
        ];
    }

    private function emergencyNoticeTab(): Tab
    {
        return Tab::make('Emergency Notice')
            ->icon('heroicon-o-exclamation-triangle')
            ->schema([
                Tabs::make('emergency_locales')
                    ->tabs([
                        Tab::make('العربية (AR)')
                            ->extraAttributes(['dir' => 'rtl'])
                            ->schema($this->emergencyNoticeFields('ar')),
                        Tab::make('English (EN)')
                            ->extraAttributes(['dir' => 'ltr'])
                            ->schema($this->emergencyNoticeFields('en')),
                    ]),
            ]);
    }

    /** @return array<int, Component> */
    private function emergencyNoticeFields(string $locale): array
    {
        $prefix = "emergency_{$locale}";

        return [
            Section::make('Emergency Notice')->schema([
                Toggle::make("{$prefix}_enabled")
                    ->label('Enabled')
                    ->default(false),

                TextInput::make("{$prefix}_title")
                    ->label('Title')
                    ->maxLength(255),

                Textarea::make("{$prefix}_message")
                    ->label('Message')
                    ->rows(4)
                    ->maxLength(2000),

                TextInput::make("{$prefix}_url")
                    ->label('Link URL')
                    ->url()
                    ->maxLength(2048),
            ]),
        ];
    }

    private function contactTab(): Tab
    {
        return Tab::make('Contact')
            ->icon('heroicon-o-phone')
            ->schema([
                Tabs::make('contact_locales')
                    ->tabs([
                        Tab::make('العربية (AR)')
                            ->extraAttributes(['dir' => 'rtl'])
                            ->schema($this->contactFields('ar')),
                        Tab::make('English (EN)')
                            ->extraAttributes(['dir' => 'ltr'])
                            ->schema($this->contactFields('en')),
                    ]),
            ]);
    }

    /** @return array<int, Component> */
    private function contactFields(string $locale): array
    {
        $prefix = "contact_{$locale}";

        return [
            Repeater::make("{$prefix}_links")
                ->label('Contact Links')
                ->schema([
                    TextInput::make('type')
                        ->label('Type')
                        ->placeholder('phone, email, fax, etc.')
                        ->required()
                        ->maxLength(50),
                    TextInput::make('label')
                        ->label('Label')
                        ->required()
                        ->maxLength(255),
                    TextInput::make('value')
                        ->label('Value')
                        ->required()
                        ->maxLength(500),
                ])
                ->columns(3)
                ->collapsible()
                ->collapsed()
                ->defaultItems(0),
        ];
    }

    private function socialTab(): Tab
    {
        return Tab::make('Social')
            ->icon('heroicon-o-share')
            ->schema([
                Tabs::make('social_locales')
                    ->tabs([
                        Tab::make('العربية (AR)')
                            ->extraAttributes(['dir' => 'rtl'])
                            ->schema($this->socialFields('ar')),
                        Tab::make('English (EN)')
                            ->extraAttributes(['dir' => 'ltr'])
                            ->schema($this->socialFields('en')),
                    ]),
            ]);
    }

    /** @return array<int, Component> */
    private function socialFields(string $locale): array
    {
        $prefix = "social_{$locale}";

        return [
            Repeater::make("{$prefix}_links")
                ->label('Social Links')
                ->schema([
                    TextInput::make('platform')
                        ->label('Platform')
                        ->placeholder('facebook, twitter, instagram, etc.')
                        ->required()
                        ->maxLength(50),
                    TextInput::make('url')
                        ->label('URL')
                        ->url()
                        ->required()
                        ->maxLength(2048),
                    Toggle::make('is_enabled')
                        ->label('Enabled')
                        ->default(true),
                ])
                ->columns(3)
                ->collapsible()
                ->collapsed()
                ->defaultItems(0),
        ];
    }

    private function seoDefaultsTab(): Tab
    {
        return Tab::make('SEO Defaults')
            ->icon('heroicon-o-magnifying-glass')
            ->schema([
                Tabs::make('seo_locales')
                    ->tabs([
                        Tab::make('العربية (AR)')
                            ->extraAttributes(['dir' => 'rtl'])
                            ->schema($this->seoDefaultsFields('ar')),
                        Tab::make('English (EN)')
                            ->extraAttributes(['dir' => 'ltr'])
                            ->schema($this->seoDefaultsFields('en')),
                    ]),
            ]);
    }

    /** @return array<int, Component> */
    private function seoDefaultsFields(string $locale): array
    {
        $prefix = "seo_{$locale}";

        return [
            Section::make('Default SEO Settings')->schema([
                TextInput::make("{$prefix}_title")
                    ->label('Default Title')
                    ->maxLength(70),

                Textarea::make("{$prefix}_meta_description")
                    ->label('Default Meta Description')
                    ->rows(3)
                    ->maxLength(160),

                TextInput::make("{$prefix}_og_title")
                    ->label('Default OG Title')
                    ->maxLength(70),

                Textarea::make("{$prefix}_og_description")
                    ->label('Default OG Description')
                    ->rows(3)
                    ->maxLength(200),

                MediaPicker::image("{$prefix}_og_image", 'Default OG Image'),

                TextInput::make("{$prefix}_robots")
                    ->label('Default Robots Directive')
                    ->placeholder('index,follow')
                    ->maxLength(100),
            ]),
        ];
    }
}
