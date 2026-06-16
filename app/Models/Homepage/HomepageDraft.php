<?php

declare(strict_types=1);

namespace App\Models\Homepage;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HomepageDraft extends Model
{
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'target_type',
        'target_id',
        'payload_json',
        'draft_notes',
        'created_by',
        'updated_by',
        'scheduled_at',
        'published_at',
        'version',
    ];

    protected function casts(): array
    {
        return [
            'target_id' => 'integer',
            'payload_json' => 'array',
            'scheduled_at' => 'datetime',
            'published_at' => 'datetime',
            'version' => 'integer',
        ];
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }
}
