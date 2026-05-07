<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Contracts\TotpAuthenticatorInterface;
use App\Models\User;
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

    protected static ?string $navigationLabel = 'Two-Factor Auth';

    protected static ?string $title = 'Two-Factor Authentication';

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
            && in_array($user->role_slug, ['super_admin', 'editor', 'faculty_editor'], true);
    }

    public function mount(): void
    {
        /** @var User $user */
        $user = auth()->user();

        abort_unless($user instanceof User && static::canAccess(), 403);

        $this->twoFactorEnabled = (bool) $user->two_factor_enabled;
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make('Confirm TOTP Code')
                    ->description('Enter the 6-digit code from your authenticator app to complete setup.')
                    ->schema([
                        TextInput::make('totp_code')
                            ->label('Authentication Code')
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
            $actions[] = $this->disableAction();
        }

        return $actions;
    }

    private function enableAction(): Action
    {
        return Action::make('enable')
            ->label('Enable Two-Factor Authentication')
            ->icon('heroicon-o-shield-check')
            ->color('success')
            ->form($this->currentPasswordForm())
            ->action(function (array $data): void {
                $this->startEnrollment($data);
            });
    }

    private function disableAction(): Action
    {
        return Action::make('disable')
            ->label('Disable Two-Factor Authentication')
            ->icon('heroicon-o-shield-exclamation')
            ->color('danger')
            ->requiresConfirmation()
            ->modalHeading('Disable Two-Factor Authentication')
            ->modalDescription('Are you sure you want to disable two-factor authentication? This will reduce the security of your account.')
            ->form($this->currentPasswordForm())
            ->action(function (array $data): void {
                $this->disableTwoFactor($data);
            });
    }

    private function showRecoveryCodesAction(): Action
    {
        return Action::make('showRecoveryCodes')
            ->label('View Recovery Codes')
            ->icon('heroicon-o-key')
            ->color('warning')
            ->action(function (): void {
                $this->recoveryCodes = [];
                $this->showRecoveryCodes = false;

                Notification::make()
                    ->title('Recovery codes cannot be displayed')
                    ->body('For security, existing recovery codes are stored as hashes. Regenerate them to view a new one-time set.')
                    ->warning()
                    ->send();
            });
    }

    private function regenerateRecoveryCodesAction(): Action
    {
        return Action::make('regenerateRecoveryCodes')
            ->label('Regenerate Recovery Codes')
            ->icon('heroicon-o-arrow-path')
            ->color('gray')
            ->requiresConfirmation()
            ->modalHeading('Regenerate Recovery Codes')
            ->modalDescription('This will invalidate your existing recovery codes and generate new ones. Make sure to save the new codes.')
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
        $this->showRecoveryCodes = true;
    }

    public function confirmEnrollment(): void
    {
        $formData = $this->form->getState();
        $code = (string) ($formData['totp_code'] ?? '');

        /** @var User $user */
        $user = auth()->user();

        if (! $this->authenticator->verify($user, $code)) {
            Notification::make()
                ->title('Invalid code')
                ->body('The authentication code you entered is invalid. Please try again.')
                ->danger()
                ->send();

            return;
        }

        $this->twoFactorEnabled = true;
        $this->showEnrollment = false;

        Notification::make()
            ->title('Two-factor authentication enabled')
            ->body('Your account is now protected with two-factor authentication.')
            ->success()
            ->send();
    }

    public function disableTwoFactor(array $data = []): void
    {
        /** @var User $user */
        $user = auth()->user();
        $this->assertCurrentPassword($user, $data);

        $user->forceFill([
            'two_factor_enabled' => false,
            'two_factor_confirmed_at' => null,
            'totp_secret_encrypted' => null,
            'recovery_codes_encrypted' => null,
        ])->save();

        $this->twoFactorEnabled = false;
        $this->showEnrollment = false;
        $this->showRecoveryCodes = false;
        $this->qrCodeUrl = '';
        $this->secret = '';
        $this->recoveryCodes = [];

        // Clear the 2FA session flag so it doesn't persist after disabling.
        session()->forget(['2fa_verified', '2fa_verified_user_id']);

        Notification::make()
            ->title('Two-factor authentication disabled')
            ->body('Two-factor authentication has been removed from your account.')
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
            ->title('Recovery codes regenerated')
            ->body('Your previous recovery codes have been invalidated. Save these new codes securely.')
            ->success()
            ->send();
    }

    /** @return array<int, TextInput> */
    private function currentPasswordForm(): array
    {
        return [
            TextInput::make('current_password')
                ->label('Current password')
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
                'current_password' => __('The current password is incorrect.'),
            ]);
        }
    }
}
