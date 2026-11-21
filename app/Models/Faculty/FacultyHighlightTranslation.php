<?php

declare(strict_types=1);

namespace App\Models\Faculty;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FacultyHighlightTranslation extends Model
{
    use HasFactory;

    protected $fillable = ['faculty_highlight_id', 'locale', 'title', 'summary'];

    public function highlight(): BelongsTo
    {
        return $this->belongsTo(FacultyHighlight::class, 'faculty_highlight_id');
    }
}
