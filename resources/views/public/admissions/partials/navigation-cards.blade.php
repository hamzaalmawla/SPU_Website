<section id="admissions-navigation" class="relative rounded-md bg-section py-20 font-hacen lg:py-28">
    <div class="container relative z-10">
        <div class="flex flex-col items-start gap-14 lg:flex-row lg:items-center lg:gap-20">
            <div class="w-full shrink-0 lg:w-[38%]">
                <h3 class="text-[26px] font-bold leading-tight text-spu-blue md:text-[32px]">{{ $section['navigationTitle'] ?? '' }}</h3>
                <p class="mt-5 max-w-[480px] text-[16px] leading-8 text-slate-600">{{ $section['navigationDesc'] ?? '' }}</p>
            </div>
            <div class="w-full min-w-0 flex-1">
                <div class="cms-grid-compact gap-4">
                    @foreach (($section['navigationCards'] ?? []) as $card)
                        <a href="{{ $card['href'] ?? '#' }}" class="group relative flex h-[118px] flex-col items-center justify-center overflow-hidden rounded-[6px] border border-slate-200/70 bg-white p-6 text-center shadow shadow-md transition-colors duration-300 hover:bg-spu-blue">
                            <h4 class="text-[17px] font-bold leading-tight transition-colors duration-300 group-hover:text-white">{{ $card['title'] ?? '' }}</h4>
                            <span class="mt-5 text-[8px] font-bold uppercase tracking-[0.22em] text-white opacity-0 transition-opacity duration-300 group-hover:opacity-100">{{ $locale === 'ar' ? 'استكشف' : 'Explore' }} -&gt;</span>
                        </a>
                    @endforeach
                </div>
            </div>
        </div>
    </div>
</section>
