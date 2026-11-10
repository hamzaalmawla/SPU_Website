<?php

declare(strict_types=1);

namespace App\Models\Legacy;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LegacyContentMapping extends Model
{
    use HasFactory;

    protected $fillable = [
        'module',
        'source_table',
        'source_id',
        'legacy_key',
        'classification',
        'mapping_status',
        'target_module',
        'target_type',
        'target_identifier',
        'target_table',
        'target_id',
        'confidence',
        'file_dependency',
        'phase3_reasons',
        'source_identity',
        'source_url',
        'source_date',
        'rule_key',
        'notes',
        'metadata',
        'approved_at',
        'approved_by',
    ];

    protected function casts(): array
    {
        return [
            'source_id' => 'integer',
            'target_id' => 'integer',
            'phase3_reasons' => 'array',
            'metadata' => 'array',
            'approved_at' => 'datetime',
            'approved_by' => 'integer',
        ];
    }
}
