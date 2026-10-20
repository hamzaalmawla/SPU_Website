<?php

declare(strict_types=1);

namespace App\Filament\Concerns;

use App\Contracts\Auth\AdminAccessServiceInterface;
use App\Contracts\Cms\CmsTargetRegistryInterface;
use App\DTOs\Cms\CmsTargetDTO;
use App\Models\User\User;
use Illuminate\Support\Facades\Gate;

/**
 * Lets a limited editor into a CMS area screen only for the pages a super
 * administrator granted them. Full editors keep using the area's gate.
 *
 * The page picker is narrowed here for the editor's convenience; the CMS
 * workflow service still refuses any save, preview or publish of a page
 * that was not granted.
 */
trait LimitsCmsTargetsToGrants
{
    /**
     * @param  string|null  $targetKey  set when the screen edits one page only
     */
    protected static function canAccessCmsArea(string $ability, string $area, ?string $targetKey = null): bool
    {
        $user = auth()->user();

        if (! $user instanceof User || ! $user->isLimitedEditor()) {
            return Gate::allows($ability);
        }

        if ($targetKey !== null) {
            return $user->hasPageScope($targetKey);
        }

        return app(AdminAccessServiceInterface::class)->canEnterArea($user, $area);
    }

    /**
     * @param  array<string, string>  $options  target key => label
     * @return array<string, string>
     */
    protected function onlyGrantedTargets(array $options): array
    {
        $user = auth()->user();

        if (! $user instanceof User || ! $user->isLimitedEditor()) {
            return $options;
        }

        $access = app(AdminAccessServiceInterface::class);
        $registry = app(CmsTargetRegistryInterface::class);

        return array_filter(
            $options,
            static function (string $key) use ($access, $registry, $user): bool {
                $target = $registry->find($key);

                return $target instanceof CmsTargetDTO && $access->canManageTarget($user, $target);
            },
            ARRAY_FILTER_USE_KEY,
        );
    }

    /**
     * The page a screen opens on: its usual first page when the editor may
     * edit it, otherwise the first page they were granted.
     *
     * @param  array<string, string>  $options
     */
    protected function firstGrantedTarget(string $default, array $options): string
    {
        if ($options === [] || array_key_exists($default, $options)) {
            return $default;
        }

        return (string) array_key_first($options);
    }
}
