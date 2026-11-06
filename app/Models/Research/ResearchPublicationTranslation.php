<?php

declare(strict_types=1);

namespace App\Models\Research;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ResearchPublicationTranslation extends Model
{
    use HasFactory;

    protected $fillable = ['research_publication_id', 'locale', 'title', 'authors', 'excerpt', 'abstract', 'publisher', 'citation', 'keywords'];

    protected function casts(): array
    {
        return ['keywords' => 'array'];
    }

    public function researchPublication(): BelongsTo
    {
        return $this->belongsTo(ResearchPublication::class);
    }
}
