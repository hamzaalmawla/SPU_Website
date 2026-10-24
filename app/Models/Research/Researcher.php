<?php

declare(strict_types=1);

namespace App\Models\Research;

use App\Models\Faculty\Faculty;
use App\Models\Person\FacultyMember;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

/**
 * A person named on the university's research papers, as the research office
 * registers them: staff or not, linked to a faculty member profile when there
 * is one.
 */
class Researcher extends Model
{
    use SoftDeletes;

    public const REGISTRATION_STATUSES = ['registered', 'pending', 'inactive'];

    protected $fillable = [
        'full_name',
        'email',
        'phone',
        'faculty_id',
        'faculty_member_id',
        'affiliation',
        'orcid_url',
        'scholar_url',
        'scopus_url',
        'expertise',
        'registration_status',
        'is_enabled',
        'notes',
        'legacy_source',
        'legacy_source_id',
        'merged_into_id',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'legacy_source_id' => 'integer',
            'merged_into_id' => 'integer',
            'expertise' => 'array',
            'is_enabled' => 'boolean',
        ];
    }

    /** The public address of a researcher without a staff profile: name, then id (nizar-aldaher-r12). */
    public function publicSlug(): string
    {
        return trim(Str::slug($this->full_name).'-r'.$this->getKey(), '-');
    }

    public function faculty(): BelongsTo
    {
        return $this->belongsTo(Faculty::class);
    }

    public function facultyMember(): BelongsTo
    {
        return $this->belongsTo(FacultyMember::class);
    }

    public function publications(): BelongsToMany
    {
        return $this->belongsToMany(ResearchPublication::class, 'research_publication_researcher')
            ->withPivot(['role', 'sort_order', 'affiliation_snapshot'])
            ->withTimestamps();
    }
}
