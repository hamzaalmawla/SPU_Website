@php
    $sectionViews = [
        'hero' => 'public.home.sections.hero',
        'hero_stats' => 'public.home.sections.hero-stats',
        'academic_faculties' => 'public.home.sections.academic-faculties',
        'achievements_highlights' => 'public.home.sections.achievements-highlights',
        'choose_your_path' => 'public.home.sections.choose-your-path',
        'university_news' => 'public.home.sections.university-news',
        'research_studies' => 'public.home.sections.research-studies',
        'events_activities' => 'public.home.sections.events-activities',
        'medical_facilities_services' => 'public.home.sections.medical-facilities-services',
    ];

    $sectionView = $sectionViews[$section->key] ?? null;
@endphp

@if ($sectionView)
    @include($sectionView, ['section' => $section, 'locale' => $locale])
@endif
