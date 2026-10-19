@extends('layouts.public')

@section('content')
    @php
        $form = $page->digitalServices;
    @endphp

    <section class="relative flex min-h-[360px] items-end overflow-hidden pt-24 font-hacen">
        <div class="absolute inset-0">
            <img src="{{ $page->hero['imageHero'] ?? '/images/slider-3.webp' }}" alt="{{ $page->hero['title'] ?? '' }}" class="h-full w-full object-cover">
            <div class="absolute inset-0 bg-gradient-to-t from-spu-blue/92 via-spu-blue/72 to-spu-blue/30"></div>
        </div>
        <div class="z-[100] mx-auto w-full max-w-[1180px] px-4 pb-10 text-center text-white sm:px-6 lg:px-0">
            <nav class="mb-3 flex items-center justify-center gap-2 text-[11px] font-semibold text-white/74">
                <a href="/{{ $locale }}" class="transition-colors hover:text-white">{{ __('public.home') }}</a>
                <img src="/images/icon-chevron-right-outline.svg" alt="" class="h-2 w-2 rtl:rotate-180" aria-hidden="true">
                <a href="/{{ $locale }}/e-services" class="transition-colors hover:text-white">{{ __('public.e_services') }}</a>
                <img src="/images/icon-chevron-right-outline.svg" alt="" class="h-2 w-2 rtl:rotate-180" aria-hidden="true">
                <span>{{ $page->hero['title'] ?? '' }}</span>
            </nav>
            <p class="mb-3 text-xs font-black uppercase tracking-[0.2em] text-white/70">{{ $page->hero['eyebrow'] ?? '' }}</p>
            <h1 class="mx-auto max-w-[900px] text-[26px] font-bold leading-tight md:text-[42px]">{{ $page->hero['title'] ?? '' }}</h1>
            <x-public.rich-text class="mx-auto mt-3 max-w-[800px] text-[13px] font-semibold leading-6 text-white/80" :text="$page->hero['summary'] ?? ''" />
        </div>
    </section>

    <section class="bg-white py-12 font-hacen md:py-16">
        <div class="container mx-auto px-6">
            <div class="mx-auto grid max-w-[1120px] gap-8 lg:grid-cols-[minmax(0,1fr)_360px]">
                <div class="rounded-2xl bg-gray-50 p-6 shadow-sm sm:p-8" x-data="dynamicFormShell()" data-form-id="{{ $form['formId'] ?? 'suggestions-complaints' }}" data-locale="{{ $locale }}" data-preview="{{ !empty($isPreview) ? '1' : '0' }}">
                    <h2 class="mb-6 text-2xl font-black text-spu-blue">{{ $form['formTitle'] ?? '' }}</h2>
                    @include('public.forms.dynamic-form', ['formKey' => $form['formId'] ?? 'suggestions-complaints'])
                </div>

                <aside class="space-y-4 lg:sticky lg:top-24 lg:self-start">
                    <div class="rounded-2xl border border-spu-blue/10 bg-white p-6 shadow-sm">
                        <h2 class="card-title text-xl font-black text-spu-blue" dir="auto">{{ $form['infoTitle'] ?? '' }}</h2>
                        <x-public.rich-text class="mt-3 text-sm font-bold leading-7 text-spu-blue/65" :text="$form['infoBody'] ?? ''" />
                    </div>
                    @foreach (($form['cards'] ?? []) as $card)
                        <article class="rounded-2xl border border-spu-blue/10 bg-white p-6 shadow-sm">
                            <h3 class="text-base font-black text-spu-red">{{ $card['title'] ?? '' }}</h3>
                            <x-public.rich-text class="mt-2 text-sm font-bold leading-6 text-spu-blue/65" :text="$card['body'] ?? ''" />
                        </article>
                    @endforeach
                </aside>
            </div>
        </div>
    </section>
@endsection
