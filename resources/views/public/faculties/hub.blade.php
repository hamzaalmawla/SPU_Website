@php
        $content = $page->content;
        $hero = $content['hero'] ?? [];
        $facts = $content['facts'] ?? [];
        $model = $content['model'] ?? [];
        $isAr = $locale === 'ar';
@endphp

    <section class="relative overflow-hidden bg-white font-hacen" dir="{{ $direction }}">
        <div class="absolute inset-0 hidden lg:block">
            <div class="absolute inset-y-0 bg-white {{ $isAr ? 'left-0 w-[65%]' : 'right-0 w-[65%]' }}"></div>
            <div class="absolute inset-y-0 w-[45%] overflow-hidden {{ $isAr ? 'right-0' : 'left-0' }}">
                <img src="{{ $hero['image'] ?? '/images/campus-feature-01.webp' }}" alt="" class="h-full w-full object-cover" aria-hidden="true">
                <div class="absolute inset-0 bg-gradient-to-b from-spu-blue/45 via-spu-blue/30 to-black/45"></div>
            </div>
        </div>

        <div class="absolute inset-0 lg:hidden">
            <img src="{{ $hero['image'] ?? '/images/campus-feature-01.webp' }}" alt="" class="h-full w-full object-cover" aria-hidden="true">
            <div class="absolute inset-0 bg-spu-blue/70"></div>
        </div>

        <div class="container relative z-10 grid min-h-[760px] gap-10 pb-5 pt-22 lg:items-center {{ $isAr ? 'lg:grid-cols-[60%_40%]' : 'lg:grid-cols-[40%_60%]' }}" dir="ltr">
            <div class="flex min-h-[360px] flex-col justify-end pb-12 text-white lg:min-h-[520px] lg:pb-40 {{ $isAr ? 'text-right lg:col-start-2 lg:row-start-1 lg:justify-self-end' : 'text-left' }}" dir="{{ $direction }}">
                <h1 class="max-w-[520px] text-[clamp(2.7rem,5vw,3rem)] font-bold leading-[1.05] text-white drop-shadow-[0_3px_14px_rgba(0,0,0,0.42)]">
                    {{ $hero['title'] ?? '' }}
                </h1>
                <p class="mt-6 max-w-md text-lg leading-8 text-white/90 drop-shadow">
                    {{ $hero['summary'] ?? '' }}
                </p>
                <div class="mt-8 flex flex-wrap gap-4 {{ $isAr ? 'justify-start lg:justify-end' : '' }}">
                    <a href="{{ $hero['applyUrl'] ?? ('/'.$locale.'/admissions/how-to-apply') }}" class="inline-flex h-12 items-center justify-center rounded-[6px] bg-spu-red px-10 text-[12px] font-bold uppercase tracking-[1.2px] text-white shadow-lg shadow-spu-red/30 transition hover:-translate-y-0.5 hover:bg-[#a21d20]">
                        {{ $hero['applyLabel'] ?? ($isAr ? 'قدّم الآن' : 'Apply Now') }}
                    </a>
                    <a href="#faculties-overview" class="inline-flex h-12 items-center justify-center rounded-[6px] border border-white/55 bg-white/10 px-10 text-[13px] font-semibold text-white backdrop-blur transition hover:-translate-y-0.5 hover:bg-white/20">
                        {{ $hero['campusMapLabel'] ?? ($isAr ? 'استكشف خريطة الحرم' : 'Explore Campus Map') }}
                    </a>
                </div>
            </div>

            <div class="relative flex w-full flex-col gap-4 lg:gap-5">
                @foreach ($page->faculties as $faculty)
                    @php($accent = $faculty->accentColor ?: '#202759')
                    <a href="{{ $faculty->url }}" class="group grid min-h-[80px] w-full grid-cols-[auto_1fr_auto] items-center rounded-[10px] border border-white/70 bg-white px-6 py-4 text-spu-blue shadow-[0_12px_34px_rgba(32,39,89,0.10)] transition duration-300 hover:-translate-y-0.5 hover:shadow-[0_18px_44px_rgba(32,39,89,0.16)] sm:min-h-[90px] sm:px-8" style="background: linear-gradient({{ $isAr ? 'to left' : 'to right' }}, #ffffff 0%, #ffffff 48%, {{ $accent }}24 100%);">
                        <div class="flex h-12 w-12 items-center justify-center rounded-full bg-spu-blue text-lg font-bold text-white sm:h-14 sm:w-14 sm:text-xl">{{ $loop->iteration }}</div>
                        <div class="min-w-0 px-5 {{ $isAr ? 'text-right' : 'text-left' }}">
                            <h3 class="truncate text-lg font-bold leading-tight text-spu-blue sm:text-xl lg:text-2xl">{{ $faculty->title }}</h3>
                            <p class="mt-2 line-clamp-1 text-sm leading-6 text-[#46464F] sm:text-[15px]">{{ $faculty->summary }}</p>
                        </div>
                        <span class="text-2xl leading-none text-[#46464F] transition group-hover:translate-x-1 rtl:rotate-180 rtl:group-hover:-translate-x-1">&rarr;</span>
                    </a>
                @endforeach
            </div>
        </div>
    </section>

    <section class="relative z-20 bg-white py-14 font-hacen" dir="{{ $direction }}">
        <div class="container">
            <div class="mx-auto max-w-7xl overflow-hidden rounded-[4px] bg-spu-blue shadow-[0_18px_36px_rgba(9,17,68,0.18)]">
                <div class="grid grid-cols-2 lg:grid-cols-4">
                    @foreach ($facts as $fact)
                        @php($value = (string) ($fact['value'] ?? ''))
                        <div class="relative flex min-h-[136px] flex-col items-center justify-center px-5 py-9 text-center sm:min-h-[168px]">
                            @if (! $loop->first)
                                <div class="absolute top-0 hidden h-full w-px bg-white/15 lg:block {{ $isAr ? 'right-0' : 'left-0' }}" aria-hidden="true"></div>
                            @endif
                            <span class="text-[clamp(2.5rem,6vw,4rem)] font-bold leading-none text-white" dir="ltr">
                                {{ str_ends_with($value, '+') ? substr($value, 0, -1) : $value }}@if (str_ends_with($value, '+'))<span class="text-spu-red">+</span>@endif
                            </span>
                            <span class="mt-4 text-[11px] font-bold uppercase tracking-[0.16em] text-[#708AB5]">{{ $fact['label'] ?? '' }}</span>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    </section>

    <section id="faculties-overview" class="relative overflow-hidden bg-[#f3f8ff] py-20 font-hacen sm:py-24 lg:py-28" dir="{{ $direction }}">
        <div class="container">
            <h2 class="mx-auto max-w-[760px] text-center text-[clamp(2rem,4vw,3.15rem)] font-bold leading-[1.08] tracking-normal text-[#091144]">
                {{ $model['title'] ?? '' }}
            </h2>

            <div class="mx-auto mt-12 grid max-w-5xl grid-cols-1 gap-5 sm:grid-cols-2 lg:grid-cols-6">
                @foreach (($model['cards'] ?? []) as $card)
                    <article class="flex min-h-[178px] flex-col items-center justify-center rounded-[7px] px-8 py-9 text-center shadow-[0_6px_26px_rgba(32,39,89,0.06)] transition duration-300 hover:-translate-y-1 hover:shadow-[0_16px_38px_rgba(32,39,89,0.10)] {{ ! empty($card['featured']) ? 'bg-spu-blue text-white' : 'bg-white text-[#091144]' }} {{ $loop->iteration <= 3 ? 'lg:col-span-2' : 'lg:col-span-3' }} {{ $loop->first ? 'lg:-mt-3' : '' }}">
                        <h3 class="text-[clamp(1.35rem,2.3vw,1.75rem)] font-bold leading-tight {{ ! empty($card['featured']) ? 'text-white' : 'text-[#091144]' }}">{{ $card['title'] ?? '' }}</h3>
                        <p class="mt-5 max-w-md text-[15px] leading-7 sm:text-base {{ ! empty($card['featured']) ? 'text-white/90' : 'text-[#46464F]' }}">{{ $card['summary'] ?? '' }}</p>
                    </article>
                @endforeach
            </div>
        </div>
    </section>
