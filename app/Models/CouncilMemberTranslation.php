<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CouncilMemberTranslation extends Model
{
    use HasFactory;

    protected $fillable = ['council_member_id', 'locale', 'full_name', 'position', 'bio'];

    public function councilMember(): BelongsTo
    {
        return $this->belongsTo(CouncilMember::class);
    }
}
