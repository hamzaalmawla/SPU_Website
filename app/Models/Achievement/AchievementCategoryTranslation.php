<?php

declare(strict_types=1);

namespace App\Models\Achievement;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AchievementCategoryTranslation extends Model
{
    use HasFactory;

    protected $fillable = ['achievement_category_id', 'locale', 'name'];

    public function category(): BelongsTo
    {
        return $this->belongsTo(AchievementCategory::class, 'achievement_category_id');
    }
}
