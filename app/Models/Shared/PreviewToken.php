<?php

declare(strict_types=1);

namespace App\Models\Shared;

use App\Models\User\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PreviewToken extends Model
{
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'token_hash',
        'target_type',
        'target_id',
        'locale',
        'device',
        'issued_to_user_id',
        'payload_json',
        'expires_at',
    ];

    protected function casts(): array
    {
        return [
            'target_id' => 'integer',
            'payload_json' => 'array',
            'expires_at' => 'datetime',
        ];
    }

    public function issuedToUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'issued_to_user_id');
    }
}
