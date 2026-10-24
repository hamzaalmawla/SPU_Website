<?php

declare(strict_types=1);

namespace App\Services\Auth;

use App\Contracts\Auth\AdminAccessServiceInterface;
use App\Contracts\Auth\AuthServiceInterface;
use App\Contracts\Auth\PermissionCatalogInterface;
use App\Contracts\Auth\TotpAuthenticatorInterface;
use App\Contracts\Cms\CmsTargetRegistryInterface;
use App\Contracts\Shared\AuditServiceInterface;
use App\DTOs\Auth\LoginCredentialsDTO;
use App\Models\User\Role;
use App\Models\User\User;
use Carbon\CarbonInterface;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Auth\Factory as AuthFactory;
use Illuminate\Contracts\Session\Session;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Timebox;
use Illuminate\Validation\ValidationException;

/**
 * Framework-backed authentication service for admin access checks.
 */
final class AuthService implements AuthServiceInterface
{
    private const MAX_FAILED_ATTEMPTS = 5;

    private const ADMIN_SESSION_STARTED_AT = 'admin_session_started_at';

    private const ADMIN_SESSION_LAST_ACTIVITY_AT = 'admin_session_last_activity_at';

    public function __construct(
        private readonly AuthFactory $authFactory,
        private readonly AuditServiceInterface $auditService,
        private readonly TotpAuthenticatorInterface $totpAuthenticator,
        private readonly Request $request,
        private readonly Session $session,
        private readonly Timebox $timebox,
        private readonly AdminAccessServiceInterface $access,
        private readonly CmsTargetRegistryInterface $targetRegistry,
        private readonly PermissionCatalogInterface $permissionCatalog,
    ) {}

