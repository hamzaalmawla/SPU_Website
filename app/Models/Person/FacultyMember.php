<?php

declare(strict_types=1);

namespace App\Models\Person;

use App\Models\Faculty\Department;
use App\Models\Faculty\Faculty;
use App\Models\Media\MediaAsset;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class FacultyMember extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'faculty_id',
        'department_id',
        'email',
        'phone',
        'photo_media_id',
        'cv_media_id',
        'sort_order',
        'is_enabled',
    ];

    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
            'is_enabled' => 'boolean',
            'deleted_at' => 'datetime',
        ];
    }

    public function faculty(): BelongsTo
    {
        return $this->belongsTo(Faculty::class);
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function photoMedia(): BelongsTo
    {
        return $this->belongsTo(MediaAsset::class, 'photo_media_id');
    }

    public function cvMedia(): BelongsTo
    {
        return $this->belongsTo(MediaAsset::class, 'cv_media_id');
    }

    public function translations(): HasMany
    {
        return $this->hasMany(FacultyMemberTranslation::class)->orderBy('locale');
    }

    public function councilMemberships(): HasMany
    {
        return $this->hasMany(CouncilMember::class)->orderBy('sort_order');
    }

    public function researchPublications(): HasMany
    {
        return $this->hasMany(ResearchPublication::class)->orderByDesc('published_at');
    }

    public function scopeEnabled(Builder $query): Builder
    {
        return $query->where('is_enabled', true);
    }
}
