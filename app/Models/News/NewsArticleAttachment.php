<?php

declare(strict_types=1);

namespace App\Models\News;

use App\Models\Media\MediaAsset;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NewsArticleAttachment extends Model
{
    use HasFactory;

    protected $fillable = [
        'news_article_id',
        'media_asset_id',
        'kind',
        'label_ar',
        'label_en',
        'legacy_source_table',
        'legacy_source_id',
        'legacy_path',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'legacy_source_id' => 'integer',
            'sort_order' => 'integer',
        ];
    }

    public function article(): BelongsTo
    {
        return $this->belongsTo(NewsArticle::class, 'news_article_id');
    }

    public function mediaAsset(): BelongsTo
    {
        return $this->belongsTo(MediaAsset::class, 'media_asset_id');
    }
}
