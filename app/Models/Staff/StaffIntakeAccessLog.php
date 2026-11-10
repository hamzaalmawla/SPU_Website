<?php

declare(strict_types=1);

namespace App\Models\Staff;

use App\Models\Person\FacultyMember;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** @property list<string>|null $changed_fields */
class StaffIntakeAccessLog extends Model
{
    public const UPDATED_AT = null;

    public const EVENTS = ['opened', 'claimed', 'started_new', 'submitted', 'superseded'];

    protected $fillable = [
        'staff_intake_link_id',
        'staff_intake_request_id',
        'faculty_member_id',
        'event',
        'submitter_name',
        'changed_fields',
        'ip_address',
        'user_agent',
    ];

    protected function casts(): array
    {
        return ['changed_fields' => 'array'];
    }

    public function link(): BelongsTo
    {
        return $this->belongsTo(StaffIntakeLink::class, 'staff_intake_link_id');
    }

    public function request(): BelongsTo
    {
        return $this->belongsTo(StaffIntakeRequest::class, 'staff_intake_request_id');
    }

    public function facultyMember(): BelongsTo
    {
        return $this->belongsTo(FacultyMember::class);
    }
}
