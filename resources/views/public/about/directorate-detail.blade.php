@extends('layouts.public')

@section('content')
    <div class="bg-white font-hacen text-spu-blue">
        <section class="detail-hero">
            <div class="container relative z-10 mx-auto px-6 text-center">
                <div class="reveal reveal-up mb-8 inline-block rounded-full bg-white/10 px-6 py-2 text-xs font-black uppercase tracking-widest text-white backdrop-blur-md">{{ $locale === 'ar' ? 'مديرية مركزية' : 'Central Directorate' }}</div>
                <h1 class="reveal reveal-up reveal-delay-1 mb-8 text-4xl font-black leading-tight text-white md:text-6xl">{{ $directorate->title }}</h1>
                <div class="reveal reveal-up reveal-delay-2 mx-auto h-1 w-24 rounded-full bg-white/30"></div>
            </div>
        </section>

        <section class="bg-white py-24">
            <div class="container mx-auto max-w-5xl px-6">
                <div class="grid gap-16 lg:grid-cols-5">
                    <main class="lg:col-span-3">
                        <h2 class="reveal reveal-up mb-8 text-3xl font-black text-spu-blue">{{ $locale === 'ar' ? 'نظرة عامة' : 'Overview' }}</h2>
                        <p class="reveal reveal-up reveal-delay-1 mb-12 text-xl leading-relaxed text-slate-600">{{ $directorate->description }}</p>

                        <h2 class="reveal reveal-up mb-8 text-3xl font-black text-spu-blue">{{ $locale === 'ar' ? 'الخدمات الرئيسية' : 'Key Services' }}</h2>
                        <div class="grid gap-5">
                            @foreach ($directorate->services as $service)
                                <article class="service-item reveal reveal-up">
                                    <div class="icon-circle"><img src="/images/icon-check-circle-outline.svg" alt="" class="h-4 w-4"></div>
                                    <h3 class="text-lg font-bold text-spu-blue">{{ $service }}</h3>
                                </article>
                            @endforeach
                        </div>
                    </main>

                    <aside class="lg:col-span-2">
                        <div class="reveal reveal-right sticky top-32 rounded-[3rem] border border-slate-100 bg-slate-50 p-10">
                            <div class="mb-8 flex h-20 w-20 items-center justify-center rounded-3xl bg-white text-3xl text-spu-red shadow-sm">
                                <img src="{{ $directorate->icon ?? '/images/icon-university-outline.svg' }}" alt="" class="h-6 w-6">
                            </div>
                            <h2 class="mb-6 border-b border-slate-200 pb-4 text-xl font-black text-spu-blue">{{ $locale === 'ar' ? 'معلومات التواصل' : 'Contact Information' }}</h2>
                            <ul class="space-y-6">
                                @if ($directorate->email)<li class="font-bold text-spu-blue">{{ $directorate->email }}</li>@endif
                                @if ($directorate->location)<li class="font-bold text-spu-blue">{{ $directorate->location }}</li>@endif
                            </ul>
                            <a href="/{{ $locale }}/about/directorates" class="mt-12 inline-flex w-full items-center justify-center gap-3 rounded-2xl bg-spu-blue px-8 py-4 font-black text-white transition-all hover:bg-spu-red">{{ $locale === 'ar' ? 'العودة إلى المديريات' : 'Back to Directorates' }}</a>
                        </div>
                    </aside>
                </div>
            </div>
        </section>
    </div>
@endsection
