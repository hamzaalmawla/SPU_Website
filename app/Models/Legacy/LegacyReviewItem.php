<?php

declare(strict_types=1);

namespace App\Models\Legacy;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LegacyReviewItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'module',
        'source_table',
        'source_id',
        'legacy_key',
        'classification',
        'mapping_status',
        'review_status',
        'target_module',
        'target_type',
        'confidence',
        'file_dependency',
        'phase3_reasons',
        'cleaning_status',
        'decision_plan_action',
        'url_status',
        'blocked_reasons',
        'source_identity',
        'source_url',
        'source_date',
        'rule_key',
        'notes',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'source_id' => 'integer',
            'phase3_reasons' => 'array',
            'blocked_reasons' => 'array',
            'metadata' => 'array',
        ];
    }
}