    /**
     * Attempt user authentication.
     *
     * Every rejection costs the same: an unknown email, a locked account and
     * an account without admin access each run one password hash, as a wrong
     * password does, inside a timebox that evens out the rest. Response time
     * therefore does not tell an attacker which staff emails exist.
     */
    public function attempt(LoginCredentialsDTO $credentials): bool
    {
        $email = strtolower(trim($credentials->email));
        $password = $credentials->password;
        $remember = $credentials->remember;

        if ($email === '' || $password === '') {
            return false;
        }

        return (bool) $this->timebox->dontReturnEarly()->call(
            fn (Timebox $timebox): bool => $this->attemptWithinTimebox($timebox, $email, $password, $remember),
            $this->timeboxMicroseconds(),
        );
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
     *
     * An automatic lock stops counting once its locked_until passes; a lock
     * set by an administrator has none and lasts until it is lifted.
     */
    public function isLocked(Authenticatable $user): bool
    {
        if ($user instanceof User) {
            return $user->isAccountLocked();
        }

        if (! (bool) $this->readAttribute($user, 'is_locked') && $this->readLockedAt($user) === null) {
            return false;
        }

        $lockedUntil = $this->readAttribute($user, 'locked_until');

        return ! $lockedUntil instanceof CarbonInterface || $lockedUntil->isFuture();
    }

    public function unlockAccount(string $email): bool
    {
        $email = strtolower(trim($email));
        $user = $email !== '' ? User::query()->where('email', $email)->first() : null;

        if (! $user instanceof User) {
            return false;
        }

        $wasLocked = $user->isAccountLocked();
        $this->clearLockState($user);

        $this->auditService->log(
            action: 'user.unlocked',
            entityType: User::class,
            entityId: (int) $user->getKey(),
            metadata: [
                'target_email' => $user->email,
                'source' => 'console',
                'was_locked' => $wasLocked,
            ],
        );

        return true;
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
            // The session id is NOT rotated here. This runs on every admin request, and admin pages
            // fire overlapping requests (Livewire updates, a click while a page is still loading).
            // Rotating the id on each one deleted the old session, so any request still carrying the
            // previous cookie found no session and was redirected to the login page, logging the
            // admin out at random. The id is rotated where it matters: at login (SessionGuard) and
            // after the two-factor challenge.
            $now = now()->getTimestamp();

            if (! $this->session->has(self::ADMIN_SESSION_STARTED_AT)) {
                $this->session->put(self::ADMIN_SESSION_STARTED_AT, $now);
            }

            $this->session->put(self::ADMIN_SESSION_LAST_ACTIVITY_AT, $now);
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

    public function createUser(array $payload, int $actorUserId): ?int
    {
        $actor = User::query()->find($actorUserId);

        if (! $actor instanceof User) {
            return null;
        }

        if (Gate::forUser($actor)->denies('create', User::class)) {
            throw new AuthorizationException('This user is not authorized to create users.');
        }

        $email = strtolower(trim((string) ($payload['email'] ?? '')));
        $password = $payload['password'] ?? null;
        $roles = $this->requestedRoles($payload);

        if ($email === '' || ! is_string($password) || $password === '' || $roles->isEmpty()) {
            return null;
        }

        if (User::query()->where('email', $email)->exists()) {
            return null;
        }

        $mainRole = $this->mainRole($roles);
        $grants = $this->accessGrants($mainRole->slug, $payload['extra_permissions'] ?? [], $payload['faculty_scopes'] ?? [], $payload['page_scopes'] ?? []);

        $user = new User;
        $user->fill([
            'name' => (string) ($payload['name'] ?? ''),
            'email' => $email,
            'password' => Hash::make($password),
        ]);
        $user->forceFill([
            'faculty_scopes' => $grants['faculty_scopes'],
            'page_scopes' => $grants['page_scopes'],
            'role_slug' => $mainRole->slug,
            'role_id' => $mainRole->getKey(),
            'is_locked' => false,
            'two_factor_enabled' => false,
            'two_factor_confirmed_at' => null,
        ]);

        $saved = DB::transaction(function () use ($user, $roles, $grants): bool {
            if (! $user->save()) {
                return false;
            }

            $this->syncRolesAndPermissions($user, $roles, $grants['extra_permissions']);

            return true;
        });

        if (! $saved) {
            return null;
        }

        $this->auditService->log(
            action: 'user.created',
            userId: $actorUserId,
            entityType: User::class,
            entityId: (int) $user->getKey(),
            metadata: [
                'actor_email' => $actor->email,
                'target_email' => $user->email,
                'role_slug' => $user->role_slug,
                'roles' => $roles->pluck('slug')->all(),
                'extra_permissions' => $grants['extra_permissions'],
                'faculty_scopes' => $user->facultyScopes(),
                'page_scopes' => $user->pageScopes(),
            ],
        );

        return (int) $user->getKey();
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

        // An automatic lock that has run out is lifted first, so the form's
        // "locked" toggle, which shows the live state, is read against it.
        $this->releaseExpiredLock($user);

        $rolesChanged = array_key_exists('role_slugs', $payload) || array_key_exists('role_slug', $payload);
        $roles = $rolesChanged ? $this->requestedRoles($payload) : $this->currentRoles($user);

        if ($roles->isEmpty()) {
            throw ValidationException::withMessages(['role_slugs' => [__('admin_resources.user.choose_at_least_one_role')]]);
        }

        $mainRole = $this->mainRole($roles);
        $requestedRole = $mainRole->slug;
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

        if (array_key_exists('two_factor_enabled', $payload) && ! (bool) $payload['two_factor_enabled'] && (bool) $user->two_factor_enabled) {
            if (! $this->totpAuthenticator->disableTwoFactor($user)) {
                return false;
            }

            $user->refresh();
        }

        $user->fill([
            'name' => $payload['name'] ?? $user->name,
            'email' => $payload['email'] ?? $user->email,
        ]);

        $grants = $this->accessGrants(
            $requestedRole,
            array_key_exists('extra_permissions', $payload) ? $payload['extra_permissions'] : $user->extraPermissions()->pluck('permission')->all(),
            array_key_exists('faculty_scopes', $payload) ? $payload['faculty_scopes'] : $user->facultyScopes(),
            array_key_exists('page_scopes', $payload) ? $payload['page_scopes'] : $user->pageScopes(),
        );
        $previousGrants = $this->grantSummary($user);

        $user->forceFill([
            'faculty_scopes' => $grants['faculty_scopes'],
            'page_scopes' => $grants['page_scopes'],
            'role_slug' => $mainRole->slug,
            'role_id' => $mainRole->getKey(),
        ]);

        if (array_key_exists('password', $payload) && is_string($payload['password']) && $payload['password'] !== '') {
            $user->password = Hash::make($payload['password']);
        }

        if ($shouldLock && ! $wasLocked) {
            // A lock set by hand has no expiry: it stays until it is lifted.
            $user->forceFill(['is_locked' => true, 'locked_at' => now(), 'locked_until' => null]);
        } elseif (! $shouldLock) {
            // Lifting a lock also clears the failure count, or the next single
            // typo would lock the account again straight away.
            $user->forceFill([
                'is_locked' => false,
                'locked_at' => null,
                'locked_until' => null,
                'failed_login_attempts' => 0,
                'failed_attempts' => 0,
            ]);
        }

        $changedFields = array_keys($user->getDirty());
        $saved = DB::transaction(function () use ($user, $roles, $grants): bool {
            if (! $user->save()) {
                return false;
            }

            $this->syncRolesAndPermissions($user, $roles, $grants['extra_permissions']);

            return true;
        });

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
                'access_before' => $previousGrants,
                'access_after' => $this->grantSummary($user),
            ],
        );

        return true;
    }

