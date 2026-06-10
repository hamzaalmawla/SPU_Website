<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DirectorateTranslation extends Model
{
    use HasFactory;

    protected $fillable = ['directorate_id', 'locale', 'title', 'summary', 'description', 'services_json'];

    protected function casts(): array
    {
        return [
            'services_json' => 'array',
        ];
    }

    public function directorate(): BelongsTo
    {
        return $this->belongsTo(Directorate::class);
    }
}
