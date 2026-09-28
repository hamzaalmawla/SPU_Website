<?php

declare(strict_types=1);

namespace App\Models\Achievement;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AchievementTranslation extends Model
{
    use HasFactory;

    protected $fillable = ['achievement_id', 'locale', 'title', 'summary', 'meta', 'action_label', 'action_url'];

    public function achievement(): BelongsTo
    {
        return $this->belongsTo(Achievement::class);
    }
}
