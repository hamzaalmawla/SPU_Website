@extends('public.staff-intake.layout')

@section('content')
    <section class="rounded-2xl bg-white p-8 text-center shadow-sm" role="status">
        <h2 class="mb-4 text-2xl font-bold text-spu-blue">{{ __('staff_intake.public.sent_heading', [], $locale) }}</h2>
        <p class="mb-6 text-slate-600">{{ __('staff_intake.public.sent_body', [], $locale) }}</p>
        @if ($reference)
            <p class="mb-8"><span class="text-sm text-slate-500">{{ __('staff_intake.public.sent_reference', [], $locale) }}:</span>
                <strong class="font-mono text-lg" dir="ltr">{{ $reference }}</strong></p>
        @endif
        <a href="{{ route('public.staff-intake.show', ['locale' => $locale, 'token' => $token]) }}" class="font-bold text-spu-blue underline">{{ __('staff_intake.public.sent_again', [], $locale) }}</a>
    </section>
@endsection
