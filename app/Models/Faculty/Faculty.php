<?php

declare(strict_types=1);

namespace App\Models\Faculty;

use App\Models\Career\Alumni;
use App\Models\Career\HonorStudent;
use App\Models\Person\FacultyMember;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Faculty extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'slug',
        'public_slug',
        'faculty_scope_slug',
        'accent_color',
        'hero_image',
        'logo_image',
        'gallery_json',
        'sort_order',
        'is_enabled',
    ];

    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
            'is_enabled' => 'boolean',
            'gallery_json' => 'array',
            'deleted_at' => 'datetime',
        ];
    }

    public function translations(): HasMany
    {
        return $this->hasMany(FacultyTranslation::class)->orderBy('locale');
    }

    public function departments(): HasMany
    {
        return $this->hasMany(Department::class)->orderBy('sort_order');
    }

    public function pages(): HasMany
    {
        return $this->hasMany(FacultyPage::class)->orderBy('sort_order');
    }

    public function highlights(): HasMany
    {
        return $this->hasMany(FacultyHighlight::class)->orderBy('sort_order');
    }

    public function labs(): HasMany
    {
        return $this->hasMany(FacultyLab::class)->orderBy('sort_order');
    }

    public function studentProjects(): HasMany
    {
        return $this->hasMany(FacultyStudentProject::class)->orderBy('sort_order');
    }

    public function members(): HasMany
    {
        return $this->hasMany(FacultyMember::class)->orderBy('sort_order');
    }

    public function alumni(): HasMany
    {
        return $this->hasMany(Alumni::class);
    }

    public function honorStudents(): HasMany
    {
        return $this->hasMany(HonorStudent::class)->orderBy('sort_order');
    }

    public function scopeEnabled(Builder $query): Builder
    {
        return $query->where('is_enabled', true);
    }
}
