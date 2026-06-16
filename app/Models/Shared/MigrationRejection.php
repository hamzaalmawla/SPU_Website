<?php

declare(strict_types=1);

namespace App\Models\Shared;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MigrationRejection extends Model
{
    use HasFactory;

    public const UPDATED_AT = null;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'module',
        'source_table',
        'source_id',
        'reason_code',
        'reason_message',
        'raw_summary',
    ];

    protected function casts(): array
    {
        return [
            'source_id' => 'integer',
            'raw_summary' => 'array',
            'created_at' => 'datetime',
        ];
    }
}