    /**
     * The roles a payload asks for: `role_slugs` (the user form) or a single
     * `role_slug`.
     *
     * @param  array<string, mixed>  $payload
     * @return Collection<int, Role>
     */
    private function requestedRoles(array $payload): Collection
    {
        $slugs = array_key_exists('role_slugs', $payload)
            ? $this->stringList($payload['role_slugs'])
            : $this->stringList([$payload['role_slug'] ?? null]);
        $roles = Role::query()->whereIn('slug', $slugs)->get();

        if ($roles->count() !== count(array_unique($slugs))) {
            throw ValidationException::withMessages(['role_slugs' => [__('admin_resources.user.unknown_role')]]);
        }

        return $roles->values();
    }

    /** @return Collection<int, Role> */
    private function currentRoles(User $user): Collection
    {
        return Role::query()
            ->where('slug', (string) $user->role_slug)
            ->orWhereIn('id', $user->roles()->pluck('roles.id'))
            ->get();
    }

    /**
     * The main role (users.role_slug): super admin whenever it is one of the
     * roles, so every super-admin check stays a single column read.
     *
     * @param  Collection<int, Role>  $roles
     */
    private function mainRole(Collection $roles): Role
    {
        /** @var Role */
        return $roles->firstWhere('slug', 'super_admin') ?? $roles->sortBy('id')->first();
    }

    /**
     * Extra permissions and the optional faculty and page limits, checked
     * against what exists. A super admin already has everything and no limits.
     *
     * @return array{extra_permissions: list<string>, faculty_scopes: list<string>|null, page_scopes: list<string>|null}
     */
    private function accessGrants(string $mainRole, mixed $permissions, mixed $faculties, mixed $pages): array
    {
        if ($mainRole === 'super_admin') {
            return ['extra_permissions' => [], 'faculty_scopes' => null, 'page_scopes' => null];
        }

        $permissions = array_values(array_unique($this->stringList($permissions)));
        $faculties = array_values(array_unique(array_map(
            fn (string $faculty): string => $this->access->canonicalFacultySlug($faculty),
            $this->stringList($faculties),
        )));
        $pages = array_values(array_unique($this->stringList($pages)));

        if (array_diff($permissions, $this->permissionCatalog->all()) !== []) {
            throw ValidationException::withMessages(['extra_permissions' => [__('admin_permissions.role.unknown_permission')]]);
        }

        if (array_diff($faculties, array_keys($this->access->facultyOptions())) !== []
            || array_diff($pages, $this->targetRegistry->grantablePages()->pluck('key')->all()) !== []) {
            throw ValidationException::withMessages(['faculty_scopes' => [__('admin_resources.user.unknown_faculty_or_page')]]);
        }

        return [
            'extra_permissions' => $permissions,
            'faculty_scopes' => $faculties === [] ? null : $faculties,
            'page_scopes' => $pages === [] ? null : $pages,
        ];
    }

