<?php

declare(strict_types=1);

namespace App\Models\Cms;

use App\Models\User\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CmsTargetContent extends Model
{
    /** @var list<string> */
    protected $fillable = [
        'target_key',
        'payload_json',
        'status',
        'updated_by',
        'published_at',
    ];

    protected function casts(): array
    {
        return [
            'payload_json' => 'array',
            'published_at' => 'datetime',
        ];
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
