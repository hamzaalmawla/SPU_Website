<?php

declare(strict_types=1);

namespace App\Models\Page;

use App\Models\Media\MediaAsset;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PageSeoMeta extends Model
{
    use HasFactory;

    protected $table = 'page_seo_meta';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'page_id',
        'locale',
        'meta_title',
        'meta_description',
        'og_title',
        'og_description',
        'og_image_media_id',
        'og_image_url',
        'canonical_url',
        'robots',
        'hreflang_payload',
    ];

    protected function casts(): array
    {
        return [
            'og_image_media_id' => 'integer',
            'hreflang_payload' => 'array',
        ];
    }

    public function page(): BelongsTo
    {
        return $this->belongsTo(Page::class);
    }

    public function ogImage(): BelongsTo
    {
        return $this->belongsTo(MediaAsset::class, 'og_image_media_id');
    }
}
