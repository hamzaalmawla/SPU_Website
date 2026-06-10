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

                <div class="staff-grid reveal reveal-up reveal-delay-1">
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

                @if ($people->count() > 9)
                    <nav class="staff-pagination" aria-label="{{ $locale === 'ar' ? 'ترقيم دليل الهيئة الأكاديمية' : 'Staff pagination' }}">
                        <button type="button" class="pag-btn pag-arrow" disabled aria-label="{{ $locale === 'ar' ? 'الصفحة السابقة' : 'Previous page' }}">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="15 18 9 12 15 6"></polyline></svg>
                        </button>
                        <button type="button" class="pag-btn active" aria-current="page">1</button>
                        <button type="button" class="pag-btn">2</button>
                        <button type="button" class="pag-btn pag-arrow" aria-label="{{ $locale === 'ar' ? 'الصفحة التالية' : 'Next page' }}">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="9 18 15 12 9 6"></polyline></svg>
                        </button>
                    </nav>
                @else
                    <div class="pb-20"></div>
                @endif
            </div>
        </section>
    </div>
@endsection
