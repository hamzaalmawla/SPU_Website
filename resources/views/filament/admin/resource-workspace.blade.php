@php
    use App\Filament\Resources\AboutPageResource;
    use App\Filament\Resources\AuditLogResource;
    use App\Filament\Resources\ContactMessageResource;
    use App\Filament\Resources\DirectorateResource;
    use App\Filament\Resources\MediaAssetResource;
    use App\Filament\Resources\NewsArticleResource;
    use App\Filament\Resources\PageResource;
    use App\Filament\Resources\PartnershipResource;
    use App\Filament\Resources\PersonResource;
    use App\Filament\Resources\UserResource;
    use App\Filament\Pages\ManageNews;
    use Filament\Resources\Resource;

    $resourceClass = collect($scopes ?? [])
        ->first(fn (mixed $scope): bool => is_string($scope) && class_exists($scope) && is_subclass_of($scope, Resource::class));

    $resourceAreas = [
        PageResource::class => 'pages',
        NewsArticleResource::class => 'news',
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
    $links = $area === 'news' ? [
        [
            'label' => __('admin.news_workspace.articles'),
            'url' => NewsArticleResource::getUrl('index'),
            'active' => $resourceClass === NewsArticleResource::class,
        ],
        [
            'label' => __('admin.news_workspace.pages_events'),
            'url' => ManageNews::getUrl(),
            'active' => false,
        ],
    ] : [];
@endphp

@if ($area !== null)
    <x-admin.cms-workspace-header :area="$area" :links="$links" />
@endif
