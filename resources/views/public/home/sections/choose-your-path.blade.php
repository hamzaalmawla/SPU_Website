<section id="home-paths" x-data="pathSlider()" class="py-8 mt-[150px] relative font-hacen reveal" style="background-color: #EAF3FF40;">
    <div class="container relative">
        <div class="flex flex-col md:flex-row items-center gap-[52px] relative">
            <div class="w-full relative md:w-[322px] h-[435px] text-center bg-[#1e2652] rounded-[24px] flex flex-col justify-center items-start text-white shrink-0 overflow-hidden group shadow-[0_30px_80px_rgba(17,26,63,0.18)] z-20">
                <div class="absolute inset-0 opacity-[0.15] z-0 animate-slow-pan" style="background-image: radial-gradient(circle, #ffffff 1px, transparent 1px); background-size: 30px 30px;"></div>
                <div class="relative z-10 w-full px-6 flex flex-col h-full justify-center text-right">
                    @php($pathTitleParts = preg_split('/\s+/u', (string) $section->payload->title, 2))
                    <h2 class="text-[45px] text-center w-full font-bold leading-tight mb-12 transition-all duration-500">
                        <span class="sr-only">{{ $section->payload->title }}</span>
                        <span>{{ $pathTitleParts[0] ?? $section->payload->title }}</span>
                        @if (! empty($pathTitleParts[1]))
                            <br>
                            <span class="text-white">{{ $pathTitleParts[1] }}</span>
                        @endif
                    </h2>
                </div>
            </div>

            <div class="flex-1 min-w-0 w-full relative">
                <div class="flex gap-3 absolute -top-26 z-50 rtl:left-0 ltr:right-0">
                    <button type="button" @click="slidePaths('previous')" class="w-12 h-12 rounded-full border border-slate-200 flex items-center justify-center hover:bg-slate-50 transition-all" aria-controls="home-paths-track" aria-label="{{ __('public.previous') }}"><img src="/images/icon-chevron-left-outline.svg" class="w-3.5 h-3.5 rtl:rotate-180" alt=""></button>
                    <button type="button" @click="slidePaths('next')" class="w-12 h-12 rounded-full border border-slate-200 flex items-center justify-center hover:bg-slate-50 transition-all" aria-controls="home-paths-track" aria-label="{{ __('public.next') }}"><img src="/images/icon-chevron-right-outline.svg" class="w-3.5 h-3.5 rtl:rotate-180" alt=""></button>
                </div>

                <div id="home-paths-track" x-ref="pathsTrack" class="flex h-[390px] w-full snap-x snap-mandatory flex-nowrap gap-6 bg-transparent overflow-x-auto overflow-y-hidden no-scrollbar scroll-smooth overscroll-x-contain px-2 pt-2 pb-5 items-start z-10" role="region" aria-roledescription="{{ __('public.carousel') }}" aria-label="{{ $section->payload->title }}" tabindex="0" @keydown="handleSliderKey($event)">
                    @foreach ($section->payload->items as $item)
                        <article class="reveal-item path-card snap-start w-[292px] h-[380px] shrink-0 relative rounded-[28px] border border-gray-100 bg-white shadow-[0_15px_35px_rgba(20,30,70,0.06)] transition-all duration-300 group overflow-hidden" role="group" aria-roledescription="{{ __('public.slide') }}" aria-label="{{ __('public.slide_position', ['current' => $loop->iteration, 'total' => count($section->payload->items)]) }}">
                            <div class="absolute inset-0 bg-white flex flex-col items-center justify-center p-8 transition-transform duration-500 ease-in-out group-hover:-translate-y-full group-focus-within:-translate-y-full">
                                <div class="absolute top-0 left-0 w-full h-[6px] bg-spu-red"></div>
                                @if (!empty($item['icon']))
                                    <div class="w-20 h-20 rounded-2xl bg-slate-50 text-spu-blue flex items-center justify-center mb-8 shadow-sm">
                                        <span class="block h-10 w-10 bg-current" aria-hidden="true" style="-webkit-mask: url('{{ $item['icon'] }}') center / contain no-repeat; mask: url('{{ $item['icon'] }}') center / contain no-repeat;"></span>
                                    </div>
                                @endif
                                <h3 class="text-[26px] font-bold text-[#1e2652] leading-tight text-center">{{ $item['title'] ?? '' }}</h3>
                            </div>
                            <div class="absolute inset-0 bg-[#1e2652] text-white p-7 flex flex-col translate-y-full transition-transform duration-500 ease-in-out group-hover:translate-y-0 group-focus-within:translate-y-0">
                                <h4 class="text-lg font-bold mb-6 opacity-90 border-b border-white/10 pb-2">{{ $item['title'] ?? '' }}</h4>
                                @if (!empty($item['links']))
                                    <ul class="space-y-4 mb-6 flex-1">
                                        @foreach ($item['links'] as $link)
                                            <li class="flex items-center gap-3 text-[14px] font-medium opacity-85 hover:opacity-100 transition-opacity">
                                                <span class="w-1.5 h-1.5 rounded-full bg-spu-red"></span>
                                                @if (is_array($link) && ! empty($link['url']))
                                                    <a href="{{ $link['url'] }}" class="cursor-pointer underline underline-offset-2 hover:underline hover:text-white transition-colors">{{ $link['text'] ?? ($link['label'] ?? '') }}</a>
                                                @else
                                                    <span class="cursor-default opacity-70">{{ is_array($link) ? ($link['text'] ?? ($link['label'] ?? '')) : $link }}</span>
                                                @endif
                                            </li>
                                        @endforeach
                                    </ul>
                                @endif

                            </div>
                        </article>
                    @endforeach
                </div>
            </div>
        </div>
    </div>
</section>
