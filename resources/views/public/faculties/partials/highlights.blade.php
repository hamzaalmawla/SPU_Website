@php
    $navigation = isset($navigationItems) ? $navigationItems->keyBy('slug') : collect();
    $facultySlug = (string) ($faculty['slug'] ?? '');
    $orderedSlugs = ['departments', 'study-plan', 'projects', 'alumni', 'valedictorians', 'labs', 'training', 'members'];
    $cards = collect($orderedSlugs)
        ->map(fn (string $slug) => $navigation->get($slug))
        ->filter()
        ->map(function ($item) use ($facultySlug) {
            $slug = (string) $item->slug;

            return [
                'title' => $item->label,
                'link' => "/facilities/{$facultySlug}/{$slug}",
            ];
        })
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