{{--
    Student help above the study plan: the "How to read this plan" guide (closed until asked
    for) and the course types, each with what it means. Each type also filters the plan.
    Wording comes from the plan payload (FacultyStudyPlanGuideService fills the defaults).
--}}
@php
    $guide = is_array($studyPayload['guide'] ?? null) ? $studyPayload['guide'] : [];
    $guideSteps = collect($guide['steps'] ?? [])->filter(fn ($step) => is_array($step))->values();
    $swatchClasses = [
        'university' => 'sp-card-accent-uni',
        'faculty' => 'sp-card-accent-fac',
        'specialization' => 'sp-card-accent-spec',
        'elective' => 'sp-card-accent-elec',
    ];
    $legendItems = collect($legend)->filter(fn ($item) => is_array($item))->values();
@endphp

<div class="overflow-hidden rounded-2xl border border-slate-200/70 bg-white shadow-[0_4px_24px_rgba(32,39,89,0.06)]" data-study-plan-help>
    @if ($guideSteps->isNotEmpty() && ($guide['title'] ?? '') !== '')
        <details class="group border-b border-slate-100" data-study-plan-guide>
            <summary class="flex cursor-pointer list-none items-center gap-3 px-5 py-4 transition-colors hover:bg-slate-50 focus:outline-none focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-spu-blue [&::-webkit-details-marker]:hidden">
                <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-spu-blue text-[15px] font-black text-white" aria-hidden="true">?</span>
                <span class="text-[15px] font-black text-spu-blue">{{ $guide['title'] }}</span>
                <span class="ms-auto inline-flex items-center gap-1.5 text-[12px] font-bold text-spu-blue/70">
                    <span class="group-open:hidden">{{ __('study_plan.guide_open') }}</span>
                    <span class="hidden group-open:inline">{{ __('study_plan.guide_close') }}</span>
                    <svg class="h-4 w-4 transition-transform group-open:rotate-180" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" /></svg>
                </span>
            </summary>
            <ol class="grid gap-4 px-5 pb-6 pt-2 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($guideSteps as $step)
                    <li class="flex flex-col gap-3 rounded-xl border border-slate-100 p-4">
                        @include('public.faculties.partials.study-plan-guide-art', ['stepId' => (string) ($step['id'] ?? ''), 'stepNumber' => $loop->iteration])
                        <div class="flex items-start gap-2.5">
                            <span class="mt-0.5 flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-spu-red/10 text-[12px] font-black text-spu-red">{{ $loop->iteration }}</span>
                            <div>
                                <h4 class="text-[14px] font-black leading-6 text-spu-blue">{{ $step['title'] ?? '' }}</h4>
                                @if (($step['text'] ?? '') !== '')
                                    <p class="mt-1 text-[13px] font-medium leading-6 text-slate-600">{{ $step['text'] }}</p>
                                @endif
                            </div>
                        </div>
                    </li>
                @endforeach
            </ol>
        </details>
    @endif

    @if ($legendItems->isNotEmpty() || $electiveListUrl !== null)
        <div class="px-5 py-4">
            <div class="mb-3 flex flex-wrap items-baseline gap-x-3 gap-y-1">
                <h3 class="text-[14px] font-black text-spu-blue">{{ $label('courseTypes') ?: __('study_plan.course_types') }}</h3>
                @if (($guide['legendHint'] ?? '') !== '')
                    <p class="text-[12px] font-medium text-slate-500">{{ $guide['legendHint'] }}</p>
                @endif
            </div>
            @php($typeCount = $legendItems->count() + ($electiveListUrl !== null ? 1 : 0))
            <div class="grid gap-2.5 sm:grid-cols-2 {{ [1 => 'xl:grid-cols-1', 2 => 'xl:grid-cols-2', 3 => 'xl:grid-cols-3', 4 => 'xl:grid-cols-4'][$typeCount] ?? 'xl:grid-cols-5' }}">
                @foreach ($legendItems as $item)
                    @php($legendType = (string) ($item['id'] ?? 'faculty'))
                    @php($legendDescription = $text($item, 'description'))
                    <button type="button" data-legend-type="{{ $legendType }}" aria-pressed="false" class="flex items-start gap-3 rounded-xl border border-slate-200 bg-white p-3 text-start transition-all hover:border-slate-300 hover:shadow-md focus:outline-none focus-visible:ring-2 focus-visible:ring-spu-blue aria-pressed:border-spu-blue aria-pressed:bg-spu-blue/[0.04] aria-pressed:shadow-md">
                        <span class="mt-0.5 h-7 w-10 shrink-0 rounded-md border border-slate-200 bg-white shadow-sm {{ $swatchClasses[$legendType] ?? 'sp-card-accent-fac' }}" aria-hidden="true"></span>
                        <span class="min-w-0">
                            <span class="block text-[13px] font-extrabold text-slate-800">{{ $text($item, 'label') }}</span>
                            @if ($legendDescription !== '')
                                <span class="mt-0.5 block text-[12px] font-medium leading-5 text-slate-500">{{ $legendDescription }}</span>
                            @endif
                        </span>
                    </button>
                @endforeach
                @if ($electiveListUrl !== null)
                    <a href="{{ $electiveListUrl }}" target="_blank" rel="noopener" class="sp-elective-slot flex items-start gap-3 rounded-xl p-3 text-start transition-all hover:shadow-md focus:outline-none focus-visible:ring-2 focus-visible:ring-amber-400">
                        <span class="min-w-0">
                            <span class="block text-[13px] font-extrabold">{{ __('centers.elective_slot_legend') }}</span>
                            <span class="mt-0.5 inline-flex items-center gap-1 text-[12px] font-semibold leading-5">{{ __('centers.elective_slot_open') }} {!! $slotIcon !!}</span>
                        </span>
                    </a>
                @endif
            </div>
        </div>
    @endif
</div>
