<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AboutPageTranslation extends Model
{
    use HasFactory;

    protected $fillable = [
        'about_page_id',
        'locale',
        'title',
        'headline',
        'summary',
        'sections_json',
    ];

    protected function casts(): array
    {
        return [
            'sections_json' => 'array',
        ];
    }

    public function aboutPage(): BelongsTo
    {
        return $this->belongsTo(AboutPage::class);
    }
}
