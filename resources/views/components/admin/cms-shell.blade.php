@props([
    'area' => null,
    'description' => null,
    'state' => null,
    'stateColor' => 'gray',
    'locales' => ['ar', 'en'],
    'links' => [],
])

<div class="space-y-6">
    <x-admin.cms-workspace-header
        :area="$area"
        :description="$description"
        :state="$state"
        :state-color="$stateColor"
        :locales="$locales"
        :links="$links"
    />

    {{ $slot }}
</div>
