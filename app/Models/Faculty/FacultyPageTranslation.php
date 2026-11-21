<?php

declare(strict_types=1);

namespace App\Models\Faculty;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FacultyPageTranslation extends Model
{
    use HasFactory;

    protected $fillable = ['faculty_page_id', 'locale', 'title', 'summary', 'body', 'sections_json'];

    protected function casts(): array
    {
        return ['sections_json' => 'array'];
    }

    public function page(): BelongsTo
    {
        return $this->belongsTo(FacultyPage::class, 'faculty_page_id');
    }
}
