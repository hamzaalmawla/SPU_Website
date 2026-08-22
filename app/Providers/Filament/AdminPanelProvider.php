<?php

declare(strict_types=1);

namespace App\Providers\Filament;

use App\Http\Controllers\Admin\AuthController;
use App\Http\Middleware\AdminAuthMiddleware;
use App\Http\Middleware\AdminLocaleMiddleware;
use App\Http\Middleware\TwoFactorChallengeMiddleware;
use Filament\Http\Middleware\AuthenticateSession;
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
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->path('admin')
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
                NavigationGroup::make(fn (): string => __('admin.navigation.groups.content')),
                NavigationGroup::make(fn (): string => __('admin.navigation.groups.news')),
                NavigationGroup::make(fn (): string => __('admin.navigation.groups.facilities')),
                NavigationGroup::make(fn (): string => __('admin.navigation.groups.about')),
                NavigationGroup::make(fn (): string => __('admin.navigation.groups.admissions')),
                NavigationGroup::make(fn (): string => __('admin.navigation.groups.campus_life')),
                NavigationGroup::make(fn (): string => __('admin.navigation.groups.research')),
                NavigationGroup::make(fn (): string => __('admin.navigation.groups.contact')),
                NavigationGroup::make(fn (): string => __('admin.navigation.groups.e_services')),
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
                PanelsRenderHook::TOPBAR_END,
                fn (): string => view('filament.admin.locale-switcher')->render(),
            )
            ->renderHook(
                PanelsRenderHook::PAGE_HEADER_WIDGETS_BEFORE,
                fn (array $scopes): string => view('filament.admin.resource-workspace', ['scopes' => $scopes])->render(),
            )
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\\Filament\\Widgets')
            ->widgets([
                Widgets\AccountWidget::class,
            ])
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                VerifyCsrfToken::class,
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
