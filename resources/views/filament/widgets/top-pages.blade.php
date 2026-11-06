@php
    /** @var \Illuminate\Support\Collection<int, \App\DTOs\Dashboard\DashboardRowDTO> $pages */
    $max = max(1, (int) $pages->max('value'));
@endphp

<x-filament-widgets::widget>
    <x-filament::section
        icon="heroicon-o-document-text"
        :heading="__('admin_dashboard.pages.heading')"
        :description="__('admin_dashboard.pages.description')"
    >
        @if ($pages->isEmpty())
            <p class="spu-dash__empty">{{ __('admin_dashboard.pages.empty') }}</p>
        @else
            <ol class="spu-dash-bars">
                @foreach ($pages as $page)
                    <li class="spu-dash-bars__row">
                        <div class="spu-dash-bars__text">
                            <a href="{{ $page->url }}" target="_blank" rel="noopener" class="spu-dash-bars__label" dir="auto">{{ $page->label }}</a>
                            @if ($page->hint !== $page->label)
                                <span class="spu-dash__muted spu-dash-bars__path" dir="ltr">{{ $page->hint }}</span>
                            @endif
                        </div>
                        <span class="spu-dash-bars__value" dir="ltr" title="{{ __('admin_dashboard.pages.views') }}">{{ number_format($page->value) }}</span>
                        <span class="spu-dash-bars__track" aria-hidden="true"><span class="spu-dash-bars__fill" style="width: {{ max(2, (int) round($page->value / $max * 100)) }}%"></span></span>
                    </li>
                @endforeach
            </ol>
        @endif
    </x-filament::section>
</x-filament-widgets::widget>
