@extends('layouts.public')

@include('public.research.partials.styles')

@section('content')
    @php
        $profileData = $page->data['profile'] ?? $page->item;
        $isAr = $locale === 'ar';
        $coverImage = '/images/about-hero-1.webp';
        
        // Map researcher data to unified array format
        $mappedProfile = [
            'name' => $profileData['name'] ?? '',
            'image' => $profileData['image'] ?? null,
            'position' => $profileData['role'] ?? '',
            'title' => null,
            'facultyName' => $profileData['faculty']['name'] ?? '',
            'departmentName' => $profileData['department'] ?? '',
            'bio' => $profileData['description'] ?? '',
            'biography' => $profileData['biography'] ?? [],
            'officeLocation' => $profileData['office']['fullAddress'] ?? null,
            'email' => $profileData['email'] ?? '',
            'phone' => null,
            'socialLinks' => [
                'scholar' => $profileData['scholarUrl'] ?? '',
                'orcid' => $profileData['orcidUrl'] ?? '',
            ],
            'specializations' => $profileData['expertise'] ?? [],
            'stats' => [
                'publications' => $profileData['researchStats']['publications'] ?? 0,
                'citations' => $profileData['researchStats']['citations'] ?? 0,
            ],
            'cvUrl' => null,
            'quote' => null,
            'educations' => array_map(fn($edu) => [
                'degree' => $edu['degree'] ?? '',
                'institution' => $edu['institution'] ?? '',
                'fieldOfStudy' => null,
                'yearStart' => null,
                'yearEnd' => null,
                'year' => $edu['year'] ?? '',
                'description' => null,
            ], $profileData['education'] ?? []),
            'councilMemberships' => [],
            'publications' => array_map(fn($pub) => [
                'title' => $pub['title'] ?? '',
                'year' => $pub['year'] ?? '',
                'publisher' => $pub['journal'] ?? null,
                'journal' => $pub['journal'] ?? null,
                'excerpt' => null,
                'externalUrl' => $pub['links']['scholar'] ?? '',
                'links' => $pub['links'] ?? []
            ], $profileData['publications'] ?? []),
            'courses' => $profileData['courses'] ?? [],
        ];
    @endphp

    @include('public.partials.profile-view', [
        'profile' => $mappedProfile,
        'coverImage' => $coverImage,
        'isResearchProfile' => true
    ])
@endsection
