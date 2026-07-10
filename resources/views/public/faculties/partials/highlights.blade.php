@php
    $navigation = isset($navigationItems) ? $navigationItems->keyBy('slug') : collect();
    $orderedSlugs = ['departments', 'study-plan', 'projects', 'alumni', 'valedictorians', 'labs', 'training'];
    $cards = collect($orderedSlugs)->map(fn (string $slug) => $navigation->get($slug))->filter()->values();
    $isSlider = $cards->count() > 4;
@endphp

@if ($cards->isNotEmpty())
    <section id="highlights" class="relative overflow-hidden bg-section py-20 font-hacen lg:py-28">
        <div class="container relative z-10">
            <div class="flex flex-col items-start gap-12 lg:flex-row lg:items-center lg:gap-16">
                <div class="w-full shrink-0 lg:w-[38%]">
                    <h3 class="text-[26px] font-bold leading-tight text-spu-blue md:text-[32px]">
                        {{ $locale === 'ar' ? 'اقسام الكلية' : 'Faculty Highlights' }}
                    </h3>
                    <p class="mt-5 max-w-[480px] text-[16px] leading-8 text-slate-600">
                        {{ $locale === 'ar' ? 'بيئة ديناميكية اكاديمية صممت لدعم تطور الطلاب , التعلم العملي و المستقبل' : 'A dynamic academic environment designed to support student growth, practical learning, and future' }}
                    </p>
                </div>

                @if ($cards->isNotEmpty())
                    <div class="w-full min-w-0 flex-1">
                        @if ($isSlider)
                            <div class="relative">
                                <div class="highlights-slider overflow-x-auto scrollbar-hide pb-2">
                                    <div class="highlights-track flex gap-4 {{ $locale === 'ar' ? 'rtl' : 'ltr' }}">
                                        @foreach ($cards as $item)
                                            <a href="{{ $item->url }}" class="group relative flex h-[118px] flex-shrink-0 w-[280px] flex-col items-center justify-center overflow-hidden rounded-[6px] border border-slate-200/70 bg-white p-6 text-center shadow-sm transition-all duration-300 hover:-translate-y-0.5 hover:bg-spu-blue hover:shadow-md">
                                                <h4 class="text-[17px] font-bold leading-tight text-spu-blue transition-colors duration-300 group-hover:text-white">{{ $item->label }}</h4>
                                                <span class="mt-5 text-[8px] font-bold uppercase tracking-[0.22em] text-white opacity-0 transition-opacity duration-300 group-hover:opacity-100">
                                                    {{ $locale === 'ar' ? 'عرض الصفحة' : 'View Page' }}
                                                </span>
                                            </a>
                                        @endforeach
                                    </div>
                                </div>
                                <div class="highlights-nav mt-6 flex items-center justify-end gap-3">
                                    <button type="button" class="highlights-prev flex h-10 w-10 items-center justify-center rounded-full border border-slate-200 bg-white text-spu-blue transition-all duration-200 hover:border-spu-blue hover:bg-spu-blue hover:text-white" aria-label="{{ $locale === 'ar' ? 'السابق' : 'Previous' }}">
                                        <svg class="h-5 w-5 {{ $locale === 'ar' ? 'rotate-180' : '' }}" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/>
                                        </svg>
                                    </button>
                                    <button type="button" class="highlights-next flex h-10 w-10 items-center justify-center rounded-full border border-spu-red bg-spu-red text-white transition-all duration-200 hover:-translate-y-0.5 hover:bg-[#a21d20]" aria-label="{{ $locale === 'ar' ? 'التالي' : 'Next' }}">
                                        <svg class="h-5 w-5 {{ $locale === 'ar' ? 'rotate-180' : '' }}" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/>
                                        </svg>
                                    </button>
                                </div>
                            </div>
                        @else
                            <div class="grid w-full grid-cols-1 gap-4 sm:grid-cols-2">
                                @foreach ($cards as $item)
                                    <a href="{{ $item->url }}" class="group relative flex h-[118px] flex-col items-center justify-center overflow-hidden rounded-[6px] border border-slate-200/70 bg-white p-6 text-center shadow-sm transition-all duration-300 hover:-translate-y-0.5 hover:bg-spu-blue hover:shadow-md">
                                        <h4 class="text-[17px] font-bold leading-tight text-spu-blue transition-colors duration-300 group-hover:text-white">{{ $item->label }}</h4>
                                        <span class="mt-5 text-[8px] font-bold uppercase tracking-[0.22em] text-white opacity-0 transition-opacity duration-300 group-hover:opacity-100">
                                            {{ $locale === 'ar' ? 'عرض الصفحة' : 'View Page' }}
                                        </span>
                                    </a>
                                @endforeach
                            </div>
                        @endif
                    </div>
                @endif
            </div>
        </div>
    </section>

    @if ($isSlider)
        @push('scripts')
        <script>
            document.addEventListener('DOMContentLoaded', function () {
                var container = document.querySelector('.highlights-slider');
                var track = container ? container.querySelector('.highlights-track') : null;
                var prevBtn = container ? container.parentElement.querySelector('.highlights-prev') : null;
                var nextBtn = container ? container.parentElement.querySelector('.highlights-next') : null;
                if (!container || !track || !prevBtn || !nextBtn) return;

                var scrollStep = 296;

                function updateButtons() {
                    var maxScroll = track.scrollWidth - container.clientWidth;
                    var atStart = container.scrollLeft <= 10;
                    var atEnd = Math.ceil(container.scrollLeft) >= maxScroll - 10;

                    if (atStart) {
                        prevBtn.style.opacity = '0.4';
                        prevBtn.style.pointerEvents = 'none';
                    } else {
                        prevBtn.style.opacity = '1';
                        prevBtn.style.pointerEvents = 'auto';
                    }

                    if (atEnd) {
                        nextBtn.style.opacity = '0.4';
                        nextBtn.style.pointerEvents = 'none';
                    } else {
                        nextBtn.style.opacity = '1';
                        nextBtn.style.pointerEvents = 'auto';
                    }
                }

                nextBtn.addEventListener('click', function () {
                    container.scrollBy({ left: scrollStep, behavior: 'smooth' });
                });

                prevBtn.addEventListener('click', function () {
                    container.scrollBy({ left: -scrollStep, behavior: 'smooth' });
                });

                container.addEventListener('scroll', updateButtons);
                updateButtons();
            });
        </script>
        <style>
            .scrollbar-hide { -ms-overflow-style: none; scrollbar-width: none; }
            .scrollbar-hide::-webkit-scrollbar { display: none; }
        </style>
        @endpush
    @endif
@endif
