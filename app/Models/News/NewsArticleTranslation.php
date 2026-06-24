<?php

declare(strict_types=1);

namespace App\Models\News;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NewsArticleTranslation extends Model
{
    use HasFactory;

    protected $fillable = ['news_article_id', 'locale', 'title', 'excerpt', 'body'];

    public function article(): BelongsTo
    {
        return $this->belongsTo(NewsArticle::class, 'news_article_id');
    }
}
