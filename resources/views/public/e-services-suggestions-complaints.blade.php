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
                <a href="/{{ $locale }}" class="transition-colors hover:text-white">{{ $locale === 'ar' ? 'الرئيسية' : 'Home' }}</a>
                <img src="/images/icon-chevron-right-outline.svg" alt="" class="h-2 w-2 rtl:rotate-180" aria-hidden="true">
                <a href="/{{ $locale }}/e-services" class="transition-colors hover:text-white">{{ $locale === 'ar' ? 'الخدمات الإلكترونية' : 'E-Services' }}</a>
                <img src="/images/icon-chevron-right-outline.svg" alt="" class="h-2 w-2 rtl:rotate-180" aria-hidden="true">
                <span>{{ $page->hero['title'] ?? '' }}</span>
            </nav>
            <p class="mb-3 text-xs font-black uppercase tracking-[0.2em] text-white/70">{{ $page->hero['eyebrow'] ?? '' }}</p>
            <h1 class="mx-auto max-w-[900px] text-[26px] font-bold leading-tight md:text-[42px]">{{ $page->hero['title'] ?? '' }}</h1>
            <p class="mx-auto mt-3 max-w-[800px] text-[13px] font-semibold leading-6 text-white/80">{{ $page->hero['summary'] ?? '' }}</p>
        </div>
    </section>

    <section class="bg-white py-12 font-hacen md:py-16">
        <div class="container mx-auto px-6">
            <div class="mx-auto grid max-w-[1120px] gap-8 lg:grid-cols-[minmax(0,1fr)_360px]">
                <form method="POST" action="/{{ $locale }}/e-services/suggestions-complaints" class="rounded-2xl bg-gray-50 p-6 shadow-sm sm:p-8">
                    @csrf
                    <h2 class="mb-6 text-2xl font-black text-spu-blue">{{ $form['formTitle'] ?? '' }}</h2>

                    @if (session('suggestions_status'))
                        <div class="mb-5 rounded-xl border border-green-200 bg-green-50 px-4 py-3 text-sm font-bold text-green-700">{{ session('suggestions_status') }}</div>
                    @endif

                    <div class="grid gap-5 sm:grid-cols-2">
                        <label class="block"><span class="text-xs font-bold uppercase tracking-wide text-gray-700">{{ $locale === 'ar' ? 'الاسم الكامل' : 'Full Name' }} *</span><input name="name" value="{{ old('name') }}" required class="mt-1 w-full rounded-lg border border-gray-200 bg-white px-4 py-3 text-sm outline-none transition focus:border-spu-red focus:ring-2 focus:ring-spu-red/10"></label>
                        <label class="block"><span class="text-xs font-bold uppercase tracking-wide text-gray-700">{{ $locale === 'ar' ? 'البريد الإلكتروني' : 'Email' }} *</span><input name="email" type="email" value="{{ old('email') }}" required class="mt-1 w-full rounded-lg border border-gray-200 bg-white px-4 py-3 text-sm outline-none transition focus:border-spu-red focus:ring-2 focus:ring-spu-red/10"></label>
                        <label class="block"><span class="text-xs font-bold uppercase tracking-wide text-gray-700">{{ $locale === 'ar' ? 'نوع الطلب' : 'Request Type' }} *</span><select name="request_type" required class="mt-1 w-full rounded-lg border border-gray-200 bg-white px-4 py-3 text-sm outline-none transition focus:border-spu-red focus:ring-2 focus:ring-spu-red/10">@foreach (($form['requestTypes'] ?? []) as $type)<option value="{{ $type['value'] ?? '' }}" @selected(old('request_type') === ($type['value'] ?? ''))>{{ $type['label'] ?? '' }}</option>@endforeach</select></label>
                        <label class="block"><span class="text-xs font-bold uppercase tracking-wide text-gray-700">{{ $locale === 'ar' ? 'الموضوع' : 'Subject' }} *</span><input name="subject" value="{{ old('subject') }}" required class="mt-1 w-full rounded-lg border border-gray-200 bg-white px-4 py-3 text-sm outline-none transition focus:border-spu-red focus:ring-2 focus:ring-spu-red/10"></label>
                        <label class="block sm:col-span-2"><span class="text-xs font-bold uppercase tracking-wide text-gray-700">{{ $locale === 'ar' ? 'الرسالة' : 'Message' }} *</span><textarea name="message" rows="7" required class="mt-1 w-full resize-none rounded-lg border border-gray-200 bg-white px-4 py-3 text-sm outline-none transition focus:border-spu-red focus:ring-2 focus:ring-spu-red/10">{{ old('message') }}</textarea></label>
                    </div>

                    @if ($errors->any())
                        <div class="mt-5 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm font-bold text-red-700">{{ $locale === 'ar' ? 'يرجى مراجعة الحقول المطلوبة.' : 'Please review the required fields.' }}</div>
                    @endif

                    <button type="submit" class="mt-6 w-full rounded-lg bg-spu-red px-8 py-4 font-bold text-white transition-all hover:bg-spu-red/90 hover:shadow-lg">{{ $locale === 'ar' ? 'إرسال الطلب' : 'Submit Request' }}</button>
                </form>

                <aside class="space-y-4 lg:sticky lg:top-24 lg:self-start">
                    <div class="rounded-2xl border border-spu-blue/10 bg-white p-6 shadow-sm">
                        <h2 class="text-xl font-black text-spu-blue">{{ $form['infoTitle'] ?? '' }}</h2>
                        <p class="mt-3 text-sm font-bold leading-7 text-spu-blue/65">{{ $form['infoBody'] ?? '' }}</p>
                    </div>
                    @foreach (($form['cards'] ?? []) as $card)
                        <article class="rounded-2xl border border-spu-blue/10 bg-white p-6 shadow-sm">
                            <h3 class="text-base font-black text-spu-red">{{ $card['title'] ?? '' }}</h3>
                            <p class="mt-2 text-sm font-bold leading-6 text-spu-blue/65">{{ $card['body'] ?? '' }}</p>
                        </article>
                    @endforeach
                </aside>
            </div>
        </div>
    </section>
@endsection
