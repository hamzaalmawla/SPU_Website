@extends('layouts.public')

@section('content')
    <section class="relative flex h-[400px] items-center justify-center overflow-hidden font-hacen">
        <div class="absolute inset-0 z-0">
            <img src="{{ $contact->hero['bgImage'] }}" alt="Contact Hero" class="h-full w-full object-cover">
            <div class="absolute inset-0 bg-spu-blue/60 backdrop-blur-[2px]"></div>
        </div>

        <div class="container relative z-10 text-center">
            <h1 class="text-4xl font-bold tracking-wider text-white md:text-5xl lg:text-6xl">{{ $contact->hero['title'] }}</h1>
        </div>
    </section>

    <section class="bg-white py-16 font-hacen lg:py-24">
        <div class="container">
            <div class="grid gap-16 lg:grid-cols-2">
                <div id="contact-form" class="space-y-8 scroll-mt-32">
                    <h2 class="text-3xl font-bold text-spu-blue">{{ $contact->form['title'] }}</h2>
                    
                    @if (session('contact_status'))
                        <div class="rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-sm font-bold text-green-800">
                            {{ session('contact_status') }}
                        </div>
                    @endif

                    <form method="POST" action="{{ route('public.contact.submit', ['locale' => $locale]) }}" class="grid gap-6">
                        @csrf

                        <div class="grid gap-6 md:grid-cols-2">
                            <div class="space-y-2">
                                <label for="contact-name" class="text-sm font-medium text-slate-600">{{ $contact->form['fields']['name']['label'] }}</label>
                                <input id="contact-name" name="name" type="text" value="{{ old('name') }}" required
                                    class="w-full rounded-lg border border-slate-200 bg-white px-4 py-3 outline-none transition-all focus:border-spu-red/50 focus:ring-4 focus:ring-spu-red/5">
                                @error('name')<p class="text-xs font-bold text-spu-red">{{ $message }}</p>@enderror
                            </div>
                            <div class="space-y-2">
                                <label for="contact-email" class="text-sm font-medium text-slate-600">{{ $contact->form['fields']['email']['label'] }}</label>
                                <input id="contact-email" name="email" type="email" value="{{ old('email') }}" required
                                    class="w-full rounded-lg border border-slate-200 bg-white px-4 py-3 outline-none transition-all focus:border-spu-red/50 focus:ring-4 focus:ring-spu-red/5">
                                @error('email')<p class="text-xs font-bold text-spu-red">{{ $message }}</p>@enderror
                            </div>
                        </div>

                        <div class="space-y-2">
                            <label for="contact-subject" class="text-sm font-medium text-slate-600">{{ $contact->form['fields']['subject']['label'] }}</label>
                            <input id="contact-subject" name="subject" type="text" value="{{ old('subject') }}" required
                                class="w-full rounded-lg border border-slate-200 bg-white px-4 py-3 outline-none transition-all focus:border-spu-red/50 focus:ring-4 focus:ring-spu-red/5">
                            @error('subject')<p class="text-xs font-bold text-spu-red">{{ $message }}</p>@enderror
                        </div>

                        <div class="space-y-2">
                            <label for="contact-message" class="text-sm font-medium text-slate-600">{{ $contact->form['fields']['message']['label'] }}</label>
                            <textarea id="contact-message" name="message" rows="6" required
                                class="w-full rounded-lg border border-slate-200 bg-white px-4 py-3 outline-none transition-all focus:border-spu-red/50 focus:ring-4 focus:ring-spu-red/5">{{ old('message') }}</textarea>
                            @error('message')<p class="text-xs font-bold text-spu-red">{{ $message }}</p>@enderror
                        </div>

                        <div>
                            <button type="submit"
                                class="bg-spu-red px-8 py-3 font-bold text-white transition-all hover:bg-spu-red/90 hover:shadow-lg active:scale-95">{{ $contact->form['submit'] }}</button>
                        </div>
                    </form>
                </div>

                <div id="contact-info" class="space-y-4 scroll-mt-32">
                    <span id="admissions-support" class="sr-only"></span>
                    <span id="it-support" class="sr-only"></span>
                    <p class="text-xs font-bold uppercase tracking-widest text-spu-red">{{ $contact->dateLabel }}</p>
                    <h2 class="text-3xl font-bold text-spu-blue">{{ $contact->info['title'] }}</h2>

                    <div class="grid gap-10 sm:grid-cols-2">
                        <div class="flex gap-4">
                            <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-spu-blue text-white">
                                <img src="{{ $contact->info['callUs']['icon'] }}" alt="" class="h-4 w-4" aria-hidden="true">
                            </div>
                            <div class="space-y-1">
                                <p class="text-[10px] font-bold uppercase tracking-widest text-slate-400">{{ $contact->info['callUs']['label'] }}</p>
                                <p class="text-sm font-bold text-spu-blue">{{ $contact->info['callUs']['value'] }}</p>
                            </div>
                        </div>

                        <div class="flex gap-4">
                            <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-spu-blue text-white">
                                <img src="/images/icon-map-outline.svg" alt="" class="h-4 w-4" aria-hidden="true">
                            </div>
                            <div class="space-y-1">
                                <p class="text-[10px] font-bold uppercase tracking-widest text-slate-400">{{ $contact->info['address']['label'] }}</p>
                                <p class="text-[13px] font-medium leading-relaxed text-slate-600">{{ $contact->info['address']['value'] }}</p>
                            </div>
                        </div>

                        <div class="flex gap-4">
                            <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-spu-blue text-white">
                                <img src="/images/icon-envelope-outline.svg" alt="" class="h-4 w-4" aria-hidden="true">
                            </div>
                            <div class="space-y-1">
                                <p class="text-[10px] font-bold uppercase tracking-widest text-slate-400">{{ $contact->info['emailUs']['label'] }}</p>
                                <p class="text-sm font-bold text-spu-blue">{{ $contact->info['emailUs']['value'] }}</p>
                            </div>
                        </div>

                        <div class="flex gap-4">
                            <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-spu-blue text-white">
                                <img src="/images/time.svg" alt="" class="h-4 w-4" aria-hidden="true">
                            </div>
                            <div class="space-y-1">
                                <p class="text-[10px] font-bold uppercase tracking-widest text-slate-400">{{ $contact->info['officeHours']['label'] }}</p>
                                <p class="text-[13px] font-medium leading-relaxed text-slate-600">{{ $contact->info['officeHours']['value'] }}</p>
                            </div>
                        </div>

                        <div class="h-px w-full bg-slate-100"></div>

                        <div class="space-y-4">
                            <p class="text-[10px] font-bold uppercase tracking-widest text-slate-400">{{ $contact->socialsTitle }}</p>
                            <div class="flex gap-3">
                                @foreach ($contact->socials as $social)
                                    <a href="{{ $social['url'] }}" rel="noreferrer" class="flex h-10 w-10 items-center justify-center rounded-full bg-[#1e2756] text-white transition-all hover:bg-spu-red">
                                        <img src="{{ $social['icon'] }}" alt="" class="h-5 w-5 brightness-0 invert transition-opacity" aria-hidden="true">
                                    </a>
                                @endforeach
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <section id="campus-map" class="relative scroll-mt-32 font-hacen">
        <span id="accessibility" class="sr-only"></span>
        <span id="visit-campus" class="sr-only"></span>
        <h1 class="container relative z-10 mt-20 mb-8 flex items-center justify-center text-center text-[44px] font-bold text-spu-blue">
            <span>{{ $contact->location['title'] }}</span>
        </h1>
        <div class="relative h-[400px]">
            <div class="inset-0 relative h-full w-full">
                <div class="absolute top-0 left-0 z-10 h-full w-full bg-spu-blue/20"></div>
                <iframe src="{{ $contact->location['embedUrl'] }}"
                    class="h-full w-full border-0 grayscale-[0.2] transition-all duration-700 hover:grayscale-0"
                    allowfullscreen loading="lazy" referrerpolicy="no-referrer-when-downgrade"></iframe>
            </div>

            <div class="container absolute bottom-[45%] left-[50%] z-20 flex -translate-x-1/2 -translate-y-1/2 items-center justify-center">
                <div>
                    <a href="{{ $contact->location['mapUrl'] }}" target="_blank" rel="noreferrer"
                        class="inline-flex items-center gap-3 bg-spu-red px-10 py-4 text-sm font-bold text-white transition-all hover:bg-spu-red/90 hover:shadow-xl active:scale-95">
                        <img src="/images/icon-map-outline.svg" alt="" class="h-4 w-4" aria-hidden="true">
                        <span>{{ $contact->location['button'] }}</span>
                    </a>
                </div>
            </div>
        </div>
    </section>
@endsection
