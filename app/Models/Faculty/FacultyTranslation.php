<?php

declare(strict_types=1);

namespace App\Models\Faculty;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FacultyTranslation extends Model
{
    use HasFactory;

    protected $fillable = ['faculty_id', 'locale', 'name', 'catalog_title', 'short_description', 'description', 'years_label'];

    public function faculty(): BelongsTo
    {
        return $this->belongsTo(Faculty::class);
    }
}
