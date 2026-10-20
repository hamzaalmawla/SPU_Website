<?php

declare(strict_types=1);

namespace App\Policies;

use App\Contracts\Auth\AdminAccessServiceInterface;
use App\Models\Media\MediaAsset;
use App\Models\User\User;

/**
 * Authorizes the media library through the `media.*` permissions. A user
 * with a faculty limit reaches media of those faculties and media they
 * uploaded themselves.
 */
final class MediaAssetPolicy
{
    public function __construct(
        private readonly AdminAccessServiceInterface $access,
    ) {}

    public function viewAny(User $user): bool
    {
        return $this->access->can($user, 'media.view');
    }

    public function view(User $user, MediaAsset $mediaAsset): bool
    {
        return $this->allowed($user, 'media.view', $mediaAsset);
    }

    public function create(User $user): bool
    {
        return $this->access->can($user, 'media.create');
    }

    public function update(User $user, MediaAsset $mediaAsset): bool
    {
        return $this->allowed($user, 'media.update', $mediaAsset);
    }

    public function delete(User $user, MediaAsset $mediaAsset): bool
    {
        return $this->allowed($user, 'media.delete', $mediaAsset);
    }

    private function allowed(User $user, string $permission, MediaAsset $mediaAsset): bool
    {
        if (! $this->access->can($user, $permission)) {
            return false;
        }

        $scope = $mediaAsset->getAttribute('faculty_scope_slug');

        return (int) $mediaAsset->getAttribute('uploaded_by') === (int) $user->getKey()
            || $this->access->withinFacultyLimit($user, is_string($scope) ? $scope : null);
    }
}
