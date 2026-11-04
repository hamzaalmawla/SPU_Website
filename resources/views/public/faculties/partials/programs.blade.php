{{-- A faculty's academic programs, grouped by level (bachelor's, master's, ...). Each shows its department and its study plan or page. --}}
@php
    $levels = is_array($page->detail['levels'] ?? null) ? $page->detail['levels'] : [];
    $programs = collect($page->items);
@endphp

<section class="bg-white py-16 font-hacen lg:py-24" aria-label="{{ $isAr ? 'البرامج الأكاديمية' : 'Academic programs' }}">
    <div class="container">
        @if ($levels !== [])
            <div class="mx-auto flex max-w-[1080px] flex-wrap justify-center gap-4">
                @foreach ($levels as $level)
                    <a href="#programs-{{ $level['level'] }}" class="min-w-[180px] rounded-[8px] bg-section p-6 text-center transition hover:-translate-y-0.5">
                        <span class="block text-3xl font-bold" style="color: {{ $accent }}" dir="ltr">{{ $level['count'] }}</span>
                        <span class="mt-2 block text-xs font-bold uppercase tracking-[0.16em] text-spu-blue/55">{{ $level['label'] }}</span>
                    </a>
                @endforeach
            </div>

            @foreach ($levels as $level)
                <div id="programs-{{ $level['level'] }}" class="mx-auto mt-12 max-w-[1080px] scroll-mt-28">
                    <h2 class="text-2xl font-bold text-spu-blue">{{ $level['label'] }}</h2>
                    <div class="mt-5 grid gap-4 md:grid-cols-2">
                        @foreach ($programs->where('level', $level['level']) as $program)
                            <article id="{{ $program['id'] }}" class="flex scroll-mt-28 flex-col rounded-[8px] border border-slate-100 bg-white p-6 shadow-[0_12px_40px_rgba(9,17,68,0.06)]">
                                <div class="flex flex-wrap items-center gap-2">
                                    <span class="rounded-full bg-section px-3 py-1 text-[11px] font-bold text-spu-blue/70">{{ $program['levelLabel'] }}</span>
                                    @if (($program['duration'] ?? '') !== '')
                                        <span class="text-xs font-semibold text-slate-500" dir="auto">{{ $program['duration'] }}</span>
                                    @endif
                                </div>
                                <h3 class="card-title mt-3 text-lg font-bold text-spu-blue" dir="auto">{{ $program['title'] }}</h3>
                                @if (is_array($program['department'] ?? null))
                                    <p class="mt-2 text-sm text-slate-600">
                                        <span class="font-bold text-slate-500">{{ $isAr ? 'القسم:' : 'Department:' }}</span>
                                        <a href="{{ $program['department']['url'] }}" class="font-semibold text-spu-blue hover:underline" dir="auto">{{ $program['department']['title'] }}</a>
                                    </p>
                                @endif
                                @if (($program['summary'] ?? '') !== '')
                                    <x-public.rich-text class="mt-3 text-sm leading-7 text-slate-600" :text="$program['summary']" />
                                @endif
                                @if (! empty($program['studyPlanUrl']) || ! empty($program['pageUrl']))
                                    <div class="mt-auto flex flex-wrap gap-2 pt-5">
                                        @if (! empty($program['studyPlanUrl']))
                                            <a href="{{ $program['studyPlanUrl'] }}" class="inline-flex h-10 items-center justify-center rounded-[6px] px-5 text-sm font-bold text-white transition-all hover:-translate-y-0.5" style="background-color: {{ $accent }}">{{ $isAr ? 'الخطة الدراسية' : 'Study Plan' }}</a>
                                        @endif
                                        @if (! empty($program['pageUrl']))
                                            <a href="{{ $program['pageUrl'] }}" class="inline-flex h-10 items-center justify-center rounded-[6px] border px-5 text-sm font-bold transition-all hover:-translate-y-0.5" style="border-color: {{ $accent }}; color: {{ $accent }}">{{ $isAr ? 'صفحة البرنامج' : 'Program Page' }}</a>
                                        @endif
                                    </div>
                                @endif
                            </article>
                        @endforeach
                    </div>
                </div>
            @endforeach
        @else
            <p class="mx-auto max-w-2xl rounded-xl border border-slate-200 bg-slate-50 px-6 py-12 text-center font-bold text-slate-700">{{ $isAr ? 'لا توجد برامج منشورة حالياً.' : 'No published programs are available yet.' }}</p>
        @endif
    </div>
</section>
