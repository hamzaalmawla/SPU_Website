@php
    $trainingHero = $training['hero'] ?? [];
    $trainingBreadcrumb = $training['breadcrumb'] ?? [];
@endphp

<section class="relative z-20 flex min-h-[330px] items-end pt-28 font-hacen">
    <div class="absolute inset-0 overflow-hidden">
        <img src="{{ $trainingHero['image'] ?? '/images/pharmacy-place.jpg' }}" alt="{{ $localized($trainingHero, 'title') }}" class="h-full w-full object-cover">
        <div class="absolute inset-0 bg-gradient-to-t from-spu-blue via-spu-blue/72 to-spu-blue/22"></div>
    </div>

    <div class="container z-10 pb-10 text-center text-white">
        <nav class="mb-4 flex items-center justify-center gap-2 text-[11px] font-semibold text-white/75" aria-label="{{ $locale === 'ar' ? 'مسار التنقل' : 'Breadcrumb' }}">
            <a href="/{{ $locale }}" class="transition-colors hover:text-white">{{ $localized($trainingBreadcrumb, 'home') ?: ($locale === 'ar' ? 'الرئيسية' : 'Home') }}</a>
            <img src="/images/icon-chevron-right-outline.svg" alt="" class="h-2 w-2 rtl:rotate-180" aria-hidden="true">
            <a href="/{{ $locale }}/faculties" class="transition-colors hover:text-white">{{ $localized($trainingBreadcrumb, 'facilities') ?: ($locale === 'ar' ? 'الكليات' : 'Faculties') }}</a>
            <img src="/images/icon-chevron-right-outline.svg" alt="" class="h-2 w-2 rtl:rotate-180" aria-hidden="true">
            <a href="/{{ $locale }}/faculties/pharmacy" class="transition-colors hover:text-white">{{ $localized($trainingBreadcrumb, 'pharmacy') ?: ($locale === 'ar' ? 'كلية الصيدلة' : 'Faculty of Pharmacy') }}</a>
        </nav>

        <div class="absolute bottom-[-50px] left-1/2 z-50 mx-auto max-w-[620px] -translate-x-1/2 transform bg-spu-blue px-6 py-8 shadow-[0_18px_46px_rgba(0,0,0,0.18)] sm:px-10">
            <p class="text-[11px] font-bold uppercase tracking-[0.18em] text-white/70">{{ $localized($trainingHero, 'eyebrow') }}</p>
            <h1 class="mt-3 text-[28px] font-bold leading-tight md:text-[34px]">{{ $localized($trainingHero, 'title') }}</h1>
            <x-public.rich-text class="mx-auto mt-4 max-w-[520px] text-[13px] font-semibold leading-6 text-white/78" :text="$localized($trainingHero, 'summary')" />
        </div>
    </div>
</section>

@if (($training['introCards'] ?? []) !== [])
<section class="z-10 bg-white pb-12 pt-20 font-hacen">
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
                    <x-public.rich-text class="mt-4 text-[12px] font-semibold leading-6 text-slate-500" :text="$localized($card, 'description')" />
                </article>
            @endforeach
        </div>
    </div>
</section>
@else
{{-- Room for the hero card that hangs below the hero. --}}
<div class="h-24 bg-white" aria-hidden="true"></div>
@endif

@php($programme = $training['programme'] ?? [])
@if (($programme['steps'] ?? []) !== [])
<section class="bg-white pb-14 font-hacen">
    <div class="container">
        <h2 class="text-center text-[24px] font-bold text-spu-blue">{{ $localized($programme, 'title') }}</h2>
        <div class="relative mt-10 grid gap-7 md:grid-cols-4">
            <div class="absolute left-0 right-0 top-6 hidden h-px bg-slate-200 md:block" aria-hidden="true"></div>
            @foreach (($programme['steps'] ?? []) as $step)
                <article class="relative z-10 text-center">
                    <div class="mx-auto flex h-12 w-12 items-center justify-center rounded-full bg-spu-blue text-[14px] font-bold text-white shadow-[0_10px_26px_rgba(32,39,89,0.18)]">{{ $step['number'] ?? $loop->iteration }}</div>
                    <h3 class="mt-4 text-[15px] font-bold text-spu-blue">{{ $localized($step, 'title') }}</h3>
                    <x-public.rich-text class="mx-auto mt-2 max-w-[210px] text-[11px] font-semibold leading-5 text-slate-500" :text="$localized($step, 'description')" />
                </article>
            @endforeach
        </div>
    </div>
