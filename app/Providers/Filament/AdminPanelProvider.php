<?php

declare(strict_types=1);

namespace App\Providers\Filament;

use App\Filament\Support\DefaultFieldLabels;
use App\Http\Controllers\Admin\AuthController;
use App\Http\Middleware\AdminAuthMiddleware;
use App\Http\Middleware\AdminLocaleMiddleware;
use App\Http\Middleware\AuthenticateAdminSession;
use App\Http\Middleware\TwoFactorChallengeMiddleware;
use App\Http\Middleware\ValidateCsrfToken;
use App\Support\AdminPath;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Navigation\NavigationGroup;
use Filament\Pages;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\Support\Enums\MaxWidth;
use Filament\View\PanelsRenderHook;
use Filament\Widgets;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class AdminPanelProvider extends PanelProvider
{
    public function boot(): void
    {
        DefaultFieldLabels::register();
    }

    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->path(AdminPath::prefix())
            ->brandName(fn (): string => __('admin.panel.brand'))
            ->brandLogo(fn (): string => asset('images/single-logo.png'))
            ->brandLogoHeight('2.35rem')
            ->favicon(asset('images/single-logo.png'))
            ->authGuard((string) config('auth.admin_guard', 'web'))
            ->login([AuthController::class, 'create'])
            ->colors([
                'primary' => Color::hex('#202759'),
                'danger' => Color::Red,
                'warning' => Color::Amber,
            ])
            ->maxContentWidth(MaxWidth::Full)
            ->sidebarCollapsibleOnDesktop()
            ->unsavedChangesAlerts()
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\\Filament\\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\\Filament\\Pages')
            ->navigationGroups([
                // Five groups (2026-11 refresh). Every screen names one of these in
                // getNavigationGroup(); the older per-area group labels stay in the
                // lang files because user scopes still use them as area names.
                NavigationGroup::make(fn (): string => __('admin.navigation.groups.content')),
                NavigationGroup::make(fn (): string => __('admin.navigation.groups.site_sections')),
                NavigationGroup::make(fn (): string => __('admin.navigation.groups.academic')),
                NavigationGroup::make(fn (): string => __('admin.navigation.groups.inbox')),
                NavigationGroup::make(fn (): string => __('admin.navigation.groups.administration')),
            ])
            ->pages([
                Pages\Dashboard::class,
            ])
            ->renderHook(
                PanelsRenderHook::HEAD_END,
                fn (): string => view('filament.admin.styles')->render(),
            )
            ->renderHook(
                PanelsRenderHook::BODY_START,
                fn (): string => view('filament.admin.skip-link')->render(),
            )
            ->renderHook(
                PanelsRenderHook::CONTENT_START,
                fn (): string => '<span id="spu-main-content" tabindex="-1" class="spu-skip-target"></span>',
            )
            ->renderHook(
                PanelsRenderHook::TOPBAR_END,
                fn (): string => view('filament.admin.locale-switcher')->render(),
            )
            ->renderHook(
                PanelsRenderHook::PAGE_HEADER_WIDGETS_BEFORE,
                fn (array $scopes): string => view('filament.admin.resource-workspace', ['scopes' => $scopes])->render(),
            )
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\\Filament\\Widgets')
            // The dashboard's widgets are discovered from app/Filament/Widgets: visitor
            // statistics, the work queue and recent publishing (Filament's own
            // account card is left out; the user menu already has it).
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateAdminSession::class,
                ShareErrorsFromSession::class,
                ValidateCsrfToken::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->authMiddleware([
                AdminLocaleMiddleware::class,
                AdminAuthMiddleware::class,
                TwoFactorChallengeMiddleware::class,
            ], isPersistent: true);
    }
}
