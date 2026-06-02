@if ($navigation->emergencyNotice->isEnabled)
    <a href="{{ $navigation->emergencyNotice->url ?? '#' }}"
       class="mb-2 flex rounded-[10px] bg-spu-red px-4 py-2 text-sm font-bold text-white shadow-[0_12px_30px_rgba(111,22,22,0.22)]">
        @if ($navigation->emergencyNotice->title)
            <span class="sr-only">{{ $navigation->emergencyNotice->title }}</span>
        @endif
        <span>{{ $navigation->emergencyNotice->message ?? $navigation->emergencyNotice->title }}</span>
    </a>
@endif
