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
        'current_path',
        'media_asset_id',
        'status',
        'mime_type',
        'file_size_bytes',
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
}
