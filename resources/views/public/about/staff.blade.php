@extends('layouts.public')

@section('content')
    <div class="bg-white font-hacen text-spu-blue">
        @include('public.about.partials.hero', ['title' => $locale === 'ar' ? 'دليل الهيئة الأكاديمية' : 'Academic Staff Directory', 'summary' => $locale === 'ar' ? 'دليل أعضاء الهيئة الأكاديمية في الجامعة السورية الخاصة.' : 'Directory of SPU academic staff members.', 'image' => '/images/about-hero-2.webp'])

        <section class="bg-white font-hacen">
            <div class="container mx-auto px-6">
                <div class="staff-filter-bar">
                    <span class="staff-filter-label">{{ $locale === 'ar' ? 'تصفية حسب الدور' : 'Filter by role' }}</span>
                    <select class="staff-filter-select" aria-label="{{ $locale === 'ar' ? 'تصفية حسب الدور' : 'Filter by role' }}">
                        <option>{{ $locale === 'ar' ? 'جميع أعضاء الهيئة' : 'All Staff Members' }}</option>
                    </select>
                </div>

                <div class="staff-grid pb-20">
                    @foreach ($people as $person)
                        <article class="staff-card reveal reveal-up">
                            <div class="staff-card-media"><img src="{{ $person->image ?? '/images/medicine-dean.jpg' }}" alt="{{ $person->name }}"></div>
                            <div class="staff-card-body">
                                <h2 class="staff-card-name">{{ $person->name }}</h2>
                                <p class="staff-card-role">{{ $person->role }}</p>
                            </div>
                        </article>
                    @endforeach
                </div>
            </div>
        </section>
    </div>
@endsection
