<?php

declare(strict_types=1);

namespace App\Services;

use App\Contracts\AuditServiceInterface;
use App\Contracts\AuthServiceInterface;
use App\DTOs\LoginCredentialsDTO;
use App\Models\Role;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Auth\Factory as AuthFactory;
use Illuminate\Contracts\Session\Session;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Gate;

/**
 * Framework-backed authentication service for admin access checks.
 */
final class AuthService implements AuthServiceInterface
{
    private const MAX_FAILED_ATTEMPTS = 5;

    public function __construct(
        private readonly AuthFactory $authFactory,
        private readonly AuditServiceInterface $auditService,
        private readonly Request $request,
        private readonly Session $session,
    ) {}

    /**
     * Attempt user authentication.
     */
    public function attempt(LoginCredentialsDTO $credentials): bool
    {
        $email = strtolower(trim($credentials->email));
        $password = $credentials->password;
        $remember = $credentials->remember;

        if ($email === '' || $password === '') {
            return false;
        }

        $user = User::query()->where('email', $email)->first();

        if ($user instanceof User && $this->isLocked($user)) {
            $this->logLoginFailure($user, $email);

            return false;
        }

        if ($user instanceof User && ! $this->canAccessAdmin($user)) {
            $this->logLoginFailure($user, $email);

            return false;
        }

        $authenticated = $this->authFactory->guard($this->guardName())->attempt([
            'email' => $email,
            'password' => $password,
        ], $remember);

        if (! $authenticated) {
            $this->handleFailedAttempt($user, $email);

            return false;
        }

        $authenticatedUser = $this->authFactory->guard($this->guardName())->user();

        if (! $authenticatedUser instanceof User) {
            return false;
        }

        $this->clearFailedAttempts($authenticatedUser);
        $this->clearTwoFactorVerification();
        $this->extendSession();
        $this->auditService->log(
            action: 'user.login',
            userId: (int) $authenticatedUser->getKey(),
            entityType: User::class,
            entityId: (int) $authenticatedUser->getKey(),
            metadata: $this->authMetadata($authenticatedUser, $email),
        );

        return true;
    }

    /**
     * Check if the user has a specific role.
     */
    public function checkRole(Authenticatable $user, string $role): bool
    {
        if ($role === '') {
            return false;
        }

        return $this->readRoleSlug($user) === $role;
    }

    /**
     * Check whether the account is currently locked.
     */
    public function isLocked(Authenticatable $user): bool
    {
        return (bool) $this->readAttribute($user, 'is_locked') || $this->readLockedAt($user) !== null;
    }

    /**
     * End the current authenticated session.
     */
    public function logout(): void
    {
        $user = $this->authFactory->guard($this->guardName())->user();

        if ($user instanceof User) {
            $this->auditService->log(
                action: 'user.logout',
                userId: (int) $user->getKey(),
                entityType: User::class,
                entityId: (int) $user->getKey(),
                metadata: $this->authMetadata($user, (string) $user->email),
            );
        }

        $this->authFactory->guard($this->guardName())->logout();

        if ($this->session->isStarted()) {
            $this->clearTwoFactorVerification();
            $this->session->invalidate();
            $this->session->regenerateToken();
        }
    }

    /**
     * Extend active session lifetime.
     */
    public function extendSession(): void
    {
        if ($this->session->isStarted()) {
            $this->session->migrate(true);
        }
    }

    public function recordFailedTwoFactor(Authenticatable $user): void
    {
        if (! $user instanceof User) {
            return;
        }

        $this->handleFailedAttempt($user, (string) $user->email);
    }

    public function recordSuccessfulTwoFactor(Authenticatable $user): void
    {
        if ($user instanceof User) {
            $this->clearFailedAttempts($user);
        }
    }

    public function updateUser(int $userId, array $payload, int $actorUserId): bool
    {
        $user = User::query()->find($userId);
        $actor = User::query()->find($actorUserId);

        if (! $user instanceof User || ! $actor instanceof User) {
            return false;
        }

        if (Gate::forUser($actor)->denies('update', $user)) {
            throw new AuthorizationException('This user is not authorized to update users.');
        }

        $requestedRole = array_key_exists('role_slug', $payload) ? (string) $payload['role_slug'] : (string) $user->role_slug;
        $shouldLock = array_key_exists('is_locked', $payload) ? (bool) $payload['is_locked'] : (bool) $user->is_locked;

        if ($userId === $actorUserId && ($shouldLock || $requestedRole !== 'super_admin')) {
            throw new AuthorizationException('Super administrators cannot lock or demote their own account.');
        }

        if ($user->role_slug === 'super_admin'
            && ($shouldLock || $requestedRole !== 'super_admin')
            && $this->activeSuperAdminCount() <= 1
        ) {
            throw new AuthorizationException('At least one active super administrator must remain.');
        }

        $wasLocked = $user->isAccountLocked();

        $user->fill([
            'name' => $payload['name'] ?? $user->name,
            'email' => $payload['email'] ?? $user->email,
            'faculty_scope_slug' => array_key_exists('faculty_scope_slug', $payload)
                ? $payload['faculty_scope_slug']
                : $user->faculty_scope_slug,
        ]);

        if (array_key_exists('role_slug', $payload)) {
            $role = Role::query()->where('slug', (string) $payload['role_slug'])->first();

            $user->forceFill([
                'role_slug' => $payload['role_slug'],
                'role_id' => $role?->getKey(),
            ]);
        }

        if (array_key_exists('password', $payload) && is_string($payload['password']) && $payload['password'] !== '') {
            $user->password = Hash::make($payload['password']);
        }

        $user->is_locked = $shouldLock;
        $user->locked_at = $shouldLock ? ($user->locked_at ?? now()) : null;

        $changedFields = array_keys($user->getDirty());
        $saved = $user->save();

        if (! $saved) {
            return false;
        }

        if ($wasLocked !== $user->isAccountLocked()) {
            $this->auditService->log(
                action: $user->isAccountLocked() ? 'user.locked' : 'user.unlocked',
                userId: $actorUserId,
                entityType: User::class,
                entityId: $userId,
                metadata: [
                    'actor_email' => $actor->email,
                    'target_email' => $user->email,
                ],
            );
        }

        $this->auditService->log(
            action: 'user.updated',
            userId: $actorUserId,
            entityType: User::class,
            entityId: $userId,
            metadata: [
                'actor_email' => $actor->email,
                'target_email' => $user->email,
                'changed_fields' => $changedFields,
            ],
        );

        return true;
    }

