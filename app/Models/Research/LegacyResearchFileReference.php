<?php

declare(strict_types=1);

namespace App\Models\Research;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LegacyResearchFileReference extends Model
{
    protected $fillable = [
        'research_publication_id',
        'legacy_source_table',
        'legacy_source_id',
        'legacy_path',
        'label_ar',
        'label_en',
        'sort_order',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'legacy_source_id' => 'integer',
            'sort_order' => 'integer',
        ];
    }

    public function researchPublication(): BelongsTo
    {
        return $this->belongsTo(ResearchPublication::class);
    }
}
