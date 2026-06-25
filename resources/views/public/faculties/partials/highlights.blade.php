@php
    $navigation = isset($navigationItems) ? $navigationItems->keyBy('slug') : collect();
    $orderedSlugs = ['departments', 'study-plan', 'projects', 'alumni', 'valedictorians', 'labs', 'training'];
    $cards = collect($orderedSlugs)->map(fn (string $slug) => $navigation->get($slug))->filter()->values();
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
                    </div>
                @endif
            </div>
        </div>
    </section>
@endif
