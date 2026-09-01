@extends('layouts.public')

@include('public.research.partials.styles')

@section('content')
    @php($data = $page->data)
    @if (! $page->isAvailable)
        @include('public.research.partials.empty-state', ['locale' => $locale, 'direction' => $direction])
    @else

    @include('public.research.partials.page-hero', ['hero' => $data['hero'] ?? [], 'locale' => $locale, 'direction' => $direction])

    <section class="bg-white py-12 font-hacen md:py-16" dir="{{ $direction }}">
        <div class="container mx-auto px-6">
            <div class="mx-auto mb-12 max-w-[1200px]">
                <h2 class="mb-2 text-center text-lg font-bold text-spu-red">{{ $data['upcomingSection']['title'] ?? '' }}</h2>
                <p class="mb-8 text-center text-sm text-spu-blue/60">
                    {{ $data['upcomingSection']['subtitle'] ?? ($locale === 'ar' ? 'سجل واحجز في الفعاليات العلمية القادمة' : 'Register and attend upcoming scientific events') }}
                </p>

                <div class="grid gap-8 md:grid-cols-2">
                    @foreach (($data['upcoming'] ?? []) as $event)
                        <div class="group overflow-hidden rounded-xl border border-spu-blue/10 bg-white shadow-sm transition-all hover:shadow-lg">
                            <div class="relative">
                                <img src="{{ $event['image'] ?? '/images/uni-main-place.JPG' }}" alt="{{ $event['title'] ?? '' }}" class="h-[200px] w-full object-cover">
                                <div class="absolute left-0 top-0 bg-spu-red px-3 py-1.5 text-xs font-bold text-white rtl:left-auto rtl:right-0">{{ $event['eventType'] ?? '' }}</div>
                            </div>
                            <div class="p-6">
                                <div class="mb-3 flex flex-wrap items-center gap-4 text-sm text-spu-blue/60">
                                    <span>{{ $event['date'] ?? '' }}</span>
                                    <span>{{ $event['location'] ?? '' }}</span>
                                </div>
                                <h3 class="mb-2 text-lg font-bold text-spu-blue">{{ $event['title'] ?? '' }}</h3>
                                <p class="mb-5 text-sm text-spu-blue/70">{{ $event['description'] ?? '' }}</p>
                                @if (! empty($event['registrationUrl']) && $event['registrationUrl'] !== '#')
                                    <a href="{{ $event['registrationUrl'] }}" class="inline-flex h-10 items-center justify-center rounded-lg bg-spu-red px-6 text-xs font-bold text-white transition hover:bg-spu-blue">
                                        {{ $event['registrationLabel'] ?? ($locale === 'ar' ? 'سجل الآن' : 'Register Now') }}
                                    </a>
                                @endif
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>

            <div class="mx-auto max-w-[1200px]">
                <h2 class="mb-8 text-center text-lg font-bold text-spu-blue">{{ $data['pastSection']['title'] ?? '' }}</h2>
                <div class="grid gap-6 md:grid-cols-2 lg:grid-cols-3">
                    @foreach (($data['past'] ?? []) as $conf)
                        <div class="group overflow-hidden rounded-xl border border-spu-blue/10 bg-white shadow-sm transition-all hover:shadow-lg">
                            <img src="{{ $conf['image'] ?? '/images/uni-main-place.JPG' }}" alt="{{ $conf['title'] ?? '' }}" class="h-[160px] w-full object-cover opacity-80">
                            <div class="p-5">
                                <div class="mb-2 flex flex-wrap items-center gap-3 text-xs text-spu-blue/60">
                                    <span>{{ $conf['date'] ?? '' }}</span>
                                    @if (! empty($conf['participants']))
                                        <span>{{ $conf['participants'] }}</span>
                                    @endif
                                </div>
                                <h3 class="mb-2 text-base font-bold text-spu-blue">{{ $conf['title'] ?? '' }}</h3>
                                <p class="mb-4 text-sm text-spu-blue/70">{{ $conf['description'] ?? '' }}</p>

                                @if (! empty($conf['hasProceedings']))
                                    <div class="flex items-center justify-between border-t border-spu-blue/10 pt-4">
                                        <span class="text-xs font-semibold text-spu-blue/60">{{ $data['pastSection']['proceedings'] ?? ($locale === 'ar' ? 'الوقائع' : 'Proceedings') }}</span>
                                         @if (! empty($conf['proceedingsUrl']) && $conf['proceedingsUrl'] !== '#')
                                            <a href="{{ $conf['proceedingsUrl'] }}" class="text-xs font-bold text-spu-red transition hover:text-spu-blue">
                                                {{ $conf['proceedingsLabel'] ?? ($locale === 'ar' ? 'تحميل' : 'Download') }}
                                            </a>
                                         @else
                                             <span class="text-xs font-semibold text-slate-400">{{ $locale === 'ar' ? 'غير متاح حالياً' : 'Currently unavailable' }}</span>
                                         @endif
                                    </div>
                                @endif
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    </section>
    @endif
@endsection
