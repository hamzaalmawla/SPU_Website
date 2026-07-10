<?php

declare(strict_types=1);

namespace App\Models\Person;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PersonTranslation extends Model
{
    use HasFactory;

    protected $fillable = [
        'person_id',
        'locale',
        'name',
        'role',
        'bio',
        'quote',
        'education',
    ];

    public function person(): BelongsTo
    {
        return $this->belongsTo(Person::class);
    }
}
