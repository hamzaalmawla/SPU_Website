<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FaqTranslation extends Model
{
    use HasFactory;

    protected $fillable = ['faq_id', 'locale', 'question', 'answer', 'keywords'];

    public function faq(): BelongsTo
    {
        return $this->belongsTo(Faq::class);
    }
}
