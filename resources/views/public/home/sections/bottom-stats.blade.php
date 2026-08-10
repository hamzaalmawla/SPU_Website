@if ($section->payload->stats !== [])
    @php($statsCount = count($section->payload->stats))
    <section x-data="statsCounter()" class="bg-white py-8 font-hacen reveal">
        <div class="container">
            <div class="relative overflow-hidden rounded-[28px] bg-spu-blue px-6 py-10 shadow-[0_20px_50px_rgba(32,39,89,0.18)] md:px-10" dir="{{ $locale === 'ar' ? 'rtl' : 'ltr' }}">
                <div class="absolute inset-0 opacity-10" aria-hidden="true" style="background-image: radial-gradient(circle, #fff 1px, transparent 1px); background-size: 24px 24px;"></div>
                <div class="relative z-10 grid gap-8 {{ $statsCount === 3 ? 'md:grid-cols-3' : 'md:grid-cols-2' }}">
                    @foreach ($section->payload->stats as $stat)
                        <div class="text-center text-white">
                            <div class="flex items-baseline justify-center">
                                @if ($stat->prefix)<span class="text-2xl font-bold" translate="no">{{ $stat->prefix }}</span>@endif
                                <span class="text-4xl font-bold tracking-tight md:text-5xl" data-value="{{ $stat->value }}" translate="no">{{ $stat->value }}</span>
                                @if ($stat->suffix)<span class="ms-1 text-2xl font-bold text-[#d8bd6e]" translate="no">{{ $stat->suffix }}</span>@endif
                            </div>
                            <p class="mt-2 text-sm font-bold text-white/75">{{ $stat->label }}</p>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    </section>
@endif
