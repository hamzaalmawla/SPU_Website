@php
    $trainingHero = $training['hero'] ?? [];
    $trainingBreadcrumb = $training['breadcrumb'] ?? [];
@endphp

<section class="relative flex min-h-[330px] items-end overflow-hidden pt-28 font-hacen">
    <div class="absolute inset-0">
        <img src="{{ $trainingHero['image'] ?? '/images/pharmacy-place.jpg' }}" alt="SPU pharmacy training campus" class="h-full w-full object-cover">
        <div class="absolute inset-0 bg-gradient-to-t from-spu-blue via-spu-blue/72 to-spu-blue/22"></div>
    </div>

    <div class="container z-10 pb-10 text-center text-white">
        <nav class="mb-4 flex items-center justify-center gap-2 text-[11px] font-semibold text-white/75" aria-label="Breadcrumb">
            <a href="/{{ $locale }}" class="transition-colors hover:text-white">{{ $localized($trainingBreadcrumb, 'home') }}</a>
            <img src="/images/icon-chevron-right-outline.svg" alt="" class="h-2 w-2 rtl:rotate-180" aria-hidden="true">
            <a href="/{{ $locale }}/facilities" class="transition-colors hover:text-white">{{ $localized($trainingBreadcrumb, 'facilities') }}</a>
            <img src="/images/icon-chevron-right-outline.svg" alt="" class="h-2 w-2 rtl:rotate-180" aria-hidden="true">
            <a href="/{{ $locale }}/facilities/pharmacy" class="transition-colors hover:text-white">{{ $localized($trainingBreadcrumb, 'pharmacy') }}</a>
        </nav>

        <div class="absolute bottom-[-50px] left-1/2 z-50 mx-auto max-w-[620px] -translate-x-1/2 transform bg-spu-blue px-6 py-8 shadow-[0_18px_46px_rgba(0,0,0,0.18)] sm:px-10">
            <p class="text-[11px] font-bold uppercase tracking-[0.18em] text-white/70">{{ $localized($trainingHero, 'eyebrow') }}</p>
            <h1 class="mt-3 text-[28px] font-bold leading-tight md:text-[34px]">{{ $localized($trainingHero, 'title') }}</h1>
            <p class="mx-auto mt-4 max-w-[520px] text-[13px] font-semibold leading-6 text-white/78">{{ $localized($trainingHero, 'summary') }}</p>
        </div>
    </div>
</section>

<section class="z-10 bg-white py-12 font-hacen">
    <div class="container">
        <div class="grid gap-5 md:grid-cols-3">
            @foreach (($training['introCards'] ?? []) as $card)
                <article class="min-h-[150px] border border-slate-200 bg-white p-6 shadow-[0_8px_26px_rgba(15,23,42,0.04)]">
                    <div class="flex items-start justify-between gap-4">
                        <h2 class="text-[15px] font-bold text-spu-blue">{{ $localized($card, 'title') }}</h2>
                        <span class="flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-[#eef4ff]">
                            <img src="{{ $card['icon'] ?? '/images/icons/training.svg' }}" alt="" class="h-4 w-4" aria-hidden="true">
                        </span>
                    </div>
                    <p class="mt-4 text-[12px] font-semibold leading-6 text-slate-500">{{ $localized($card, 'description') }}</p>
                </article>
            @endforeach
        </div>
    </div>
</section>

@php($programme = $training['programme'] ?? [])
<section class="bg-white pb-14 font-hacen">
    <div class="container">
        <h2 class="text-center text-[24px] font-bold text-spu-blue">{{ $localized($programme, 'title') }}</h2>
        <div class="relative mt-10 grid gap-7 md:grid-cols-4">
            <div class="absolute left-0 right-0 top-6 hidden h-px bg-slate-200 md:block" aria-hidden="true"></div>
            @foreach (($programme['steps'] ?? []) as $step)
                <article class="relative z-10 text-center">
                    <div class="mx-auto flex h-12 w-12 items-center justify-center rounded-full bg-spu-blue text-[14px] font-bold text-white shadow-[0_10px_26px_rgba(32,39,89,0.18)]">{{ $step['number'] ?? $loop->iteration }}</div>
                    <h3 class="mt-4 text-[15px] font-bold text-spu-blue">{{ $localized($step, 'title') }}</h3>
                    <p class="mx-auto mt-2 max-w-[210px] text-[11px] font-semibold leading-5 text-slate-500">{{ $localized($step, 'description') }}</p>
                </article>
            @endforeach
        </div>
    </div>
</section>

@php($partners = $training['partners'] ?? [])
<section class="bg-white pb-16 font-hacen">
    <div class="container">
        <div class="mb-7 flex items-center justify-between gap-4">
            <h2 class="text-[24px] font-bold text-spu-blue">{{ $localized($partners, 'title') }}</h2>
            <a href="/{{ $locale }}/facilities/pharmacy" class="inline-flex items-center gap-2 text-[12px] font-bold text-spu-blue transition-colors hover:text-spu-red">
                <span>{{ $localized($partners, 'cta') }}</span>
                <img src="/images/icon-chevron-right-outline.svg" alt="" class="h-3 w-3 rtl:rotate-180" aria-hidden="true">
            </a>
        </div>
        <div class="grid gap-5 md:grid-cols-2 lg:grid-cols-3">
            @foreach (($partners['items'] ?? []) as $item)
                @php($href = str_starts_with((string) ($item['href'] ?? ''), '/campus-life') ? '/'.$locale.($item['href'] ?? '') : '/'.$locale.'/'.ltrim((string) ($item['href'] ?? 'facilities/pharmacy/labs'), '/'))
                <a href="{{ rtrim($href, '/') }}" class="group overflow-hidden border border-slate-200 bg-white shadow-[0_8px_26px_rgba(15,23,42,0.05)] transition-all duration-300 hover:-translate-y-1 hover:shadow-[0_18px_44px_rgba(15,23,42,0.12)]">
                    <div class="h-[155px] overflow-hidden">
                        <img src="{{ $item['image'] ?? '/images/pharmacy-place.jpg' }}" alt="{{ $localized($item, 'title') }}" class="h-full w-full object-cover transition-transform duration-500 group-hover:scale-105">
                    </div>
                    <div class="p-5">
                        <h3 class="text-[15px] font-bold text-spu-blue">{{ $localized($item, 'title') }}</h3>
                        <div class="mt-3 flex items-center gap-2 text-[11px] font-semibold text-slate-500">
                            <span class="h-1.5 w-1.5 rounded-full bg-spu-blue"></span>
                            <span>{{ $localized($item, 'category') }}</span>
                        </div>
                        <p class="mt-3 text-[12px] font-semibold leading-5 text-slate-500">{{ $localized($item, 'description') }}</p>
                    </div>
                </a>
            @endforeach
        </div>
    </div>
</section>

<section class="mb-12 bg-spu-blue py-9 font-hacen">
    <div class="container">
        <div class="grid gap-5 text-white sm:grid-cols-2 lg:grid-cols-4">
            @foreach (($training['facts'] ?? []) as $fact)
                <article class="text-center lg:text-center rtl:lg:text-center">
                    <p class="text-[14px] font-bold">{{ $localized($fact, 'value') }}</p>
                    <p class="mt-2 text-[11px] font-semibold text-white/60">{{ $localized($fact, 'label') }}</p>
                </article>
            @endforeach
        </div>
    </div>
</section>
