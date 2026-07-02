@extends('layouts.public')

@section('content')
    <div class="bg-white font-hacen text-spu-blue">
        <section id="about-dynamic-section" class="relative overflow-x-visible bg-white py-24 font-hacen">
            <div class="container relative z-10 mt-14">
                <span class="sr-only">{{ $locale === 'ar' ? 'الرئيسية' : 'Home' }}</span>

                <div class="flex flex-col items-start justify-between gap-10 text-start lg:flex-row">
                    <h1 class="mb-5 w-full min-w-0 text-5xl font-black uppercase tracking-tighter text-spu-blue md:text-7xl">
                        @if ($locale === 'ar')
                            عن الجامعة
                        @else
                            ABOUT SP<span class="text-spu-red">U</span>
                        @endif
                    </h1>
                </div>

                <div class="grid grid-cols-1 gap-4 overflow-visible lg:grid-cols-12">
                    <div class="reveal-left relative z-20 flex flex-col justify-center gap-6 overflow-visible lg:col-span-5">
                        <div class="relative mx-auto w-full overflow-visible">
                            <div class="relative z-10 h-[411px] w-full max-w-[448px] overflow-hidden shadow-xl">
                                <img src="{{ $about->imagePrimary }}" class="h-full w-full object-cover" alt="">
                                <div class="overlay absolute inset-0 bg-spu-blue/40"></div>
                            </div>

                            <div class="relative z-10 mt-6 h-[411px] w-full max-w-[448px] overflow-hidden shadow-xl">
                                <img src="{{ $about->imageSecondary }}" class="h-full w-full object-cover" alt="">
                                <div class="overlay absolute inset-0 bg-spu-blue/40"></div>
                            </div>

                            <div class="pointer-events-none absolute top-[67px] left-[20%] right-[20%] z-40 h-[700px] w-[211px] border-[5px] border-white/60" aria-hidden="true"></div>
                        </div>

                        <div dir="{{ $direction }}" class="absolute top-1/2 left-1/2 z-50 hidden h-[276px] w-[min(100%-1rem,24.75rem)] -translate-x-1/2 -translate-y-1/2 items-center justify-center rounded-sm border-[6px] border-white bg-spu-blue p-10 text-center text-white shadow-2xl md:flex lg:left-[calc(100%-6.5rem)] lg:rtl:left-[6.5rem] xl:left-[calc(100%-7.5rem)] xl:rtl:left-[7.5rem]">
                            <p class="text-2xl font-bold leading-tight">{{ $locale === 'ar' ? 'قيم جوهرية تلتزم بها الجامعة' : 'A core value upheld by the university' }}</p>
                        </div>
                    </div>

                    <div class="reveal-right relative z-10 flex flex-col justify-center lg:col-span-7">
                        <div class="mb-12 lg:ps-12">
                            <p class="w-full text-[30px] font-bold leading-[100%] text-[#1B1B1F]">{{ $about->headline }}</p>
                        </div>

                        <div class="space-y-0 lg:ps-12">
                            @foreach ($about->storyItems as $index => $item)
                                <div class="px-10 py-12 transition-all duration-500 {{ $index % 2 === 0 ? 'bg-section' : 'bg-white' }}">
                                    <h2 class="mb-4 flex items-center gap-3 text-2xl font-bold text-spu-blue">{{ $item['title'] ?? '' }}</h2>
                                    <p class="max-w-2xl text-[17px] leading-relaxed text-slate-600">{{ $item['summary'] ?? '' }}</p>
                                </div>
                            @endforeach
                        </div>

                        <div class="grid grid-cols-2 gap-6 bg-section py-4 lg:ps-12">
                            <div class="flex items-start gap-3">
                                <img src="/images/icon-check-circle-outline.svg" class="mt-1 h-5 w-5 text-spu-red" alt="Check">
                                <div>
                                    <h3 class="font-bold text-spu-blue">{{ $locale === 'ar' ? 'اعتماد دولي' : 'Global Accreditation' }}</h3>
                                    <p class="text-sm text-slate-500">{{ $locale === 'ar' ? 'شهادات معترف بها عالمياً' : 'Degrees recognized globally' }}</p>
                                </div>
                            </div>
                            <div class="flex items-start gap-3">
                                <img src="/images/icon-check-circle-outline.svg" class="mt-1 h-5 w-5 text-spu-red" alt="Check">
                                <div>
                                    <h3 class="font-bold text-spu-blue">{{ $locale === 'ar' ? 'جاهزية مهنية' : 'Career Readiness' }}</h3>
                                    <p class="text-sm text-slate-500">{{ $locale === 'ar' ? 'تأهيل كامل لسوق العمل' : 'Prepared for job market' }}</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="relative mt-20 bg-spu-blue text-white shadow-2xl">
                <div class="container">
                    <div class="cms-grid-stats relative z-10 gap-0">
                        @foreach ($about->stats as $stat)
                            <div class="group py-8 text-center">
                                @if (! empty($stat['icon']))
                                    <img src="{{ $stat['icon'] }}" class="mx-auto mb-6 h-8 w-8 brightness-0 invert transition-all duration-300" alt="">
                                @endif
                                <div class="mb-3 flex items-center justify-center text-6xl font-normal tracking-tighter">
                                    <span translate="no">{{ $stat['value'] ?? '' }}</span>
                                </div>
                                <p class="text-xs font-bold uppercase tracking-[0.2em] text-[#799DD6]">{{ $stat['label'] ?? '' }}</p>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>

            <div class="container relative z-10">
                <div class="relative mt-32 overflow-hidden">
                    <div class="flex flex-col items-center justify-between gap-24 lg:flex-row lg:gap-20">
                        <div class="reveal-left relative w-full lg:w-1/2">
                            <div class="relative h-[400px] overflow-hidden rounded-3xl md:h-[500px]">
                                <img src="{{ $about->imagePrimary }}" class="h-full w-full object-cover transition-transform duration-1000" alt="University Life">
                                <div class="absolute inset-0 bg-gradient-to-t from-spu-blue/60 via-transparent to-transparent"></div>
                            </div>
                        </div>

                        <div class="reveal-right w-full text-start lg:w-1/2">
                            <div class="mb-6 inline-flex items-center gap-3">
                                <div class="h-[2px] w-12 bg-spu-red"></div>
                                <img src="/images/icon-award-outline.svg" class="h-5 w-5 text-spu-red" alt="Award">
                                <span class="text-sm font-black uppercase tracking-widest text-spu-red">{{ $about->badge }}</span>
                            </div>

                            <h2 class="mb-8 text-3xl font-bold leading-tight text-spu-blue md:text-4xl">{{ $about->headline }}</h2>
                            <div class="space-y-6">
                                <p class="border-s-4 border-spu-blue/10 ps-6 text-xl leading-relaxed text-slate-600">{{ $about->quote }}</p>
                                <p class="text-lg leading-relaxed text-slate-500">{{ $about->description }}</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        @php
            $aboutNavigationCards = collect($about->subPages);
            $aboutNavigationSlides = $aboutNavigationCards->chunk(4)->values();
            $aboutNavigationShouldSlide = $aboutNavigationCards->count() > 4;
        @endphp

        <section id="about-navigation" class="relative rounded-md bg-section py-20 font-hacen lg:py-28" @if ($aboutNavigationShouldSlide) x-data="aboutNavigation()" data-slide-count="{{ $aboutNavigationSlides->count() }}" @endif>
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

                    <div class="w-full min-w-0 flex-1">
                        @if (! $aboutNavigationShouldSlide)
                            <div class="cms-grid-cards gap-4">
                                @foreach ($aboutNavigationCards as $subPage)
                                    @include('public.about.partials.navigation-card', ['subPage' => $subPage, 'locale' => $locale])
                                @endforeach
                            </div>
                        @else
                            <div>
                                <div class="overflow-hidden">
                                    <div class="flex transition-transform duration-500 ease-out" :style="slideStyle()">
                                        @foreach ($aboutNavigationSlides as $slide)
                                            <div class="cms-grid-cards w-full shrink-0 gap-4">
                                                @foreach ($slide as $subPage)
                                                    @include('public.about.partials.navigation-card', ['subPage' => $subPage, 'locale' => $locale])
                                                @endforeach
                                            </div>
                                        @endforeach
                                    </div>
                                </div>

                                <div class="mt-6 flex items-center justify-between gap-4">
                                    <div class="flex items-center gap-2">
                                        @foreach ($aboutNavigationSlides as $slideIndex => $slide)
                                            <button type="button"
                                                    class="h-1.5 rounded-full transition-all duration-300"
                                                    :class="dotClass({{ $slideIndex }})"
                                                    @click="goToSlide({{ $slideIndex }})"
                                                    aria-label="{{ ($locale === 'ar' ? 'انتقل إلى مجموعة البطاقات ' : 'Go to navigation card group ') . ($slideIndex + 1) }}"></button>
                                        @endforeach
                                    </div>

                                    <div class="flex items-center gap-3">
                                        <button type="button" class="flex h-10 w-10 items-center justify-center rounded-full border border-slate-200 bg-white transition-colors hover:bg-slate-50" @click="previousSlide()" aria-label="{{ __('public.previous') }}">
                                            <img src="/images/icon-chevron-left-outline.svg" alt="" class="h-3.5 w-3.5 rtl:rotate-180" aria-hidden="true">
                                        </button>
                                        <button type="button" class="flex h-10 w-10 items-center justify-center rounded-full border border-slate-200 bg-white transition-colors hover:bg-slate-50" @click="nextSlide()" aria-label="{{ __('public.next') }}">
                                            <img src="/images/icon-chevron-right-outline.svg" alt="" class="h-3.5 w-3.5 rtl:rotate-180" aria-hidden="true">
                                        </button>
                                    </div>
                                </div>
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        </section>
    </div>
@endsection
