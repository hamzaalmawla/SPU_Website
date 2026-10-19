<?php

declare(strict_types=1);

namespace App\Models\Research;

use App\Models\Faculty\Faculty;
use App\Models\Person\FacultyMember;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

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
        'registration_status',
        'notes',
        'legacy_source',
        'legacy_source_id',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'legacy_source_id' => 'integer',
        ];
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
