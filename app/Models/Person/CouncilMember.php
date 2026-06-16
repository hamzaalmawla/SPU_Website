<?php

declare(strict_types=1);

namespace App\Models\Person;

use App\Models\Faculty\Council;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class CouncilMember extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = ['council_id', 'faculty_member_id', 'sort_order', 'is_enabled'];

    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
            'is_enabled' => 'boolean',
            'deleted_at' => 'datetime',
        ];
    }

    public function council(): BelongsTo
    {
        return $this->belongsTo(Council::class);
    }

    public function facultyMember(): BelongsTo
    {
        return $this->belongsTo(FacultyMember::class);
    }

    public function translations(): HasMany
    {
        return $this->hasMany(CouncilMemberTranslation::class)->orderBy('locale');
    }

    public function scopeEnabled(Builder $query): Builder
    {
        return $query->where('is_enabled', true);
    }
}
