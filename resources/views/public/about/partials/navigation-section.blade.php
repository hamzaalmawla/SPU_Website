@php
    $aboutNavigationCards = [
        ['title' => $locale === 'ar' ? 'التاريخ' : 'History', 'link' => '/about/history'],
        ['title' => $locale === 'ar' ? 'القيادة' : 'Leadership', 'link' => '/about/leadership'],
        ['title' => $locale === 'ar' ? 'المديريات' : 'Directorates', 'link' => '/about/directorates'],
        ['title' => $locale === 'ar' ? 'الشراكات' : 'Partnerships', 'link' => '/about/partnerships'],
    ];
@endphp

<section id="about-navigation" class="relative rounded-md bg-section py-20 font-hacen lg:py-28">
    <div class="container relative z-10">
        <div class="flex flex-col items-start gap-14 lg:flex-row lg:items-center lg:gap-20">
            <div class="w-full shrink-0 lg:w-[38%]">
                <h2 class="text-[32px] font-bold leading-tight text-spu-blue md:text-[48px]">
                    @if ($locale === 'ar')
                        استكشف المزيد عن <span class="text-spu-red">الجامعة</span>
                    @else
                        Learn More About <span class="text-spu-red">SPU</span>
                    @endif
                </h2>
            </div>
            <div class="cms-grid-compact w-full min-w-0 flex-1 gap-4">
                @foreach ($aboutNavigationCards as $subPage)
                    @include('public.about.partials.navigation-card', ['subPage' => $subPage, 'locale' => $locale])
                @endforeach
            </div>
        </div>
    </div>
</section>
