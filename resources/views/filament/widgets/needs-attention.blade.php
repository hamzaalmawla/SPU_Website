@php
    /** @var list<array{key: string, label: string, count: int, icon: string, tone: string, url: ?string, hint: ?string}> $items */
    $lists = ['drafts' => $drafts, 'scheduled' => $scheduled, 'missing_pages' => $missingPages];
@endphp

<x-filament-widgets::widget>
    <x-filament::section
        icon="heroicon-o-bell-alert"
        :heading="__('admin_dashboard.attention.heading')"
        :description="__('admin_dashboard.attention.description')"
    >
        @if ($items === [])
            <p class="spu-dash__empty spu-dash__empty--ok">
                <x-filament::icon icon="heroicon-o-check-circle" class="spu-dash__empty-icon" />
                {{ __('admin_dashboard.attention.all_clear') }}
            </p>
        @else
            <ul class="spu-dash-attention">
                @foreach ($items as $item)
                    @php($rows = $lists[$item['key']] ?? null)
                    <li class="spu-dash-attention__item spu-dash-attention__item--{{ $item['tone'] }}">
                        <x-filament::icon :icon="$item['icon']" class="spu-dash-attention__icon" />
                        <div class="spu-dash-attention__body">
                            <p class="spu-dash-attention__title">
                                <span class="spu-dash-attention__count" dir="ltr">{{ number_format($item['count']) }}</span>
                                <span>{{ $item['label'] }}</span>
                            </p>
                            @if ($item['hint'])
                                <p class="spu-dash__muted">{{ $item['hint'] }}</p>
                            @endif
                            @if ($rows !== null && $rows->isNotEmpty())
                                <ul class="spu-dash-sublist">
                                    @foreach ($rows->take(5) as $row)
                                        <li>
                                            @if ($row->url)
                                                <a href="{{ $row->url }}" target="_blank" rel="noopener" class="spu-dash-sublist__label" dir="auto">{{ $row->label }}</a>
                                            @else
                                                <span class="spu-dash-sublist__label" dir="auto">{{ $row->label }}</span>
                                            @endif
                                            @if ($item['key'] === 'missing_pages')
                                                <span class="spu-dash__muted">{{ __('admin_dashboard.attention.visits', ['count' => number_format($row->value)]) }} · {{ $row->hint }}</span>
                                            @elseif ($row->hint)
                                                <span class="spu-dash__muted">{{ $row->hint }}</span>
                                            @endif
                                        </li>
                                    @endforeach
                                    @if ($item['count'] > 5)
                                        <li class="spu-dash__muted">{{ __('admin_dashboard.attention.more', ['count' => number_format($item['count'] - 5)]) }}</li>
                                    @endif
                                </ul>
                            @endif
                        </div>
                        @if ($item['url'])
                            <x-filament::link :href="$item['url']" icon="heroicon-m-arrow-up-right" icon-position="after" class="spu-dash-attention__open">
                                {{ __('admin_dashboard.attention.open') }}
                            </x-filament::link>
                        @endif
                    </li>
                @endforeach
            </ul>
        @endif
    </x-filament::section>
</x-filament-widgets::widget>
