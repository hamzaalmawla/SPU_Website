<?php

declare(strict_types=1);

namespace App\Models\Content;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PartnershipTranslation extends Model
{
    use HasFactory;

    protected $fillable = [
        'partnership_id',
        'locale',
        'name',
        'category',
        'status',
        'established_label',
        'description',
        'scope',
    ];

    public function partnership(): BelongsTo
    {
        return $this->belongsTo(Partnership::class);
    }
}
