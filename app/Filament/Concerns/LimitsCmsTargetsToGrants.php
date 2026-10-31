<?php

declare(strict_types=1);

namespace App\Filament\Concerns;

use App\Contracts\Auth\AdminAccessServiceInterface;
use App\Contracts\Cms\CmsTargetRegistryInterface;
use App\DTOs\Cms\CmsTargetDTO;
use App\Models\User\User;
use Illuminate\Support\Facades\Gate;

/**
 * Opens a CMS screen through its component's `view` permission and narrows
 * its page picker to the pages the user may edit (their page limit, if they
 * have one). Publish buttons follow the component's `publish` permission.
 *
 * The picker is narrowed here for the editor's convenience; the CMS workflow
 * service still refuses any save, preview or publish the user may not do.
 */
trait LimitsCmsTargetsToGrants
{
    /**
     * @param  string|null  $area  the screen's CMS area, for a user with a page limit
     */
    protected static function canAccessCmsArea(string $component, ?string $area = null): bool
    {
        $user = auth()->user();

        return $user instanceof User && app(AdminAccessServiceInterface::class)->canOpenScreen($user, $component, $area);
    }

    protected static function canPublishCmsArea(string $component): bool
    {
        return Gate::allows($component.'.publish');
    }

    /**
     * @param  array<string, string>  $options  target key => label
     * @return array<string, string>
     */
    protected function onlyGrantedTargets(array $options): array
    {
        $user = auth()->user();

        if (! $user instanceof User) {
            return [];
        }

        $access = app(AdminAccessServiceInterface::class);
        $registry = app(CmsTargetRegistryInterface::class);

        return array_filter(
            $options,
            static function (string $key) use ($access, $registry, $user): bool {
                $target = $registry->find($key);

                return $target instanceof CmsTargetDTO && $access->canManageTarget($user, $target, 'view');
            },
            ARRAY_FILTER_USE_KEY,
        );
    }

    /**
     * The page a screen opens on: its usual first page when the editor may
     * edit it, otherwise the first page they may.
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
