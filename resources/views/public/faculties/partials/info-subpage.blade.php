{{-- The faculty subpages made of text, cases and files: clinical cases and final exam schedules. --}}
@php
    $payload = is_array($subpage['payload'] ?? null) ? $subpage['payload'] : [];
    $isFileUrl = static fn (mixed $url): bool => is_string($url) && preg_match('#^(/|https://)#', $url) === 1;
    $documentsOf = static fn (mixed $documents): array => array_values(array_filter(is_array($documents) ? $documents : [], static fn (mixed $document): bool => is_array($document) && $isFileUrl($document['url'] ?? null)));
    $entries = array_values(array_filter(is_array($payload['entries'] ?? null) ? $payload['entries'] : [], static fn (mixed $entry): bool => is_array($entry) && trim((string) ($entry['title'] ?? '')) !== ''));
    $schedules = array_values(array_filter(is_array($payload['schedules'] ?? null) ? $payload['schedules'] : [], static fn (mixed $schedule): bool => is_array($schedule) && trim((string) ($schedule['title'] ?? '')) !== ''));
    $pageDocuments = $documentsOf($payload['documents'] ?? []);
    $downloadLabel = $isAr ? 'تحميل الملف' : 'Download the file';
    $pdfLabel = $isAr ? 'تحميل البرنامج (PDF)' : 'Download the schedule (PDF)';
    $examColumns = [
        'day' => $isAr ? 'اليوم' : 'Day',
        'date' => $isAr ? 'التاريخ' : 'Date',
        'time' => $isAr ? 'الساعة' : 'Time',
        'course' => $isAr ? 'المقرر' : 'Course',
        'year' => $isAr ? 'السنة' : 'Year',
        'room' => $isAr ? 'القاعة' : 'Hall',
    ];
@endphp

<section class="bg-white py-16 font-hacen lg:py-20">
    <div class="container">
        @if (trim((string) ($subpage['body'] ?? '')) !== '')
            <x-public.rich-text class="mx-auto mb-12 max-w-[860px] text-center text-base font-semibold leading-8 text-slate-600" :text="$subpage['body']" />
        @endif

        @if ($entries !== [])
            <div class="mx-auto grid max-w-[1080px] gap-6 md:grid-cols-2">
                @foreach ($entries as $entry)
                    <article id="case-{{ $loop->iteration }}" class="flex flex-col rounded-[8px] border border-slate-100 bg-white p-6 shadow-[0_12px_40px_rgba(9,17,68,0.06)] md:p-8">
                        <div class="flex items-start gap-4">
                            <span class="text-[28px] font-bold leading-none" style="color: {{ $accent }}" dir="ltr">{{ str_pad((string) $loop->iteration, 2, '0', STR_PAD_LEFT) }}</span>
                            <div class="min-w-0">
                                <h2 class="text-lg font-bold leading-7 text-spu-blue" dir="auto">{{ $entry['title'] }}</h2>
                                @if (trim((string) ($entry['meta'] ?? '')) !== '')
                                    <p class="mt-1 text-xs font-bold text-spu-blue/55" dir="auto">{{ $entry['meta'] }}</p>
                                @endif
                            </div>
                        </div>
                        <x-public.rich-text class="mt-4 flex-1 text-sm leading-7 text-slate-600" :text="$entry['body'] ?? ''" />
                        @if (is_string($entry['videoUrl'] ?? null) && str_starts_with($entry['videoUrl'], 'https://'))
                            <a href="{{ $entry['videoUrl'] }}" target="_blank" rel="noopener" class="mt-6 inline-flex h-11 w-fit items-center gap-2 rounded-[6px] px-5 text-sm font-bold text-white transition-all hover:-translate-y-0.5 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-spu-blue" style="background-color: {{ $accent }}">
                                <span aria-hidden="true">▶</span>{{ $isAr ? 'مشاهدة فيديو الحالة' : 'Watch the case video' }}
                            </a>
                        @endif
                    </article>
                @endforeach
            </div>
        @endif

        @foreach ($schedules as $schedule)
            @php
                $scheduleDocuments = $documentsOf($schedule['documents'] ?? []);
                $rows = array_values(array_filter(is_array($schedule['rows'] ?? null) ? $schedule['rows'] : [], static fn (mixed $row): bool => is_array($row)));
            @endphp
            <article class="mx-auto {{ $loop->first ? '' : 'mt-10' }} max-w-[1080px] rounded-[8px] border border-slate-100 bg-white p-6 shadow-[0_12px_40px_rgba(9,17,68,0.06)] md:p-8">
                <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                    <h2 class="text-xl font-bold text-spu-blue">{{ $schedule['title'] }}</h2>
                    {{-- The schedule's file stays one click away beside the interactive board. --}}
                    @if ($scheduleDocuments !== [])
                        <div class="flex shrink-0 flex-wrap gap-3">
                            @foreach ($scheduleDocuments as $document)
                                @php $isPdf = str_ends_with(strtolower(strtok((string) $document['url'], '?#')), '.pdf'); @endphp
                                <a href="{{ $document['url'] }}" target="_blank" rel="noopener" @if ($isPdf) download @endif class="inline-flex h-11 items-center gap-2 rounded-[6px] px-5 text-sm font-bold text-white transition-all hover:-translate-y-0.5 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-spu-blue" style="background-color: {{ $accent }}">
                                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 3v12m0 0-4-4m4 4 4-4M5 21h14"/></svg>
                                    {{ trim((string) ($document['label'] ?? '')) !== '' ? $document['label'] : ($isPdf ? $pdfLabel : $downloadLabel) }}
                                </a>
                            @endforeach
                        </div>
                    @endif
                </div>
                @if (trim((string) ($schedule['note'] ?? '')) !== '')
                    <x-public.rich-text class="mt-3 text-sm leading-7 text-slate-600" :text="$schedule['note']" />
                @endif
                @if (collect($rows)->contains(static fn (array $row): bool => trim((string) ($row['course'] ?? '')) !== ''))
                    @include('public.faculties.partials.exam-schedule', ['rows' => $rows, 'scheduleKey' => 'exams-'.$loop->iteration])
                @endif
            </article>
        @endforeach

        @if ($pageDocuments !== [])
            <div class="mx-auto mt-12 flex max-w-[1080px] flex-wrap justify-center gap-3">
                @foreach ($pageDocuments as $document)
                    <a href="{{ $document['url'] }}" target="_blank" rel="noopener" class="inline-flex h-11 items-center gap-2 rounded-[6px] bg-spu-blue px-5 text-sm font-bold text-white transition-all hover:-translate-y-0.5 hover:bg-spu-red focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-spu-blue">
                        <span aria-hidden="true">⤓</span>{{ trim((string) ($document['label'] ?? '')) !== '' ? $document['label'] : $downloadLabel }}
                    </a>
                @endforeach
            </div>
        @endif

        @if ($entries === [] && $schedules === [] && $pageDocuments === [] && trim((string) ($subpage['body'] ?? '')) === '')
            <p class="mx-auto max-w-2xl rounded-xl border border-slate-200 bg-slate-50 px-6 py-12 text-center font-bold text-slate-700">
                {{ $page->subpageSlug === 'final-exams'
                    ? ($isAr ? 'سيُنشر برنامج الامتحان النهائي هنا عند صدوره.' : 'The final exam schedule will be published here when it is issued.')
                    : ($isAr ? 'لا يوجد محتوى منشور حالياً.' : 'No content has been published yet.') }}
            </p>
        @endif
    </div>
</section>
