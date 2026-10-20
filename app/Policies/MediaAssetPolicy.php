<?php

declare(strict_types=1);

namespace App\Policies;

use App\Contracts\Auth\AdminAccessServiceInterface;
use App\Models\Media\MediaAsset;
use App\Models\User\User;

/**
 * Authorizes media asset management actions.
 *
 * super_admin has full access via before(). Editors and limited editors
 * can view and upload media; a limited editor only sees media of their granted
 * faculties and media they uploaded. Only editors can delete.
 */
final class MediaAssetPolicy
{
    public function __construct(
        private readonly AdminAccessServiceInterface $access,
    ) {}

    public function before(User $user, string $ability): ?bool
    {
        return $user->role_slug === 'super_admin' ? true : null;
    }

    public function viewAny(User $user): bool
    {
        return in_array($user->role_slug, ['editor', 'faculty_editor'], true);
    }

    public function view(User $user, MediaAsset $mediaAsset): bool
    {
        return $this->canAccessScopedAsset($user, $mediaAsset);
    }

    public function create(User $user): bool
    {
        return in_array($user->role_slug, ['editor', 'faculty_editor'], true);
    }

    public function update(User $user, MediaAsset $mediaAsset): bool
    {
        return $this->canAccessScopedAsset($user, $mediaAsset);
    }

    public function delete(User $user, MediaAsset $mediaAsset): bool
    {
        return $user->role_slug === 'editor';
    }

    private function canAccessScopedAsset(User $user, MediaAsset $mediaAsset): bool
    {
        if ($user->role_slug === 'editor') {
            return true;
        }

        if (! $user->isLimitedEditor()) {
            return false;
        }

        $assetScope = $mediaAsset->getAttribute('faculty_scope_slug');

        return (int) $mediaAsset->getAttribute('uploaded_by') === (int) $user->getKey()
            || (is_string($assetScope) && $this->access->canManageFaculty($user, $assetScope));
    }
}
