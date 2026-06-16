<?php

declare(strict_types=1);

namespace App\Models\Page;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PageDraft extends Model
{
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'page_id',
        'payload_json',
        'status',
        'draft_notes',
        'created_by',
        'updated_by',
        'approved_by',
        'scheduled_at',
        'published_at',
        'version',
    ];

    protected function casts(): array
    {
        return [
            'payload_json' => 'array',
            'scheduled_at' => 'datetime',
            'published_at' => 'datetime',
            'version' => 'integer',
        ];
    }

    public function page(): BelongsTo
    {
        return $this->belongsTo(Page::class);
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
