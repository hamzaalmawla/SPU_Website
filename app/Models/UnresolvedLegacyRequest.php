<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Append-only log of legacy URL requests that could not be resolved to a redirect or file mapping.
 */
class UnresolvedLegacyRequest extends Model
{
    use HasFactory;

    /**
     * Append-only table — no updated_at column.
     */
    public const UPDATED_AT = null;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'url',
        'url_hash',
        'query_string',
        'method',
        'referrer',
        'resolved_locale',
        'request_type',
        'user_agent',
        'ip_hash',
        'hit_count',
        'first_seen_at',
        'last_seen_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'hit_count' => 'integer',
            'first_seen_at' => 'datetime',
            'last_seen_at' => 'datetime',
            'created_at' => 'datetime',
        ];
    }
}
