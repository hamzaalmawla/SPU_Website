<?php

declare(strict_types=1);

namespace App\Models\Legacy;

use App\Models\Media\MediaAsset;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Tracks legacy file/document paths and their mapping to current media assets or delivery paths.
 */
class LegacyFileInventory extends Model
{
    use HasFactory;

    protected $table = 'legacy_file_inventory';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'legacy_path',
        'source_table',
        'source_column',
        'source_id',
        'current_path',
        'media_asset_id',
        'status',
        'mime_type',
        'file_size_bytes',
        'extension',
        'checksum_sha256',
        'checksum_status',
        'reference_count',
        'source_references',
        'last_seen_at',
        'notes',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'media_asset_id' => 'integer',
            'file_size_bytes' => 'integer',
            'source_id' => 'integer',
            'reference_count' => 'integer',
            'source_references' => 'array',
            'last_seen_at' => 'datetime',
        ];
    }

    public function mediaAsset(): BelongsTo
    {
        return $this->belongsTo(MediaAsset::class, 'media_asset_id');
    }

    public function scopeMapped(Builder $query): Builder
    {
        return $query->where('status', 'mapped');
    }

    public function scopeUnmapped(Builder $query): Builder
    {
        return $query->where('status', 'unmapped');
    }

    public function scopeByLegacyPath(Builder $query, string $path): Builder
    {
        return $query->whereRaw('LOWER(legacy_path) = ?', [mb_strtolower($path)]);
    }
}
