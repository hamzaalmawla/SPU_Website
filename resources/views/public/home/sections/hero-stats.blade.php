@if ($section->payload->stats !== [])
@php($statsCount = count($section->payload->stats))
@php($statsGridCols = $statsCount >= 4 ? 'stats-grid-cols-4' : ($statsCount === 3 ? 'stats-grid-cols-3' : ($statsCount === 2 ? 'stats-grid-cols-2' : '')))
<section x-data="statsCounter()" class="stats-section relative z-20 w-full overflow-hidden font-hacen reveal scroll-mt-24">
    <div class="container">
        <div class="stats-shell reveal-item">
            <div class="stats-shell__grid {{ $statsGridCols }}">
                    @foreach ($section->payload->stats as $stat)
                        <article class="stats-card" style="--card-accent: #caa949;">
                            <div class="stats-card__top">
                                @if ($stat->icon)
                                    <div class="stats-icon-badge">
                                        <img src="{{ $stat->icon }}" alt="{{ $stat->label }}" class="h-7 w-7 object-contain brightness-0 text-white invert sm:h-8 sm:w-8" width="32" height="32" loading="lazy" decoding="async">
                                    </div>
                                @endif
                            </div>
                            <div class="stats-card__body">
                                <div class="stats-card__value-row">
                                    @if ($stat->prefix)
                                        <span class="stats-card-value" translate="no">{{ $stat->prefix }}</span>
                                    @endif
                                    <span class="stats-card-value" data-value="{{ $stat->value }}" translate="no">{{ $stat->value }}</span>
                                    @if ($stat->suffix)
                                        <span class="stats-card-plus">{{ $stat->suffix }}</span>
                                    @endif
                                </div>
                                <p class="stats-card-label">{{ $stat->label }}</p>
                                @if ($stat->helperText)
                                    <p class="stats-card-summary">{{ $stat->helperText }}</p>
                                @endif
                            </div>
                            <span class="stats-card-line" aria-hidden="true"></span>
                        </article>
                    @endforeach
            </div>
        </div>
    </div>
</section>
@endif
