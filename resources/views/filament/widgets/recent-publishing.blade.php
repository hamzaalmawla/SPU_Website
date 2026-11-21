@php
    /** @var \Illuminate\Support\Collection<int, \App\DTOs\Dashboard\DashboardRowDTO> $rows */
@endphp

<x-filament-widgets::widget>
    <x-filament::section
        icon="heroicon-o-rocket-launch"
        :heading="__('admin_dashboard.publishing.heading')"
        :description="__('admin_dashboard.publishing.description')"
    >
        @if ($rows->isEmpty())
            <p class="spu-dash__empty">{{ __('admin_dashboard.publishing.empty') }}</p>
        @else
            <ul class="spu-dash-list">
                @foreach ($rows as $row)
                    <li>
                        <span class="spu-dash-list__main">
                            @if ($row->url)
                                <a href="{{ $row->url }}" target="_blank" rel="noopener" dir="auto">{{ $row->label }}</a>
                            @else
                                <span dir="auto">{{ $row->label }}</span>
                            @endif
                            <span class="spu-dash__muted">{{ $row->hint }}</span>
                        </span>
                        <x-filament::badge :color="$row->value === 1 ? 'success' : 'gray'">
                            {{ $row->value === 1 ? __('admin_dashboard.publishing.published') : __('admin_dashboard.publishing.unpublished') }}
                        </x-filament::badge>
                    </li>
                @endforeach
            </ul>
            @if ($auditUrl)
                <p class="spu-dash__footer">
                    <x-filament::link :href="$auditUrl" icon="heroicon-m-arrow-up-right" icon-position="after">{{ __('admin_dashboard.publishing.all') }}</x-filament::link>
                </p>
            @endif
        @endif
    </x-filament::section>
</x-filament-widgets::widget>
