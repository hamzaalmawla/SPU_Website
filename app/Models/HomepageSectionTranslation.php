<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HomepageSectionTranslation extends Model
{
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'section_id',
        'locale',
        'payload_json',
    ];

    protected function casts(): array
    {
        return [
            'payload_json' => 'array',
        ];
    }

    public function section(): BelongsTo
    {
        return $this->belongsTo(HomepageSection::class, 'section_id');
    }
}
