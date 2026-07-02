@php
    use App\Filament\Resources\AboutPageResource;
    use App\Filament\Resources\AuditLogResource;
    use App\Filament\Resources\ContactMessageResource;
    use App\Filament\Resources\DirectorateResource;
    use App\Filament\Resources\MediaAssetResource;
    use App\Filament\Resources\NewsArticleResource;
    use App\Filament\Resources\NewsCategoryResource;
    use App\Filament\Resources\PageResource;
    use App\Filament\Resources\PartnershipResource;
    use App\Filament\Resources\PersonResource;
    use App\Filament\Resources\UserResource;
    use Filament\Resources\Resource;

    $resourceClass = collect($scopes ?? [])
        ->first(fn (mixed $scope): bool => is_string($scope) && class_exists($scope) && is_subclass_of($scope, Resource::class));

    $resourceAreas = [
        PageResource::class => 'pages',
        NewsArticleResource::class => 'news',
        NewsCategoryResource::class => 'news',
        MediaAssetResource::class => 'media',
        AboutPageResource::class => 'about',
        DirectorateResource::class => 'about',
        PartnershipResource::class => 'about',
        PersonResource::class => 'about',
        ContactMessageResource::class => 'contact',
        UserResource::class => 'administration',
        AuditLogResource::class => 'administration',
    ];

    $area = is_string($resourceClass) ? ($resourceAreas[$resourceClass] ?? null) : null;
@endphp

@if ($area !== null)
    <x-admin.cms-workspace-header :area="$area" />
@endif
