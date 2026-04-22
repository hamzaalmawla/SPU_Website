<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LegacyRecordSnapshot extends Model
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
        'legacy_key',
        'classification',
        'locale',
        'payload_json',
        'payload_text',
    ];

    protected function casts(): array
    {
        return [
            'source_id' => 'integer',
            'payload_json' => 'array',
            'created_at' => 'datetime',
        ];
    }
}
