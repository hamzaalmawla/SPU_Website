<?php

declare(strict_types=1);

namespace App\Models\Person;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Person extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'persons';

    protected $fillable = [
        'slug',
        'category',
        'title',
        'position',
        'faculty_scope_slug',
        'email',
        'phone',
        'office_location',
        'image',
        'profile_url',
        'social_links',
        'sort_order',
        'is_enabled',
    ];

    protected function casts(): array
    {
        return [
            'social_links' => 'array',
            'sort_order' => 'integer',
            'is_enabled' => 'boolean',
            'deleted_at' => 'datetime',
        ];
    }

    public function translations(): HasMany
    {
        return $this->hasMany(PersonTranslation::class)->orderBy('locale');
    }

    public function educations(): HasMany
    {
        return $this->hasMany(PersonEducation::class)->orderBy('sort_order');
    }

    public function scopeEnabled(Builder $query): Builder
    {
        return $query->where('is_enabled', true);
    }
}
