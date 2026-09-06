@php
    $navigation = isset($navigationItems) ? $navigationItems->keyBy('slug') : collect();
    $facultySlug = (string) ($faculty['slug'] ?? '');
    $orderedSlugs = ['departments', 'study-plan', 'projects', 'alumni', 'valedictorians', 'labs', 'training', 'members'];
    $cards = collect($orderedSlugs)
        ->map(fn (string $slug) => $navigation->get($slug))
        ->filter()
        ->map(function ($item) use ($facultySlug) {
            $slug = (string) $item->slug;

            // No locale segment: navigation-section strips one and the shared
            // card component prepends "/{locale}" itself. "faculties" is the
            // canonical prefix - "/facilities" still resolves, but only by way
            // of the legacy 301, so linking to it would cost every card a
            // redirect and point the site at its own old URLs.
            return [
                'title' => $item->label,
                'link' => "/faculties/{$facultySlug}/{$slug}",
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