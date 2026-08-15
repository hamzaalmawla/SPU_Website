<?php

declare(strict_types=1);

namespace App\Models\Person;

use App\Enums\PublicationStatus;
use App\Models\Faculty\Faculty;
use App\Models\Media\MediaAsset;
use App\Models\Research\ResearchPublication;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
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
        'photo_media_id',
        'cv_media_id',
        'orcid_url',
        'scholar_url',
        'legacy_photo_path',
        'legacy_cv_path',
        'legacy_ar_cv_path',
        'sort_order',
        'is_enabled',
        'publication_status',
        'published_at',
    ];

    protected function casts(): array
    {
        return [
            'social_links' => 'array',
            'sort_order' => 'integer',
            'is_enabled' => 'boolean',
            'published_at' => 'datetime',
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

    public function appointments(): HasMany
    {
        return $this->hasMany(PersonAppointment::class)->orderBy('sort_order');
    }

    public function photoMedia(): BelongsTo
    {
        return $this->belongsTo(MediaAsset::class, 'photo_media_id');
    }

    public function cvMedia(): BelongsTo
    {
        return $this->belongsTo(MediaAsset::class, 'cv_media_id');
    }

    public function researchPublications(): HasMany
    {
        return $this->hasMany(ResearchPublication::class)->orderByDesc('published_at');
    }

    public function councilMemberships(): HasMany
    {
        return $this->hasMany(CouncilMember::class)->orderBy('sort_order');
    }

    public function scopeEnabled(Builder $query): Builder
    {
        return $query->where('is_enabled', true);
    }

    public function scopePublic(Builder $query): Builder
    {
        return $query
            ->where('is_enabled', true)
            ->where('publication_status', PublicationStatus::Published->value)
            ->whereNotNull('published_at')
            ->where('published_at', '<=', now());
    }
}
