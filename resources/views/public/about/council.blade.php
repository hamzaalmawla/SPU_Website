{{-- One council's page (About › Councils › {council}), from the council template. --}}
@extends('layouts.public')

@section('content')
    <div class="bg-white font-hacen text-spu-blue">
        @include('public.about.partials.hero', [
            'title' => $council->title,
            'summary' => $council->summary,
            'image' => $council->heroImage,
            'parent' => ['label' => $councilsTitle, 'url' => '/'.$locale.'/about/councils'],
        ])

        @include('public.about.partials.council-body', ['council' => $council])

        @include('public.about.partials.navigation-section', ['locale' => $locale])
    </div>
@endsection
