<section x-data="statsCounter()" class="py-16 bg-slate-50 font-hacen overflow-hidden reveal">
    <div class="container">
        <h2 class="text-4xl lg:text-5xl font-bold text-spu-blue mb-10">{{ $section->payload->title }}</h2>

        <div class="grid grid-cols-12 gap-4 md:gap-8 mb-16">
            @isset($section->payload->items[0])
                @php($mainItem = $section->payload->items[0])
                @php($mainImage = $mainItem['imageUrl'] ?? ($mainItem['image'] ?? null))
                <article class="col-span-7 bg-white rounded-[2rem] shadow-xl overflow-hidden flex flex-col group hover:shadow-2xl transition-all duration-500">
                    @if ($mainImage)
                        <div class="h-64 md:h-[350px] overflow-hidden relative">
                            <img src="{{ $mainImage }}" alt="{{ $mainItem['title'] ?? '' }}" loading="lazy" decoding="async" width="840" height="350" class="w-full h-full object-cover transition-transform duration-1000 group-hover:scale-110">
                            <div class="absolute inset-0 bg-gradient-to-t from-black/20 to-transparent"></div>
                        </div>
                    @endif
                    <div class="p-5 md:p-8 flex-1 flex flex-col justify-between">
                        <div>
                            @if (! empty($mainItem['title']))<h3 class="text-2xl font-bold text-spu-blue mb-4">{{ $mainItem['title'] }}</h3>@endif
                            @if (! empty($mainItem['summary']))<p class="text-gray-600 leading-relaxed mb-6">{{ $mainItem['summary'] }}</p>@endif
                            @if (! empty($mainItem['features']))
                            <ul class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-8">
                                    @foreach ($mainItem['features'] as $feature)
                                        <li class="flex items-center gap-3"><div class="w-6 h-6 rounded-full bg-green-500/30 flex items-center justify-center shrink-0"><img src="/images/icons/check-circle.svg" class="w-3 h-3 brightness-105 invert" alt=""></div><span class="text-spu-blue font-medium text-sm">{{ $feature }}</span></li>
                                    @endforeach
                                </ul>
                            @endif
                        </div>
                        @if (! empty($mainItem['action']['url']) && ! empty($mainItem['action']['label']))
                            <a href="{{ $mainItem['action']['url'] }}" class="inline-flex items-center justify-center px-10 py-3 bg-spu-blue text-white rounded-xl font-bold hover:bg-spu-red transition-all self-start" @if (! empty($mainItem['action']['target'])) target="{{ $mainItem['action']['target'] }}" rel="noreferrer" @endif>{{ $mainItem['action']['label'] }}</a>
                        @endif
                    </div>
                </article>
            @endisset

            @if (isset($section->payload->items[1]) || isset($section->payload->items[2]))
                <div class="col-span-5 grid grid-cols-1 gap-4 md:gap-8">
                    @foreach ([1, 2] as $index)
                        @isset($section->payload->items[$index])
                            @php($sideItem = $section->payload->items[$index])
                            @php($sideImage = $sideItem['imageUrl'] ?? ($sideItem['image'] ?? null))
                            <article class="bg-white rounded-[2rem] shadow-lg overflow-hidden flex flex-col flex-1 group hover:shadow-xl transition-all">
                                @if ($sideImage)
                                    <div class="h-40 md:h-48 overflow-hidden"><img src="{{ $sideImage }}" alt="{{ $sideItem['title'] ?? '' }}" loading="lazy" decoding="async" width="560" height="192" class="w-full h-full object-cover transition-transform duration-700 group-hover:scale-110"></div>
                                @endif
                                <div class="p-5 md:p-6">
                                    @if (! empty($sideItem['title']))<h4 class="text-xl font-bold text-spu-blue mb-2">{{ $sideItem['title'] }}</h4>@endif
                                    @if (! empty($sideItem['summary']))<p class="text-gray-500 text-sm line-clamp-2">{{ $sideItem['summary'] }}</p>@endif
                                    @if (! empty($sideItem['action']['url']) && ! empty($sideItem['action']['label']))
                                        <a href="{{ $sideItem['action']['url'] }}" class="text-spu-blue font-bold text-sm flex items-center gap-2 hover:text-spu-red transition-colors mt-2" @if (! empty($sideItem['action']['target'])) target="{{ $sideItem['action']['target'] }}" rel="noreferrer" @endif><span>{{ $sideItem['action']['label'] }}</span><img src="/images/icon-chevron-right-outline.svg" class="w-2.5 h-2.5 rtl:rotate-180" alt=""></a>
                                    @endif
                                </div>
                            </article>
                        @endisset
                    @endforeach
                </div>
            @endif
        </div>

        @if ($section->payload->stats !== [])
            @php($statsCount = count($section->payload->stats))
            <div class="bg-spu-blue rounded-[24px] py-12 px-8 shadow-2xl relative overflow-hidden">
                <div class="cms-grid-stats gap-12 relative z-10 {{ $statsCount >= 4 ? 'cms-grid-stats-cols-4' : ($statsCount === 3 ? 'cms-grid-stats-cols-3' : ($statsCount === 2 ? 'cms-grid-stats-cols-2' : '')) }}">
                    @foreach ($section->payload->stats as $stat)
                        <div class="flex flex-col items-center justify-center text-center px-4">
                            <div class="flex items-baseline mb-3">
                                <span class="text-5xl lg:text-6xl font-bold text-white tracking-tighter stats-card-value" data-value="{{ $stat->value }}" translate="no">{{ $stat->value }}</span>
                                @if ($stat->suffix)<span class="text-3xl font-bold text-spu-red ms-1" translate="no">{{ $stat->suffix }}</span>@endif
                            </div>
                            <p class="text-[#799DD6] text-xs font-bold tracking-widest uppercase">{{ $stat->label }}</p>
                        </div>
                    @endforeach
                </div>
            </div>
        @endif
    </div>
</section>