    private function guardName(): string
    {
        return (string) config('auth.admin_guard', 'web');
    }

    private function canAccessAdmin(User $user): bool
    {
        return in_array($user->role_slug, ['super_admin', 'editor', 'faculty_editor'], true);
    }

    private function activeSuperAdminCount(): int
    {
        return User::query()
            ->where('role_slug', 'super_admin')
            ->where(function ($query): void {
                $query->where('is_locked', false)->orWhereNull('is_locked');
            })
            ->whereNull('locked_at')
            ->count();
    }

    private function clearTwoFactorVerification(): void
    {
        if (! $this->session->isStarted()) {
            return;
        }

        $this->session->forget(['2fa_verified', '2fa_verified_user_id']);
    }

    private function clearFailedAttempts(User $user): void
    {
        if ($this->currentFailedAttempts($user) === 0
            && ! $user->isAccountLocked()
        ) {
            return;
        }

        $user->forceFill([
            'failed_login_attempts' => 0,
            'failed_attempts' => 0,
            'is_locked' => false,
            'locked_at' => null,
            'last_login_at' => now(),
        ])->save();
    }

    private function handleFailedAttempt(?User $user, string $email): void
    {
        if (! $user instanceof User) {
            $this->auditService->log(
                action: 'user.login_failed',
                entityType: User::class,
                metadata: $this->failureMetadata($email),
            );

            return;
        }

        $wasUnlocked = $user->locked_at === null;
        $failedAttempts = min($this->currentFailedAttempts($user) + 1, self::MAX_FAILED_ATTEMPTS);
        $isLocked = $failedAttempts >= self::MAX_FAILED_ATTEMPTS;

        $user->forceFill([
            'failed_login_attempts' => $failedAttempts,
            'failed_attempts' => $failedAttempts,
            'is_locked' => $isLocked,
            'locked_at' => $isLocked ? now() : $user->locked_at,
        ])->save();

        $this->logLoginFailure($user, $email);

        if ($wasUnlocked && $user->locked_at !== null) {
            $this->auditService->log(
                action: 'user.locked',
                userId: (int) $user->getKey(),
                entityType: User::class,
                entityId: (int) $user->getKey(),
                metadata: $this->authMetadata($user, $email),
            );
        }
    }

    private function logLoginFailure(User $user, string $email): void
    {
        $this->auditService->log(
            action: 'user.login_failed',
            userId: (int) $user->getKey(),
            entityType: User::class,
            entityId: (int) $user->getKey(),
            metadata: $this->failureMetadata($email),
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function authMetadata(?User $user, string $email): array
    {
        $metadata = [
            'email' => $email,
            'ip_address' => $this->request->ip(),
        ];

        if ($user instanceof User && is_string($user->role_slug) && $user->role_slug !== '') {
            $metadata['role_slug'] = $user->role_slug;
        }

        return $metadata;
    }

    /**
     * @return array<string, mixed>
     */
    private function failureMetadata(string $email): array
    {
        return [
            'email' => $email,
            'ip_address' => $this->request->ip(),
        ];
    }

    private function readRoleSlug(Authenticatable $user): ?string
    {
        $roleSlug = $this->readAttribute($user, 'role_slug');

        if (is_string($roleSlug) && $roleSlug !== '') {
            return $roleSlug;
        }

        return null;
    }

    private function readLockedAt(Authenticatable $user): mixed
    {
        return $this->readAttribute($user, 'locked_at');
    }

    private function readAttribute(Authenticatable $user, string $attribute): mixed
    {
        if ($user instanceof User) {
            return $user->getAttribute($attribute);
        }

        return isset($user->{$attribute}) ? $user->{$attribute} : null;
    }

    private function currentFailedAttempts(User $user): int
    {
        return max((int) $user->failed_login_attempts, (int) $user->failed_attempts);
    }
}
