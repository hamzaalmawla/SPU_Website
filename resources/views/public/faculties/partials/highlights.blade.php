@php
    $navigation = isset($navigationItems) ? $navigationItems->keyBy('slug') : collect();
    $orderedSlugs = ['departments', 'study-plan', 'projects', 'alumni', 'valedictorians', 'labs', 'training'];
    $cards = collect($orderedSlugs)
        ->map(fn (string $slug) => $navigation->get($slug))
        ->filter()
        ->values();
@endphp

@include('public.faculties.partials.navigation-section', [
    'navSectionId' => 'highlights',
    'navHeadingAr' => 'اقسام',
    'navHighlightAr' => 'الكلية',
    'navHeadingEn' => 'Faculty',
    'navHighlightEn' => 'Highlights',
    'navCards' => $cards,
    'locale' => $locale,
])