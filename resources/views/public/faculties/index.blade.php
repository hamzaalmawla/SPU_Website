@extends('layouts.public')

@section('content')
    @if ($page instanceof \App\DTOs\Faculty\FacultyHubPageDTO)
        @include('public.faculties.hub')
    @else
        @include('public.faculties.detail')
    @endif
@endsection
