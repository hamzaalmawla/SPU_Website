<?php

declare(strict_types=1);

namespace App\Models\Contact;

use App\Models\User\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ContactMessage extends Model
{
    use HasFactory;

    protected $fillable = [
        'reference_number',
        'locale',
        'name',
        'email',
        'subject',
        'message',
        'status',
        'ip_address',
        'user_agent',
        'read_at',
        'read_by_user_id',
        'assigned_to_user_id',
        'assigned_at',
        'assigned_by_user_id',
        'internal_notes',
        'status_changed_at',
        'email_delivery_status',
        'email_queued_at',
        'email_sent_at',
        'email_delivered_at',
        'email_failed_at',
        'email_failure_reason',
    ];

    protected $casts = [
        'read_at' => 'datetime',
        'assigned_at' => 'datetime',
        'status_changed_at' => 'datetime',
        'email_queued_at' => 'datetime',
        'email_sent_at' => 'datetime',
        'email_delivered_at' => 'datetime',
        'email_failed_at' => 'datetime',
    ];

    public function readBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'read_by_user_id');
    }

    public function assignedTo(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to_user_id');
    }

    public function assignedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_by_user_id');
    }
}
