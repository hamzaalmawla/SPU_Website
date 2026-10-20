<?php

declare(strict_types=1);

namespace App\Models\Staff;

use App\Models\Faculty\Faculty;
use App\Models\Person\FacultyMember;
use App\Models\User\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property array<string, mixed> $payload
 * @property array<string, mixed>|null $original_snapshot
 * @property list<int>|null $duplicate_candidates
 */
class StaffIntakeRequest extends Model
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_REJECTED = 'rejected';

    public const STATUS_SUPERSEDED = 'superseded';

    public const TYPE_UPDATE = 'update';

    public const TYPE_CREATE = 'create';

    protected $fillable = [
        'reference',
        'staff_intake_link_id',
        'faculty_id',
        'type',
        'faculty_member_id',
        'related_faculty_member_id',
        'submitter_name',
        'payload',
        'original_snapshot',
        'duplicate_candidates',
        'possible_duplicate',
        'status',
        'reviewed_by',
        'reviewed_at',
        'review_note',
        'applied_faculty_member_id',
    ];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'original_snapshot' => 'array',
            'duplicate_candidates' => 'array',
            'possible_duplicate' => 'boolean',
            'reviewed_at' => 'datetime',
        ];
    }

    public function link(): BelongsTo
    {
        return $this->belongsTo(StaffIntakeLink::class, 'staff_intake_link_id');
    }

    public function faculty(): BelongsTo
    {
        return $this->belongsTo(Faculty::class);
    }

    public function facultyMember(): BelongsTo
    {
        return $this->belongsTo(FacultyMember::class);
    }

    public function relatedFacultyMember(): BelongsTo
    {
        return $this->belongsTo(FacultyMember::class, 'related_faculty_member_id');
    }

    public function appliedFacultyMember(): BelongsTo
    {
        return $this->belongsTo(FacultyMember::class, 'applied_faculty_member_id');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function accessLogs(): HasMany
    {
        return $this->hasMany(StaffIntakeAccessLog::class);
    }
}
