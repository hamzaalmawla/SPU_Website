<?php

declare(strict_types=1);

namespace App\Models\Research;

use App\Enums\PublicationStatus;
use App\Models\Media\MediaAsset;
use App\Models\Person\FacultyMember;
use App\Models\Person\Person;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class ResearchPublication extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'faculty_member_id',
        'person_id',
        'category_key',
        'published_at',
        'publication_year',
        'doi',
        'journal_rank',
        'legacy_source_table',
        'legacy_source_id',
        'legacy_owner_id',
        'legacy_owner_source',
        'legacy_image_path',
        'extraction_status',
        'external_url',
        'file_media_id',
        'sort_order',
        'is_enabled',
    ];

    protected function casts(): array
    {
        return [
            'published_at' => 'date',
            'publication_year' => 'integer',
            'legacy_source_id' => 'integer',
            'legacy_owner_id' => 'integer',
            'sort_order' => 'integer',
            'is_enabled' => 'boolean',
            'deleted_at' => 'datetime',
        ];
    }

    public function facultyMember(): BelongsTo
    {
        return $this->belongsTo(FacultyMember::class);
    }

    public function person(): BelongsTo
    {
        return $this->belongsTo(Person::class);
    }

    public function fileMedia(): BelongsTo
    {
        return $this->belongsTo(MediaAsset::class, 'file_media_id');
    }

    public function translations(): HasMany
    {
        return $this->hasMany(ResearchPublicationTranslation::class)->orderBy('locale');
    }

    public function files(): HasMany
    {
        return $this->hasMany(ResearchFile::class)->orderBy('sort_order');
    }

    public function legacyFileReferences(): HasMany
    {
        return $this->hasMany(LegacyResearchFileReference::class)->orderBy('sort_order');
    }

    public function scopeEnabled(Builder $query): Builder
    {
        return $query->where('is_enabled', true);
    }

    public function scopePublic(Builder $query): Builder
    {
        return $query
            ->enabled()
            ->where(function (Builder $publicationQuery): void {
                $publicationQuery
                    ->where(function (Builder $legacyQuery): void {
                        $legacyQuery
                            ->where('legacy_source_table', 'jx_member_categories')
                            ->where('extraction_status', 'published');
                    })
                    ->orWhere(function (Builder $nativeQuery): void {
                        $nativeQuery
                            ->whereNull('legacy_source_table')
                            ->whereNotNull('published_at')
                            ->whereDate('published_at', '<=', today());
                    });
            })
            ->where(function (Builder $ownershipQuery): void {
                $ownershipQuery
                    ->where(function (Builder $legacyQuery): void {
                        $legacyQuery
                            ->where('legacy_source_table', 'jx_member_categories')
                            ->where('extraction_status', 'published');
                    })
                    ->orWhere(function (Builder $nativeQuery): void {
                        $nativeQuery
                            ->whereNull('legacy_source_table')
                            ->whereNull('faculty_member_id');
                    })
                    ->orWhereHas('facultyMember', function (Builder $memberQuery): void {
                        $memberQuery
                            ->where('is_enabled', true)
                            ->where('publication_status', PublicationStatus::Published->value)
                            ->whereNotNull('published_at')
                            ->where('published_at', '<=', now());
                    });
            });
    }
}
