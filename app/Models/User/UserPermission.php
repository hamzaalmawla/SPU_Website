<?php

declare(strict_types=1);

namespace App\Models\User;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One permission key (see App\Services\Auth\PermissionCatalog) held by a user.
 */
class UserPermission extends Model
{
    protected $table = 'user_permissions';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'permission',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