</section>
@endif

@php($regulations = $training['regulations'] ?? [])
@php($regulationItems = array_values(array_filter(is_array($regulations['items'] ?? null) ? $regulations['items'] : [], fn (mixed $item): bool => is_array($item) && trim((string) ($item['body'] ?? '')) !== '')))
@php($trainingDocuments = array_values(array_filter(is_array($training['documents'] ?? null) ? $training['documents'] : [], fn (mixed $document): bool => is_array($document) && preg_match('#^(/|https://)#', (string) ($document['url'] ?? '')) === 1)))
@if ($regulationItems !== [] || $trainingDocuments !== [])
<section class="bg-section py-14 font-hacen">
    <div class="container">
        @if (trim((string) ($regulations['title'] ?? '')) !== '')
            <h2 class="text-center text-[24px] font-bold text-spu-blue">{{ $regulations['title'] }}</h2>
        @endif
        <div class="mx-auto mt-8 grid max-w-[960px] gap-5">
            @foreach ($regulationItems as $item)
                <article class="border border-slate-200 bg-white p-6 shadow-[0_8px_26px_rgba(15,23,42,0.04)] md:p-8">
                    @if (trim((string) ($item['title'] ?? '')) !== '')
                        <h3 class="mb-3 text-[17px] font-bold text-spu-blue">{{ $item['title'] }}</h3>
                    @endif
                    <x-public.rich-text class="text-[14px] font-semibold leading-7 text-slate-600" :text="$item['body']" />
                </article>
            @endforeach
        </div>
        @if ($trainingDocuments !== [])
            <div class="mt-10 flex flex-wrap justify-center gap-3">
                @foreach ($trainingDocuments as $document)
                    <a href="{{ $document['url'] }}" target="_blank" rel="noopener" class="inline-flex h-11 items-center gap-2 bg-spu-blue px-6 text-sm font-bold text-white transition-colors hover:bg-spu-red focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-spu-blue">
                        <span aria-hidden="true">⤓</span>{{ trim((string) ($document['label'] ?? '')) !== '' ? $document['label'] : ($locale === 'ar' ? 'تحميل الملف' : 'Download the file') }}
                    </a>
                @endforeach
            </div>
        @endif
    </div>
</section>
@endif

@php($partners = $training['partners'] ?? [])
@if (($partners['items'] ?? []) !== [])
<section class="bg-white pb-16 font-hacen">
    <div class="container">
        <div class="mb-7 flex items-center justify-between gap-4">
            <h2 class="text-[24px] font-bold text-spu-blue">{{ $localized($partners, 'title') }}</h2>
            <a href="/{{ $locale }}/faculties/pharmacy" class="inline-flex items-center gap-2 text-[12px] font-bold text-spu-blue transition-colors hover:text-spu-red">
                <span>{{ $localized($partners, 'cta') }}</span>
                <img src="/images/icon-chevron-right-outline.svg" alt="" class="h-3 w-3 rtl:rotate-180" aria-hidden="true">
            </a>
        </div>
        <div class="grid gap-5 md:grid-cols-2 lg:grid-cols-3">
            @foreach (($partners['items'] ?? []) as $item)
                @php($href = str_starts_with((string) ($item['href'] ?? ''), '/campus-life') ? '/'.$locale.($item['href'] ?? '') : '/'.$locale.'/'.ltrim((string) ($item['href'] ?? 'faculties/pharmacy/labs'), '/'))
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
                        <x-public.rich-text class="mt-3 text-[12px] font-semibold leading-5 text-slate-500" :text="$localized($item, 'description')" />
                    </div>
                </a>
            @endforeach
        </div>
    </div>
</section>
@endif

@php($verifiedFacts = array_values(array_filter($training['facts'] ?? [], fn (mixed $fact): bool => is_array($fact) && ($fact['verified'] ?? false) === true)))
@if ($verifiedFacts !== [])
<section class="mb-12 bg-spu-blue py-9 font-hacen">
    <div class="container">
        <div class="grid gap-5 text-white sm:grid-cols-2 lg:grid-cols-4">
            @foreach ($verifiedFacts as $fact)
                <article class="text-center lg:text-center rtl:lg:text-center">
                    <p class="text-[14px] font-bold">{{ $localized($fact, 'value') }}</p>
                    <p class="mt-2 text-[11px] font-semibold text-white/60">{{ $localized($fact, 'label') }}</p>
                </article>
            @endforeach
        </div>
    </div>
</section>
@endif
