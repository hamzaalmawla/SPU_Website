@extends('layouts.public')

@include('public.research.partials.styles')

@section('content')
    @php
        $data = $page->data;
        $event = is_array($data['registerEvent'] ?? null) ? $data['registerEvent'] : null;
        $formId = is_array($event) ? (string) ($event['formId'] ?? 'conference-registration') : 'conference-registration';
    @endphp

    <section class="relative flex min-h-[360px] items-end overflow-hidden pt-24 font-hacen">
        <div class="absolute inset-0">
            <img src="{{ $data['hero']['backgroundImage'] ?? '/images/uni-main-place.JPG' }}" alt="{{ $locale === 'ar' ? 'البحث العلمي' : 'Syrian Private University Research' }}" class="h-full w-full object-cover">
            <div class="absolute inset-0 bg-gradient-to-t from-spu-blue/92 via-spu-blue/72 to-spu-blue/30"></div>
        </div>
        <div class="z-[100] mx-auto w-full max-w-[1180px] px-4 pb-10 text-center text-white sm:px-6 lg:px-0">
            <nav class="mb-3 flex items-center justify-center gap-2 text-[11px] font-semibold text-white/74">
                <a href="/{{ $locale }}" class="transition-colors hover:text-white">{{ $locale === 'ar' ? 'الرئيسية' : 'Home' }}</a>
                <img src="/images/icon-chevron-right-outline.svg" alt="" class="h-2 w-2 rtl:rotate-180" aria-hidden="true">
                <a href="/{{ $locale }}/research" class="transition-colors hover:text-white">{{ $locale === 'ar' ? 'البحث' : 'Research' }}</a>
                <img src="/images/icon-chevron-right-outline.svg" alt="" class="h-2 w-2 rtl:rotate-180" aria-hidden="true">
                <a href="/{{ $locale }}/research/conferences" class="transition-colors hover:text-white">{{ $locale === 'ar' ? 'المؤتمرات' : 'Conferences' }}</a>
                <img src="/images/icon-chevron-right-outline.svg" alt="" class="h-2 w-2 rtl:rotate-180" aria-hidden="true">
                <span>{{ $locale === 'ar' ? 'التسجيل' : 'Register' }}</span>
            </nav>
            <h1 class="mx-auto max-w-[900px] text-[26px] font-bold leading-tight md:text-[36px]">{{ $locale === 'ar' ? 'التسجيل' : 'Register' }}</h1>
            @if ($event)
                <p class="mx-auto mt-3 max-w-[800px] text-[13px] font-semibold leading-6 text-white/80">{{ $event['title'] ?? '' }}</p>
            @endif
        </div>
    </section>

    <section class="bg-white py-12 font-hacen md:py-16" @if($event) x-data="dynamicFormShell()" data-form-id="{{ $formId }}" data-locale="{{ $locale }}" @endif>
        <div class="container mx-auto px-6">
            @if (! $event)
                <div class="mx-auto max-w-[600px] py-20 text-center">
                    <div class="mx-auto mb-6 flex h-20 w-20 items-center justify-center rounded-full bg-gray-100">
                        <svg class="h-10 w-10 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.172 16.172a4 4 0 015.656 0M9 10h.01M15 10h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    </div>
                    <h2 class="text-xl font-bold text-spu-blue">{{ $locale === 'ar' ? 'الفعالية غير موجودة' : 'Event Not Found' }}</h2>
                    <p class="mt-2 text-sm text-spu-blue/60">{{ $locale === 'ar' ? 'لم يتم العثور على الفعالية المطلوبة.' : 'The requested event could not be found.' }}</p>
                    <a href="/{{ $locale }}/research/conferences" class="mt-6 inline-flex items-center gap-2 rounded-lg bg-spu-red px-6 py-3 text-sm font-bold text-white transition hover:bg-spu-blue">{{ $locale === 'ar' ? 'العودة للمؤتمرات' : 'Back to Conferences' }}</a>
                </div>
            @else
                <div class="mx-auto max-w-[1200px]">
                    <div class="grid gap-8 lg:grid-cols-[minmax(0,1fr)_400px]">
                        <div>
                            <div class="overflow-hidden rounded-2xl border border-spu-blue/10 bg-white shadow-sm">
                                <div class="relative">
                                    <img src="{{ $event['image'] ?? '/images/uni-main-place.JPG' }}" alt="{{ $event['title'] ?? '' }}" class="h-[260px] w-full object-cover">
                                    <div class="absolute left-0 top-0 bg-spu-red px-3 py-1.5 text-xs font-bold text-white rtl:left-auto rtl:right-0">{{ $event['eventType'] ?? '' }}</div>
                                </div>
                                <div class="p-6">
                                    <h2 class="mb-3 text-2xl font-bold text-spu-blue">{{ $event['title'] ?? '' }}</h2>
                                    <div class="mb-4 flex flex-wrap items-center gap-4 text-sm text-spu-blue/60">
                                        <div class="flex items-center gap-1.5"><img src="/images/icon-calendar-outline.svg" alt="" class="h-4 w-4" aria-hidden="true"><span>{{ $event['date'] ?? '' }}</span></div>
                                        <div class="flex items-center gap-1.5"><img src="/images/icon-map-outline.svg" alt="" class="h-4 w-4" aria-hidden="true"><span>{{ $event['location'] ?? '' }}</span></div>
                                    </div>
                                    <p class="text-sm leading-relaxed text-spu-blue/70">{{ $event['description'] ?? '' }}</p>
                                </div>
                            </div>

                            <div class="mt-6 rounded-2xl border border-spu-blue/10 bg-white p-6">
                                <h3 class="mb-3 flex items-center gap-2 text-base font-bold text-spu-blue">
                                    <svg class="h-5 w-5 text-spu-red" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                    <span>{{ $locale === 'ar' ? 'معلومات التسجيل' : 'Registration Information' }}</span>
                                </h3>
                                <p class="text-sm leading-6 text-spu-blue/70">{{ $locale === 'ar' ? 'يرجى إكمال النموذج بدقة. سيتم إرسال تأكيد التسجيل بعد مراجعة البيانات.' : 'Please complete the form accurately. Registration confirmation will be sent after review.' }}</p>
                            </div>
                        </div>

                        <div class="rounded-2xl bg-gray-50 p-6 sm:p-8 lg:sticky lg:top-24 lg:self-start">
                            <h3 class="mb-6 border-b border-gray-200 pb-4 text-lg font-bold text-gray-900" x-text="$store.dynamicForm.schema ? (document.documentElement.lang === 'ar' ? $store.dynamicForm.schema.titleAr : $store.dynamicForm.schema.titleEn) : ''"></h3>
                            @include('public.forms.dynamic-form')
                        </div>
                    </div>
                </div>
            @endif
        </div>
    </section>
@endsection
