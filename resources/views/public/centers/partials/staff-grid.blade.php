@if ($members !== [])
    <div class="staff-grid">
        @foreach ($members as $member)
            <a href="{{ $member['profileUrl'] }}" class="staff-card block transition hover:-translate-y-1 hover:shadow-lg focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-spu-blue">
                <div class="staff-card-media">
                    @if (! empty($member['image']))
                        <img src="{{ $member['image'] }}" alt="{{ $member['name'] }}" loading="lazy">
                    @else
                        <div class="flex h-full items-center justify-center bg-slate-100">
                            <img src="/images/icon-user-graduate-outline.svg" alt="" class="h-16 w-16 opacity-30" aria-hidden="true">
                        </div>
                    @endif
                </div>
                <div class="staff-card-body">
                    <h3 class="card-title card-title-center staff-card-name" dir="auto">{{ $member['name'] }}</h3>
                    @if ($member['position'] !== '')
                        <p class="staff-card-role">{{ $member['position'] }}</p>
                    @endif
                </div>
            </a>
        @endforeach
    </div>
@else
    <div class="mx-auto max-w-2xl rounded-xl border border-slate-200 bg-slate-50 px-6 py-12 text-center">
        <p class="font-bold text-slate-700">{{ __('centers.no_staff') }}</p>
    </div>
@endif
