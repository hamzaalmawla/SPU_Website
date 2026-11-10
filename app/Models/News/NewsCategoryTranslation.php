<?php

declare(strict_types=1);

namespace App\Models\News;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NewsCategoryTranslation extends Model
{
    use HasFactory;

    protected $fillable = ['news_category_id', 'locale', 'name', 'description'];

    public function category(): BelongsTo
    {
        return $this->belongsTo(NewsCategory::class, 'news_category_id');
    }
}
