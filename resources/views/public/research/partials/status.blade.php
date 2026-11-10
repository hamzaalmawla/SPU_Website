@php
    $status = $status ?? '';
    $label = match ($status) {
        'ongoing' => $locale === 'ar' ? 'جاري' : 'Ongoing',
        'completed' => $locale === 'ar' ? 'مكتمل' : 'Completed',
        'paused' => $locale === 'ar' ? 'معلق' : 'Paused',
        default => $status,
    };
    $class = match ($status) {
        'ongoing' => 'bg-green-500 text-white',
        'completed' => 'bg-spu-blue text-white',
        default => 'bg-yellow-500 text-white',
    };
@endphp

<span class="rounded-[6px] px-3 py-1 text-[10px] font-bold {{ $class }}">{{ $label }}</span>
