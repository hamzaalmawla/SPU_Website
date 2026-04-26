<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ComplaintCategoryTranslation extends Model
{
    use HasFactory;

    protected $fillable = ['complaint_category_id', 'locale', 'name', 'description'];

    public function complaintCategory(): BelongsTo
    {
        return $this->belongsTo(ComplaintCategory::class);
    }
}
