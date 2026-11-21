{{-- A small picture for one step of the "How to read this plan" guide. The graph is always laid out left to right, so the pictures are too. --}}
@php
    $sp = static fn (string $key): string => __('study_plan.'.$key);
    $miniCard = static fn (string $accent, string $extra = ''): string => 'flex h-[46px] w-[74px] flex-col justify-center gap-1 rounded-lg border border-slate-200 bg-white px-2 shadow-sm '.$accent.' '.$extra;
@endphp
<div class="flex h-28 items-center justify-center gap-3 overflow-hidden rounded-xl bg-slate-50" dir="ltr" aria-hidden="true">
    @switch($stepId)
        @case('card')
            <div class="flex w-[150px] flex-col gap-1.5 rounded-xl border border-slate-200 bg-white p-3 shadow-md sp-card-accent-uni" dir="{{ $direction }}">
                <span class="flex items-center justify-between gap-2"><span class="text-[10px] font-black uppercase tracking-[0.12em] text-spu-red">{{ $sp('art_code') }}</span><span class="rounded bg-amber-100 px-1.5 py-0.5 text-[10px] font-black text-amber-800 ring-1 ring-amber-300">3cr</span></span>
                <span class="text-[12px] font-extrabold text-slate-900">{{ $sp('art_course') }}</span>
            </div>
            @break
        @case('columns')
            @foreach ([1, 2, 3] as $column)
                <div class="flex flex-col items-center gap-1.5">
                    <span class="rounded-md bg-white px-2 py-0.5 text-[10px] font-black text-spu-blue shadow-sm">{{ $sp('art_semester') }} {{ $column }}</span>
                    <span class="h-[20px] w-[58px] rounded-md border border-slate-200 bg-white shadow-sm sp-card-accent-fac"></span>
                    <span class="h-[20px] w-[58px] rounded-md border border-slate-200 bg-white shadow-sm sp-card-accent-fac"></span>
                </div>
                @if ($column < 3)<span class="text-[16px] font-black text-spu-blue/40">→</span>@endif
            @endforeach
            @break
        @case('lines')
            <div class="{{ $miniCard('sp-card-accent-fac') }}"><span class="text-[10px] font-black text-slate-500">{{ $sp('art_first') }}</span><span class="h-1.5 w-10 rounded bg-slate-200"></span></div>
            <svg width="54" height="20" viewBox="0 0 54 20"><path d="M2 10 H46" stroke="var(--accent-color, #202759)" stroke-width="2.5" fill="none"/><path d="M44 4 L52 10 L44 16 z" fill="var(--accent-color, #202759)"/></svg>
            <div class="{{ $miniCard('sp-card-accent-fac') }}"><span class="text-[10px] font-black text-slate-500">{{ $sp('art_next') }}</span><span class="h-1.5 w-10 rounded bg-slate-200"></span></div>
            @break
        @case('highlight')
            <div class="{{ $miniCard('sp-card-accent-fac', 'opacity-30') }}"><span class="h-1.5 w-10 rounded bg-slate-200"></span></div>
            <div class="{{ $miniCard('sp-card-accent-fac', 'scale-110 ring-2 ring-indigo-400') }}"><span class="h-1.5 w-10 rounded bg-slate-300"></span><span class="h-1.5 w-7 rounded bg-slate-200"></span></div>
            <div class="{{ $miniCard('sp-card-accent-fac', 'opacity-30') }}"><span class="h-1.5 w-10 rounded bg-slate-200"></span></div>
            @break
        @case('electives')
            <div class="{{ $miniCard('sp-card-accent-elec') }}"><span class="text-[10px] font-black text-amber-700">{{ $sp('art_elective') }}</span><span class="h-1.5 w-10 rounded bg-slate-200"></span></div>
            <div class="flex h-[46px] w-[96px] flex-col justify-center gap-0.5 rounded-lg px-2 shadow-sm sp-elective-slot"><span class="text-[9px] font-black leading-tight" dir="{{ $direction }}">{{ $sp('art_university_elective') }}</span><span class="text-[10px] font-black">↗</span></div>
            @break
        @case('views')
            <div class="flex flex-col items-center gap-2">
                <span class="inline-flex rounded-md border border-slate-200 bg-white p-0.5 text-[10px] font-bold shadow-sm" dir="{{ $direction }}"><span class="rounded px-2 py-1 text-spu-blue/60">{{ $sp('art_graph') }}</span><span class="rounded bg-spu-blue px-2 py-1 text-white">{{ $sp('art_list') }}</span></span>
                <span class="flex w-[110px] flex-col gap-1">
                    <span class="h-2.5 rounded bg-white shadow-sm ring-1 ring-slate-200"></span>
                    <span class="h-2.5 rounded bg-white shadow-sm ring-1 ring-slate-200"></span>
                    <span class="h-2.5 rounded bg-white shadow-sm ring-1 ring-slate-200"></span>
                </span>
            </div>
            @break
        @default
            <span class="flex h-12 w-12 items-center justify-center rounded-full bg-white text-[20px] font-black text-spu-blue shadow-sm">{{ $stepNumber }}</span>
    @endswitch
</div>
