<?php

declare(strict_types=1);

namespace App\Models\Staff;

use App\Models\Faculty\Faculty;
use App\Models\User\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class StaffIntakeLink extends Model
{
    protected $fillable = [
        'faculty_id',
        'token_hash',
        'token_encrypted',
        'label',
        'expires_at',
        'revoked_at',
        'max_submissions',
        'submissions_count',
        'created_by',
    ];

    protected $hidden = ['token_hash', 'token_encrypted'];

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'revoked_at' => 'datetime',
            'max_submissions' => 'integer',
            'submissions_count' => 'integer',
        ];
    }

    public function faculty(): BelongsTo
    {
        return $this->belongsTo(Faculty::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function requests(): HasMany
    {
        return $this->hasMany(StaffIntakeRequest::class);
    }

    public function accessLogs(): HasMany
    {
        return $this->hasMany(StaffIntakeAccessLog::class);
    }

    public function isActive(): bool
    {
        return $this->revoked_at === null
            && ($this->expires_at === null || $this->expires_at->isFuture())
            && ($this->max_submissions === null || $this->submissions_count < $this->max_submissions);
    }
}
