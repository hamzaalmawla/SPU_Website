<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MigrationLog extends Model
{
    use HasFactory;

    public const UPDATED_AT = null;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'module',
        'batch_name',
        'source_table',
        'source_id',
        'target_table',
        'target_id',
        'status',
        'message',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'source_id' => 'integer',
            'target_id' => 'integer',
            'metadata' => 'array',
            'created_at' => 'datetime',
        ];
    }
}
