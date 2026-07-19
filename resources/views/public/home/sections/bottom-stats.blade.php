<section x-data="statsCounter()" class="bg-white py-16 font-hacen reveal lg:py-20">
    <div class="container">
        @if ($section->payload->stats !== [])
            <div class="relative overflow-hidden rounded-[28px] bg-spu-blue px-6 py-12 shadow-[0_28px_80px_rgba(17,26,63,0.22)] sm:px-8 lg:px-12">
                <div class="absolute inset-x-12 top-0 h-px bg-white/20" aria-hidden="true"></div>
                @if ($section->payload->title)
                    <h2 class="mb-10 text-center text-2xl font-bold text-white lg:text-3xl">{{ $section->payload->title }}</h2>
                @endif

                <div class="cms-grid-stats gap-8 lg:gap-10">
                    @foreach ($section->payload->stats as $stat)
                        <article class="flex min-h-[130px] flex-col items-center justify-center rounded-2xl border border-white/10 bg-white/[0.03] px-4 py-6 text-center">
                            <div class="mb-3 flex items-baseline justify-center" dir="ltr">
                                @if ($stat->prefix)
                                    <span class="text-3xl font-bold text-white" translate="no">{{ $stat->prefix }}</span>
                                @endif
                                <span class="stats-card-value text-5xl font-bold tracking-tighter text-white lg:text-6xl" data-value="{{ $stat->value }}" translate="no">{{ $stat->value }}</span>
                                @if ($stat->suffix)
                                    <span class="ms-1 text-3xl font-bold text-spu-red" translate="no">{{ $stat->suffix }}</span>
                                @endif
                            </div>
                            <p class="text-xs font-bold uppercase tracking-widest text-[#799DD6]">{{ $stat->label }}</p>
                            @if ($stat->helperText)
                                <p class="mt-2 max-w-[220px] text-sm leading-relaxed text-white/70">{{ $stat->helperText }}</p>
                            @endif
                        </article>
                    @endforeach
                </div>
            </div>
        @endif
    </div>
</section>
