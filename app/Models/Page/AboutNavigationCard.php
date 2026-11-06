<?php

declare(strict_types=1);

namespace App\Models\Page;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AboutNavigationCard extends Model
{
    use HasFactory;

    protected $fillable = [
        'target_key',
        'title_override_ar',
        'title_override_en',
        'sort_order',
        'is_visible',
        'status',
        'publish_at',
        'published_at',
    ];

    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
            'is_visible' => 'boolean',
            'publish_at' => 'datetime',
            'published_at' => 'datetime',
        ];
    }
}
