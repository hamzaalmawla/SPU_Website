<?php

declare(strict_types=1);

namespace App\Models\Faculty;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DepartmentTranslation extends Model
{
    use HasFactory;

    protected $fillable = ['department_id', 'locale', 'name', 'description'];

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }
}
