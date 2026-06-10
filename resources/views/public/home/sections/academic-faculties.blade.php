<section x-data="facultiesSlider()" class="mt-[120px] bg-white font-hacen relative">
    <div class="container relative">
        <div class="flex flex-col md:flex-row items-center gap-[52px] relative">
            <div class="w-full relative md:w-[322px] h-[435px] text-center bg-[#1e2652] rounded-[24px] flex flex-col justify-center items-start text-white shrink-0 overflow-hidden group z-20 shadow-[0_30px_80px_rgba(17,26,63,0.18)]">
                <div class="absolute inset-0 opacity-[0.15] z-0 animate-slow-pan" style="background-image: radial-gradient(circle, #ffffff 1px, transparent 1px); background-size: 30px 30px;"></div>
                <div class="relative z-10 w-full px-6 flex flex-col h-full justify-center text-right">
                    @php($facultyTitleParts = preg_split('/\s+/u', (string) $section->payload->title, 2))
                    <h2 class="text-[45px] text-center w-full font-bold leading-tight mb-12 transition-all duration-500">
                        <span class="sr-only">{{ $section->payload->title }}</span>
                        <span>{{ $facultyTitleParts[0] ?? $section->payload->title }}</span>
                        @if (! empty($facultyTitleParts[1]))
                            <br>
                            <span class="text-white">{{ $facultyTitleParts[1] }}</span>
                        @endif
                    </h2>
                    @if ($section->payload->sectionAction)
                        <a href="{{ $section->payload->sectionAction->url }}" class="bg-white mx-auto absolute inset-x-0 bottom-10 w-[195px] h-[40px] text-spu-blue justify-center rounded-[10px] font-bold text-[16px] flex items-center gap-2 hover:bg-gray-100 transition-all shadow-lg group/btn overflow-hidden" @if ($section->payload->sectionAction->target) target="{{ $section->payload->sectionAction->target }}" rel="noreferrer" @endif>
                            <span>{{ $section->payload->sectionAction->label }}</span>
                            <img src="/images/icon-arrow-right-outline.svg" class="w-2.5 h-2.5 mt-1 transition-transform group-hover:translate-x-1 rtl:rotate-180" alt="">
                        </a>
                    @endif
                </div>
            </div>

            <div class="flex-1 min-w-0 w-full relative">
                <div class="flex gap-3 absolute -top-20 z-50 rtl:left-0 ltr:right-0">
                    <button @click="slideFaculties('left')" type="button" class="w-12 h-12 rounded-full border border-slate-200 flex items-center justify-center hover:bg-slate-50 transition-all" aria-label="{{ __('public.previous') }}">
                        <img src="/images/icon-chevron-left-outline.svg" class="w-3.5 h-3.5 rtl:rotate-180" alt="">
                    </button>
                    <button @click="slideFaculties('right')" type="button" class="w-12 h-12 rounded-full border border-slate-200 flex items-center justify-center hover:bg-slate-50 transition-all" aria-label="{{ __('public.next') }}">
                        <img src="/images/icon-chevron-right-outline.svg" class="w-3.5 h-3.5 rtl:rotate-180" alt="">
                    </button>
                </div>

                <div x-ref="facultiesTrack" class="flex h-[390px] w-full snap-x snap-mandatory flex-nowrap gap-6 bg-transparent overflow-x-auto overflow-y-hidden no-scrollbar scroll-smooth overscroll-x-contain px-2 pb-5 items-start z-10">
                    @foreach ($section->payload->items as $item)
                        <article @mouseenter="setActiveFaculty({{ $loop->index }})" @mouseleave="clearActiveFaculty()" :class="facultyCardClass({{ $loop->index }})" class="faculty-card snap-start w-[292px] h-[380px] hover:cursor-pointer shrink-0 relative bg-white rounded-[24px] border border-gray-100 shadow-[0_10px_30px_rgba(0,0,0,0.03)] flex flex-col items-center text-center transition-all duration-300 group overflow-hidden">
                            @if (! empty($item['accent']))
                                <div class="absolute top-0 left-0 w-full h-0 group-hover:h-[6px] z-50 transition-all duration-300 ease-in-out" style="background-color: {{ $item['accent'] }};"></div>
                            @endif
                            @php($image = $item['imageUrl'] ?? ($item['image'] ?? null))
                            @if ($image)
                                <div class="relative w-[160px] h-[160px] mt-6 mb-4 flex items-center justify-center">
                                    <img src="{{ $image }}" alt="{{ $item['title'] ?? '' }}" loading="lazy" decoding="async" width="110" height="110" class="relative z-10 w-[110px] h-[110px] object-contain transition-transform duration-500">
                                </div>
                            @endif
                            <div class="px-4 mb-4">
                                <h3 class="text-[20px] font-bold leading-tight transition-colors duration-300 text-gray-800">{{ $item['title'] ?? '' }}</h3>
                            </div>
                            @if (! empty($item['metric']))
                                <div class="px-10 py-2.5 rounded-[8px] text-white font-bold text-[12px] mb-6 shadow-sm" @if (! empty($item['accent'])) style="background-color: {{ $item['accent'] }};" @endif>{{ $item['metric'] }}</div>
                            @endif
                            @if (! empty($item['action']['url']) && ! empty($item['action']['label']))
                                <a href="{{ $item['action']['url'] }}" class="mt-auto mb-6 flex items-center gap-2 text-[13px] font-extrabold transition-all duration-300 group-hover:gap-3" @if (! empty($item['accent'])) style="color: {{ $item['accent'] }};" @endif @if (! empty($item['action']['target'])) target="{{ $item['action']['target'] }}" rel="noreferrer" @endif>
                                    <span>{{ $item['action']['label'] }}</span>
                                    <img src="/images/icon-arrow-right-outline.svg" class="w-2.5 h-2.5 opacity-70 group-hover:opacity-100 transition-all rtl:rotate-180" alt="">
                                </a>
                            @endif
                        </article>
                    @endforeach
                </div>
            </div>
        </div>
    </div>
</section>
