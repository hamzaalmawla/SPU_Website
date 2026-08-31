@extends('layouts.public')

@section('content')
    @php
        $isAr = $locale === 'ar';
        $coverImage = '/images/about/hero-img.jpg';
        
        // Normalize educations
        $normalizedEducations = array_map(fn($edu) => [
            'degree' => $edu->degree,
            'institution' => $edu->institution,
            'fieldOfStudy' => $edu->fieldOfStudy,
            'yearStart' => $edu->yearStart,
            'yearEnd' => $edu->yearEnd,
            'description' => $edu->description,
        ], $profile->educations);

        // Normalize publications
        $normalizedPublications = array_map(fn($pub) => [
            'title' => $pub['title'] ?? '',
            'year' => $pub['year'] ?? '',
            'publisher' => $pub['publisher'] ?? null,
            'journal' => $pub['publisher'] ?? null,
            'excerpt' => $pub['excerpt'] ?? null,
            'externalUrl' => $pub['externalUrl'] ?? null,
            'links' => [
                'local' => ! empty($pub['slug']) ? '/'.$locale.'/research/publications/'.$pub['slug'] : '',
                'scholar' => $pub['externalUrl'] ?? '',
            ]
        ], $profile->publications);

        // Map DTO to unified array format
        $mappedProfile = [
            'name' => $profile->name,
            'image' => $profile->image,
            'position' => $profile->position,
            'title' => $profile->title,
            'facultyName' => $profile->facultyName,
            'departmentName' => $profile->departmentName,
            'bio' => $profile->bio,
            'biography' => $profile->bio !== null && $profile->bio !== '' ? [$profile->bio] : [],
            'officeLocation' => $profile->officeLocation,
            'email' => $profile->email,
            'phone' => $profile->phone,
            'socialLinks' => $profile->socialLinks,
            'specializations' => $profile->specializations,
            'stats' => [
                'publications' => count($profile->publications),
                'citations' => 0,
            ],
            'cvUrl' => $profile->cvUrl,
            'quote' => $profile->quote,
            'educations' => $normalizedEducations,
            'councilMemberships' => $profile->councilMemberships,
            'publications' => $normalizedPublications,
            'courses' => [],
        ];
    @endphp

    @include('public.partials.profile-view', [
        'profile' => $mappedProfile,
        'coverImage' => $coverImage,
        'isResearchProfile' => false
    ])
@endsection
