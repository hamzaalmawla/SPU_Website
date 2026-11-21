@php
    /** @var array<string, array<string, int>> $groups */
    /** @var \Illuminate\Support\Collection<int, \App\DTOs\Dashboard\DashboardRowDTO> $referrers */
@endphp

<x-filament-widgets::widget>
    <x-filament::section
        icon="heroicon-o-globe-alt"
        :heading="__('admin_dashboard.sources.heading')"
        :description="__('admin_dashboard.sources.description')"
    >
        @if ($total === 0)
            <p class="spu-dash__empty">{{ __('admin_dashboard.sources.empty') }}</p>
        @else
            <div class="spu-dash-groups">
                @foreach ($groups as $group => $counts)
                    @php($groupTotal = max(1, array_sum($counts)))
                    <section>
                        <h3 class="spu-dash__subheading">{{ __('admin_dashboard.sources.'.$group) }}</h3>
                        <ul class="spu-dash-bars">
                            @foreach ($counts as $key => $count)
                                <li class="spu-dash-bars__row">
                                    <span class="spu-dash-bars__label">{{ __('admin_dashboard.sources.'.$key) }}</span>
                                    <span class="spu-dash-bars__value" dir="ltr">{{ number_format($count) }} <span class="spu-dash__muted">· {{ (int) round($count / $groupTotal * 100) }}%</span></span>
                                    <span class="spu-dash-bars__track" aria-hidden="true"><span class="spu-dash-bars__fill" style="width: {{ (int) round($count / $groupTotal * 100) }}%"></span></span>
                                </li>
                            @endforeach
                        </ul>
                    </section>
                @endforeach

                @if ($referrers->isNotEmpty())
                    <section>
                        <h3 class="spu-dash__subheading">{{ __('admin_dashboard.sources.referrers') }}</h3>
                        <ul class="spu-dash-list">
                            @foreach ($referrers as $referrer)
                                <li><span dir="ltr">{{ $referrer->label }}</span><span class="spu-dash-list__value" dir="ltr">{{ number_format($referrer->value) }}</span></li>
                            @endforeach
                        </ul>
                    </section>
                @endif
            </div>
        @endif
    </x-filament::section>
</x-filament-widgets::widget>
