<?php

declare(strict_types=1);

namespace App\Models\Content;

use App\Enums\PublicationStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use App\Models\Person\FacultyMember;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Directorate extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = ['slug', 'icon', 'email', 'location', 'manager_member_id', 'sort_order', 'is_enabled', 'publication_status', 'published_at'];

    protected function casts(): array
    {
        return [
            'manager_member_id' => 'integer',
            'sort_order' => 'integer',
            'is_enabled' => 'boolean',
            'published_at' => 'datetime',
            'deleted_at' => 'datetime',
        ];
    }

    public function translations(): HasMany
    {
        return $this->hasMany(DirectorateTranslation::class)->orderBy('locale');
    }

    /** The faculty profile of the directorate's manager. */
    public function manager(): BelongsTo
    {
        return $this->belongsTo(FacultyMember::class, 'manager_member_id');
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