    /**
     * @param  Collection<int, Role>  $roles
     * @param  list<string>  $permissions
     */
    private function syncRolesAndPermissions(User $user, Collection $roles, array $permissions): void
    {
        $user->roles()->sync($roles->pluck('id')->all());
        $user->extraPermissions()->whereNotIn('permission', $permissions)->delete();

        $existing = $user->extraPermissions()->pluck('permission')->all();

        foreach (array_diff($permissions, $existing) as $permission) {
            $user->extraPermissions()->create(['permission' => $permission]);
        }

        $this->access->flushPermissions();
    }

    /** @return array<string, mixed> */
    private function grantSummary(User $user): array
    {
        return [
            'roles' => $this->currentRoles($user)->pluck('slug')->sort()->values()->all(),
            'extra_permissions' => $user->extraPermissions()->pluck('permission')->sort()->values()->all(),
            'faculty_scopes' => $user->facultyScopes(),
            'page_scopes' => $user->pageScopes(),
        ];
    }

    /** @return list<string> */
    private function stringList(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        return array_values(array_filter(
            array_map(static fn (mixed $item): string => is_string($item) ? trim($item) : '', $value),
            static fn (string $item): bool => $item !== '',
        ));
    }

    private function guardName(): string
    {
        return (string) config('auth.admin_guard', 'web');
    }

    private function canAccessAdmin(User $user): bool
    {
        return $this->access->hasAdminAccess($user);
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
            && ! (bool) $user->is_locked
            && $user->locked_at === null
        ) {
            return;
        }

        $user->forceFill([
            'failed_login_attempts' => 0,
            'failed_attempts' => 0,
            'is_locked' => false,
            'locked_at' => null,
            'locked_until' => null,
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

        $wasUnlocked = ! $user->isAccountLocked();
        $failedAttempts = min($this->currentFailedAttempts($user) + 1, self::MAX_FAILED_ATTEMPTS);
        $locksNow = $wasUnlocked && $failedAttempts >= self::MAX_FAILED_ATTEMPTS;

        $user->forceFill([
            'failed_login_attempts' => $failedAttempts,
            'failed_attempts' => $failedAttempts,
        ]);

        if ($locksNow) {
            $decayMinutes = $this->lockoutDecayMinutes();

            $user->forceFill([
                'is_locked' => true,
                'locked_at' => now(),
                'locked_until' => $decayMinutes > 0 ? now()->addMinutes($decayMinutes) : null,
            ]);
        }

        $user->save();

        $this->logLoginFailure($user, $email);

        if ($locksNow) {
            $this->auditService->log(
                action: 'user.locked',
                userId: (int) $user->getKey(),
                entityType: User::class,
                entityId: (int) $user->getKey(),
                metadata: $this->authMetadata($user, $email),
            );
        }
    }

    private function attemptWithinTimebox(Timebox $timebox, string $email, string $password, bool $remember): bool
    {
        $user = User::query()->where('email', $email)->first();

        if ($user instanceof User) {
            $this->releaseExpiredLock($user);
        }

        if (! $user instanceof User || $this->isLocked($user) || ! $this->canAccessAdmin($user)) {
            // Do the hashing work a wrong password costs, and discard it.
            Hash::make($password);

            if ($user instanceof User) {
                $this->logLoginFailure($user, $email);
            } else {
                $this->handleFailedAttempt(null, $email);
            }

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

        $timebox->returnEarly();

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
     * Lift an automatic lock whose time is up, with a fresh failure count.
     */
    private function releaseExpiredLock(User $user): void
    {
        if ($user->locked_until === null
            || $user->locked_until->isFuture()
            || (! (bool) $user->is_locked && $user->locked_at === null)
        ) {
            return;
        }

        $this->clearLockState($user);

        $this->auditService->log(
            action: 'user.unlocked',
            entityType: User::class,
            entityId: (int) $user->getKey(),
            metadata: [
                'target_email' => $user->email,
                'source' => 'lock_expired',
            ],
        );
    }

    private function clearLockState(User $user): void
    {
        $user->forceFill([
            'is_locked' => false,
            'locked_at' => null,
            'locked_until' => null,
            'failed_login_attempts' => 0,
            'failed_attempts' => 0,
        ])->save();
    }

    private function lockoutDecayMinutes(): int
    {
        return max(0, (int) config('auth.lockout.decay_minutes', 30));
    }

    private function timeboxMicroseconds(): int
    {
        return max(0, (int) config('auth.timebox_duration', 200000));
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
