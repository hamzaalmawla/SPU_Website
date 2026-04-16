<?php

declare(strict_types=1);

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'role_slug',
        'failed_login_attempts',
        'locked_at',
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
            'locked_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    /**
     * Determine whether the user has the given role slug.
     */
    public function hasRole(string $role): bool
    {
        return $role !== '' && $this->role_slug === $role;
    }

    /**
     * Determine whether the user has any of the given role slugs.
     *
     * @param  array<int, string>  $roles
     */
    public function hasAnyRole(array $roles): bool
    {
        foreach ($roles as $role) {
            if ($this->hasRole($role)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Determine whether the account is locked.
     */
    public function isLocked(): bool
    {
        return $this->locked_at !== null;
    }
}
