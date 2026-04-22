<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HonorStudentTranslation extends Model
{
    use HasFactory;

    protected $fillable = ['honor_student_id', 'locale', 'full_name'];

    public function honorStudent(): BelongsTo
    {
        return $this->belongsTo(HonorStudent::class);
    }
}
