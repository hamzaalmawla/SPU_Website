<?php

declare(strict_types=1);

namespace App\Models\User;

use App\Models\Homepage\HomepageDraft;
use App\Models\Page\Page;
use App\Models\Page\PageDraft;
use App\Models\Shared\AuditLog;
use App\Models\Shared\PreviewToken;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable, SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * Security-sensitive fields (is_locked, locked_at, failed_login_attempts, etc.)
     * are intentionally excluded. Use forceFill() in AuthService for those fields.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'role_id',
        'faculty_scope_slug',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
        'totp_secret_encrypted',
        'recovery_codes_encrypted',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'failed_login_attempts' => 'integer',
            'failed_attempts' => 'integer',
            'is_locked' => 'boolean',
            'locked_at' => 'datetime',
            'last_login_at' => 'datetime',
            'deleted_at' => 'datetime',
            'password' => 'hashed',
            'totp_secret_encrypted' => 'encrypted',
            'recovery_codes_encrypted' => 'encrypted:array',
            'two_factor_enabled' => 'boolean',
            'two_factor_confirmed_at' => 'datetime',
        ];
    }

    protected function roleSlug(): Attribute
    {
        return Attribute::make(
            get: fn (?string $value): ?string => is_string($value) && $value !== ''
                ? $value
                : $this->role?->slug,
        );
    }

    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }

    public function auditLogs(): HasMany
    {
        return $this->hasMany(AuditLog::class, 'user_id');
    }

    public function legacyAuditLogs(): HasMany
    {
        return $this->hasMany(AuditLog::class, 'actor_user_id');
    }

    public function issuedPreviewTokens(): HasMany
    {
        return $this->hasMany(PreviewToken::class, 'issued_to_user_id');
    }

    public function createdPages(): HasMany
    {
        return $this->hasMany(Page::class, 'created_by');
    }

    public function updatedPages(): HasMany
    {
        return $this->hasMany(Page::class, 'updated_by');
    }

    public function approvedPages(): HasMany
    {
        return $this->hasMany(Page::class, 'approved_by');
    }

    public function createdHomepageDrafts(): HasMany
    {
        return $this->hasMany(HomepageDraft::class, 'created_by');
    }

    public function createdPageDrafts(): HasMany
    {
        return $this->hasMany(PageDraft::class, 'created_by');
    }

    public function isAccountLocked(): bool
    {
        return (bool) $this->is_locked || $this->locked_at !== null;
    }
}
