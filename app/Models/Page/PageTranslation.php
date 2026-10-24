<?php

declare(strict_types=1);

namespace App\Models\Page;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PageTranslation extends Model
{
    use HasFactory;

    /**
     * Localized page content record.
     *
     * Payload/body fields here are the authoritative source for locale-specific runtime reads.
     */

    /**
     * @var list<string>
     */
    protected $fillable = [
        'page_id',
        'locale',
        'title',
        'navigation_label',
        'headline',
        'subheadline',
        'hero_payload',
        'overview_cards_payload',
        'stats_payload',
        'body_payload',
        'cta_payload',
        'sidebar_payload',
        'excerpt',
        'body',
        'raw_excerpt',
        'meta_title_fallback',
    ];

    protected function casts(): array
    {
        return [
            'hero_payload' => 'array',
            'overview_cards_payload' => 'array',
            'stats_payload' => 'array',
            'body_payload' => 'array',
            'cta_payload' => 'array',
            'sidebar_payload' => 'array',
        ];
    }

    public function page(): BelongsTo
    {
        return $this->belongsTo(Page::class);
    }
}
