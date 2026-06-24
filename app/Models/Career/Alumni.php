<?php

declare(strict_types=1);

namespace App\Models\Career;

use App\Models\Faculty\Department;
use App\Models\Faculty\Faculty;
use App\Models\Location\City;
use App\Models\Location\Country;
use App\Models\Media\MediaAsset;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Alumni extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'alumni';

    protected $fillable = [
        'student_identifier',
        'email',
        'phone',
        'faculty_id',
        'department_id',
        'degree',
        'graduation_year',
        'country_id',
        'city_id',
        'photo_media_id',
        'is_featured',
        'is_enabled',
    ];

    protected function casts(): array
    {
        return [
            'graduation_year' => 'integer',
            'is_featured' => 'boolean',
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

    public function country(): BelongsTo
    {
        return $this->belongsTo(Country::class);
    }

    public function city(): BelongsTo
    {
        return $this->belongsTo(City::class);
    }

    public function photoMedia(): BelongsTo
    {
        return $this->belongsTo(MediaAsset::class, 'photo_media_id');
    }

    public function translations(): HasMany
    {
        return $this->hasMany(AlumniTranslation::class)->orderBy('locale');
    }

    public function scopeEnabled(Builder $query): Builder
    {
        return $query->where('is_enabled', true);
    }

    public function scopeFeatured(Builder $query): Builder
    {
        return $query->where('is_featured', true);
    }
}
