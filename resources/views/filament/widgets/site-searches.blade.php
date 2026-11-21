@php
    /** @var \Illuminate\Support\Collection<int, \App\DTOs\Dashboard\DashboardRowDTO> $top */
    /** @var \Illuminate\Support\Collection<int, \App\DTOs\Dashboard\DashboardRowDTO> $missing */
@endphp

<x-filament-widgets::widget>
    <x-filament::section
        icon="heroicon-o-magnifying-glass"
        :heading="__('admin_dashboard.searches.heading')"
        :description="__('admin_dashboard.searches.description')"
    >
        @if ($top->isEmpty())
            <p class="spu-dash__empty">{{ __('admin_dashboard.searches.empty') }}</p>
        @else
            <div class="spu-dash-groups">
                <section>
                    <h3 class="spu-dash__subheading">{{ __('admin_dashboard.searches.top') }}</h3>
                    <ul class="spu-dash-list">
                        @foreach ($top as $term)
                            <li>
                                <a href="{{ $term->url }}" target="_blank" rel="noopener" dir="auto">{{ $term->label }}</a>
                                <span class="spu-dash-list__value"><span class="spu-dash__tag">{{ $term->hint }}</span> <span dir="ltr">{{ __('admin_dashboard.searches.times', ['count' => number_format($term->value)]) }}</span></span>
                            </li>
                        @endforeach
                    </ul>
                </section>
                <section>
                    <h3 class="spu-dash__subheading">{{ __('admin_dashboard.searches.no_results') }}</h3>
                    <p class="spu-dash__muted">{{ __('admin_dashboard.searches.no_results_hint') }}</p>
                    @if ($missing->isEmpty())
                        <p class="spu-dash__empty spu-dash__empty--ok">{{ __('admin_dashboard.searches.none_missing') }}</p>
                    @else
                        <ul class="spu-dash-list">
                            @foreach ($missing as $term)
                                <li>
                                    <a href="{{ $term->url }}" target="_blank" rel="noopener" dir="auto">{{ $term->label }}</a>
                                    <span class="spu-dash-list__value"><span class="spu-dash__tag">{{ $term->hint }}</span> <span dir="ltr">{{ __('admin_dashboard.searches.times', ['count' => number_format($term->value)]) }}</span></span>
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </section>
            </div>
        @endif
    </x-filament::section>
</x-filament-widgets::widget>
