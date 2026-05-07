<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\MediaAsset;
use App\Models\User;

/**
 * Authorizes media asset management actions.
 *
 * super_admin has full access via before(). Editors and faculty editors
 * can view and upload media. Only editors can delete.
 */
final class MediaAssetPolicy
{
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

        if ($user->role_slug !== 'faculty_editor') {
            return false;
        }

        $userScope = is_string($user->faculty_scope_slug) ? $user->faculty_scope_slug : '';
        $assetScope = $mediaAsset->getAttribute('faculty_scope_slug');

        return $userScope !== '' && is_string($assetScope) && $assetScope === $userScope;
    }
}
