<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CouncilTranslation extends Model
{
    use HasFactory;

    protected $fillable = ['council_id', 'locale', 'name', 'description'];

    public function council(): BelongsTo
    {
        return $this->belongsTo(Council::class);
    }
}
