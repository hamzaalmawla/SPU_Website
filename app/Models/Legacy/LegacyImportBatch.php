<?php

declare(strict_types=1);

namespace App\Models\Legacy;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LegacyImportBatch extends Model
{
    use HasFactory;

    protected $fillable = [
        'batch_name',
        'module',
        'mode',
        'status',
        'estimated_source_rows',
        'summary_json',
        'started_at',
        'finished_at',
    ];

    protected function casts(): array
    {
        return [
            'estimated_source_rows' => 'integer',
            'summary_json' => 'array',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
        ];
    }
}
