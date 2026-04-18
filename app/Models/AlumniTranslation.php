<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AlumniTranslation extends Model
{
    use HasFactory;

    protected $fillable = ['alumni_id', 'locale', 'full_name'];

    public function alumni(): BelongsTo
    {
        return $this->belongsTo(Alumni::class);
    }
}
