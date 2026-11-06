{{-- Hero of a center page or subpage: image, breadcrumb, title and summary. --}}
@php
    $heroTitle = $page->title;
    $heroSummary = (string) ($page->content['hero']['summary'] ?? '');
@endphp
<section class="relative flex {{ $page->subpageSlug === '' ? 'min-h-[520px] lg:min-h-[600px]' : 'min-h-[330px]' }} items-end overflow-hidden pt-28 font-hacen" dir="{{ $direction }}">
    <div class="absolute inset-0">
        <img src="{{ $page->center['heroImage'] }}" alt="" class="faculty-hero__image faculty-hero__image--ready h-full w-full object-cover" aria-hidden="true">
        <div class="absolute inset-0 bg-gradient-to-t from-spu-blue/95 via-spu-blue/65 to-spu-blue/15"></div>
    </div>
    <div class="container relative z-10 pb-14 text-center text-white">
        <nav class="mb-3 flex flex-wrap items-center justify-center gap-2 text-[11px] font-semibold text-white/72" aria-label="Breadcrumb">
            <a href="/{{ $locale }}" class="transition-colors hover:text-white">{{ __('centers.home') }}</a>
            <img src="/images/icon-chevron-right-outline.svg" alt="" class="h-2 w-2 rtl:rotate-180" aria-hidden="true">
            <a href="/{{ $locale }}/centers" class="transition-colors hover:text-white">{{ __('centers.centers') }}</a>
            <img src="/images/icon-chevron-right-outline.svg" alt="" class="h-2 w-2 rtl:rotate-180" aria-hidden="true">
            @if ($page->subpageSlug === '')
                <span class="text-white" aria-current="page">{{ $page->center['name'] }}</span>
            @else
                <a href="{{ $page->center['url'] }}" class="transition-colors hover:text-white">{{ $page->center['name'] }}</a>
                <img src="/images/icon-chevron-right-outline.svg" alt="" class="h-2 w-2 rtl:rotate-180" aria-hidden="true">
                <span class="text-white" aria-current="page">{{ $heroTitle }}</span>
            @endif
        </nav>
        <h1 class="text-[34px] font-bold leading-tight md:text-[48px]">{{ $heroTitle }}</h1>
        @if ($heroSummary !== '')
            <x-public.rich-text class="mx-auto mt-4 max-w-[760px] text-sm font-semibold leading-7 text-white/85 md:text-base" :text="$heroSummary" />
        @endif
    </div>
</section>

{{-- The center's pages, so every subpage reaches the others. --}}
<nav class="border-b border-slate-100 bg-white font-hacen" aria-label="{{ __('centers.section_nav') }}" dir="{{ $direction }}">
    <div class="container">
        <ul class="no-scrollbar flex gap-2 overflow-x-auto py-3">
            @foreach ($page->navigation as $item)
                <li class="shrink-0">
                    <a href="{{ $item['url'] }}" @if ($item['active']) aria-current="page" @endif
                       class="inline-flex rounded-full px-5 py-2.5 text-[13px] font-bold transition-colors focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-spu-blue {{ $item['active'] ? 'bg-spu-blue text-white' : 'text-spu-blue hover:bg-slate-100' }}">{{ $item['label'] }}</a>
                </li>
            @endforeach
        </ul>
    </div>
</nav>
