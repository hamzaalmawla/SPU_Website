<?php

declare(strict_types=1);

namespace App\Models\News;

use App\Models\Media\MediaAsset;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NewsArticleSeoMeta extends Model
{
    use HasFactory;

    protected $table = 'news_article_seo_meta';

    protected $fillable = [
        'news_article_id',
        'locale',
        'meta_title',
        'meta_description',
        'og_title',
        'og_description',
        'og_image_media_id',
        'og_image_url',
        'robots',
    ];

    public function article(): BelongsTo
    {
        return $this->belongsTo(NewsArticle::class, 'news_article_id');
    }

    public function ogImageMedia(): BelongsTo
    {
        return $this->belongsTo(MediaAsset::class, 'og_image_media_id');
    }
}
