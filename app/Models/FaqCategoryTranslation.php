<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FaqCategoryTranslation extends Model
{
    use HasFactory;

    protected $fillable = ['faq_category_id', 'locale', 'name', 'description'];

    public function faqCategory(): BelongsTo
    {
        return $this->belongsTo(FaqCategory::class);
    }
}
