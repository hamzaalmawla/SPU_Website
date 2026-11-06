<?php

declare(strict_types=1);

namespace App\Models\User;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One permission key (see App\Services\Auth\PermissionCatalog) held by a role.
 */
class RolePermission extends Model
{
    protected $table = 'role_permissions';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'role_id',
        'permission',
    ];

    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }
}
