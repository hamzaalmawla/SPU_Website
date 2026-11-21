<?php

declare(strict_types=1);

namespace App\Models\Career;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/** One grade of the shared graduate grades list; `alumni.grade` holds its key. */
class GraduationGrade extends Model
{
    use SoftDeletes;

    protected $fillable = ['key', 'sort_order', 'replaced_by_key'];

    protected function casts(): array
    {
        return ['sort_order' => 'integer', 'deleted_at' => 'datetime'];
    }

    public function translations(): HasMany
    {
        return $this->hasMany(GraduationGradeTranslation::class)->orderBy('locale');
    }
}
