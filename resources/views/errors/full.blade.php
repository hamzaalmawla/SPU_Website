{{--
    Branded error page inside the full public shell (header, footer, language
    switch). Rendered ONLY by ErrorPageRenderer, and only for application-level
    statuses (403/404/419/429) where the database and cache are presumed
    healthy. If building the shell throws, the renderer falls back to
    errors/{status}.blade.php instead — this view is never the last resort.

    The body partial and its critical CSS are shared with the standalone shell,
    so both presentations stay identical without depending on the asset build.
--}}
@extends('layouts.public')

@push('head')
    {{-- An error page is not counted as a page view (resources/js/public/siteStats.js). --}}
    <meta name="spu-stats" content="off">
@endpush

@push('styles')
    @include('errors.partials.styles')
@endpush

@section('content')
    @include('errors.partials.body', ['error' => $error, 'shell' => 'full'])
@endsection
