<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Contracts\Auth\AdminAccessServiceInterface;
use App\Contracts\Auth\TotpAuthenticatorInterface;
use App\Models\User\User;
use Filament\Actions\Action;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

/**
 * Filament custom page for managing TOTP two-factor authentication.
 *
 * Allows users to enable/disable 2FA, view QR code for enrollment,
 * view recovery codes, and regenerate recovery codes.
 */
class TwoFactorSetup extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-shield-check';

    protected static ?string $slug = 'two-factor-setup';

    protected static ?int $navigationSort = 10;

    protected static string $view = 'filament.pages.two-factor-setup';

    /** @var array<string, mixed> */
    public ?array $data = [];

    public bool $showEnrollment = false;

    public string $qrCodeUrl = '';

    public string $secret = '';

    /** @var list<string> */
    public array $recoveryCodes = [];

    public bool $twoFactorEnabled = false;

    public bool $twoFactorUnconfirmed = false;

    public bool $showRecoveryCodes = false;

    private TotpAuthenticatorInterface $authenticator;

    public function boot(TotpAuthenticatorInterface $authenticator): void
    {
        $this->authenticator = $authenticator;
    }

    public static function canAccess(): bool
    {
        $user = auth()->user();

        return $user instanceof User
            && ! $user->isAccountLocked()
            && app(AdminAccessServiceInterface::class)->hasAdminAccess($user);
    }

    public static function getNavigationGroup(): ?string
    {
        return __('admin.navigation.groups.administration');
    }

    public static function getNavigationLabel(): string
    {
        return __('admin.navigation.items.two_factor_auth');
    }

    public function getTitle(): string
    {
        return __('admin.pages.two_factor_authentication');
    }

    public function mount(): void
    {
        /** @var User $user */
        $user = auth()->user();

        abort_unless($user instanceof User && static::canAccess(), 403);

        $this->twoFactorEnabled = (bool) $user->two_factor_enabled && $user->two_factor_confirmed_at !== null;
        $this->twoFactorUnconfirmed = (bool) $user->two_factor_enabled && $user->two_factor_confirmed_at === null;
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make(__('admin_pages.two_factor.confirm_totp_code'))
                    ->description(__('admin_pages.two_factor.enter_the_6_digit_code_from_your_authenticator_app_to_comple'))
                    ->schema([
                        TextInput::make('totp_code')
                            ->label(__('admin_pages.two_factor.authentication_code'))
                            ->required()
                            ->maxLength(6)
                            ->minLength(6)
                            ->numeric()
                            ->placeholder('000000'),
                    ])
                    ->visible(fn (): bool => $this->showEnrollment),
            ])
            ->statePath('data');
    }

    // ──────────────────────────────────────────────
    // Header Actions
    // ──────────────────────────────────────────────

    protected function getHeaderActions(): array
    {
        $actions = [];

        if (! $this->twoFactorEnabled && ! $this->showEnrollment) {
            $actions[] = $this->enableAction();
        }

        if ($this->twoFactorEnabled) {
            $actions[] = $this->showRecoveryCodesAction();
            $actions[] = $this->regenerateRecoveryCodesAction();

            if (! $this->requiresTwoFactor()) {
                $actions[] = $this->disableAction();
            }
        }

        return $actions;
    }

    private function enableAction(): Action
    {
        return Action::make('enable')
            ->label($this->twoFactorUnconfirmed ? __('admin_pages.two_factor.complete_two_factor_setup') : __('admin_pages.two_factor.enable_two_factor_authentication'))
            ->icon('heroicon-o-shield-check')
            ->color('success')
            ->form($this->currentPasswordForm())
            ->action(function (array $data): void {
                $this->startEnrollment($data);
            });
    }

    public function requiresTwoFactor(): bool
    {
        /** @var User|null $user */
        $user = auth()->user();

        return $user instanceof User
            && (bool) config('auth.two_factor.require_for_privileged_roles', false)
            && in_array($user->role_slug, (array) config('auth.two_factor.privileged_roles', []), true);
    }

    private function disableAction(): Action
    {
        return Action::make('disable')
            ->label(__('admin_pages.two_factor.disable_two_factor_authentication'))
            ->icon('heroicon-o-shield-exclamation')
            ->color('danger')
            ->requiresConfirmation()
            ->modalHeading(__('admin_pages.two_factor.disable_two_factor_authentication'))
            ->modalDescription(__('admin_pages.two_factor.are_you_sure_you_want_to_disable_two_factor_authentication_t'))
            ->form($this->currentPasswordForm())
            ->action(function (array $data): void {
                $this->disableTwoFactor($data);
            });
    }

    private function showRecoveryCodesAction(): Action
    {
        return Action::make('showRecoveryCodes')
            ->label(__('admin_pages.two_factor.view_recovery_codes'))
            ->icon('heroicon-o-key')
            ->color('warning')
            ->action(function (): void {
                $this->recoveryCodes = [];
                $this->showRecoveryCodes = false;

                Notification::make()
                    ->title(__('admin_pages.two_factor.recovery_codes_cannot_be_displayed'))
                    ->body(__('admin_pages.two_factor.for_security_existing_recovery_codes_are_stored_as_hashes_re'))
                    ->warning()
                    ->send();
            });
    }

    private function regenerateRecoveryCodesAction(): Action
    {
        return Action::make('regenerateRecoveryCodes')
            ->label(__('admin_pages.two_factor.regenerate_recovery_codes'))
            ->icon('heroicon-o-arrow-path')
            ->color('gray')
            ->requiresConfirmation()
            ->modalHeading(__('admin_pages.two_factor.regenerate_recovery_codes'))
            ->modalDescription(__('admin_pages.two_factor.this_will_invalidate_your_existing_recovery_codes_and_genera'))
            ->form($this->currentPasswordForm())
            ->action(function (array $data): void {
                $this->regenerateRecoveryCodes($data);
            });
    }

    // ──────────────────────────────────────────────
    // Actions
    // ──────────────────────────────────────────────

    public function startEnrollment(array $data = []): void
    {
        /** @var User $user */
        $user = auth()->user();
        $this->assertCurrentPassword($user, $data);

        $enrollment = $this->authenticator->generateSecret($user);

        $this->qrCodeUrl = $enrollment->qrCodeUrl;
        $this->secret = $enrollment->secret;
        $this->recoveryCodes = $enrollment->recoveryCodes;
        $this->showEnrollment = true;
        $this->showRecoveryCodes = false;
    }

    public function confirmEnrollment(): void
    {
        $formData = $this->form->getState();
        $code = (string) ($formData['totp_code'] ?? '');

        /** @var User $user */
        $user = auth()->user();

        if (! $this->authenticator->verify($user, $code)) {
            Notification::make()
                ->title(__('admin_pages.two_factor.invalid_code'))
                ->body(__('admin_pages.two_factor.the_authentication_code_you_entered_is_invalid_please_try_ag'))
                ->danger()
                ->send();

            return;
        }

        $this->twoFactorEnabled = true;
        $this->twoFactorUnconfirmed = false;
        $this->showEnrollment = false;
        $this->showRecoveryCodes = true;

        session()->put('2fa_verified', true);
        session()->put('2fa_verified_user_id', (int) $user->getAuthIdentifier());

        Notification::make()
            ->title(__('admin_pages.two_factor.two_factor_authentication_enabled'))
            ->body(__('admin_pages.two_factor.your_account_is_now_protected_with_two_factor_authentication'))
            ->success()
            ->send();
    }

    public function disableTwoFactor(array $data = []): void
    {
        abort_if($this->requiresTwoFactor(), 403, __('admin_pages.two_factor.two_factor_authentication_is_required_for_this_account'));

        /** @var User $user */
        $user = auth()->user();
        $this->assertCurrentPassword($user, $data);

        $this->authenticator->disableTwoFactor($user);

        $this->twoFactorEnabled = false;
        $this->showEnrollment = false;
        $this->showRecoveryCodes = false;
        $this->qrCodeUrl = '';
        $this->secret = '';
        $this->recoveryCodes = [];

        // Clear the 2FA session flag so it doesn't persist after disabling.
        session()->forget(['2fa_verified', '2fa_verified_user_id']);

        Notification::make()
            ->title(__('admin_pages.two_factor.two_factor_authentication_disabled'))
            ->body(__('admin_pages.two_factor.two_factor_authentication_has_been_removed_from_your_account'))
            ->warning()
            ->send();
    }

    public function regenerateRecoveryCodes(array $data = []): void
    {
        /** @var User $user */
        $user = auth()->user();
        $this->assertCurrentPassword($user, $data);

        $this->recoveryCodes = $this->authenticator->generateRecoveryCodes($user);
        $this->showRecoveryCodes = true;

        Notification::make()
            ->title(__('admin_pages.two_factor.recovery_codes_regenerated'))
            ->body(__('admin_pages.two_factor.your_previous_recovery_codes_have_been_invalidated_save_thes'))
            ->success()
            ->send();
    }

    /** @return array<int, TextInput> */
    private function currentPasswordForm(): array
    {
        return [
            TextInput::make('current_password')
                ->label(__('admin_pages.two_factor.current_password'))
                ->password()
                ->required()
                ->revealable()
                ->autocomplete('current-password'),
        ];
    }

    /** @param array<string, mixed> $data */
    private function assertCurrentPassword(?User $user, array $data): void
    {
        $password = $data['current_password'] ?? null;

        if (! $user instanceof User || ! is_string($password) || ! Hash::check($password, (string) $user->password)) {
            throw ValidationException::withMessages([
                'current_password' => __('admin_pages.two_factor.current_password_is_incorrect'),
            ]);
        }
    }
}
