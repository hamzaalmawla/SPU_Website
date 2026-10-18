{{-- The faculty subpages made of text, cases and files: clinical cases and final exam schedules. --}}
@php
    $payload = is_array($subpage['payload'] ?? null) ? $subpage['payload'] : [];
    $isFileUrl = static fn (mixed $url): bool => is_string($url) && preg_match('#^(/|https://)#', $url) === 1;
    $documentsOf = static fn (mixed $documents): array => array_values(array_filter(is_array($documents) ? $documents : [], static fn (mixed $document): bool => is_array($document) && $isFileUrl($document['url'] ?? null)));
    $entries = array_values(array_filter(is_array($payload['entries'] ?? null) ? $payload['entries'] : [], static fn (mixed $entry): bool => is_array($entry) && trim((string) ($entry['title'] ?? '')) !== ''));
    $schedules = array_values(array_filter(is_array($payload['schedules'] ?? null) ? $payload['schedules'] : [], static fn (mixed $schedule): bool => is_array($schedule) && trim((string) ($schedule['title'] ?? '')) !== ''));
    $pageDocuments = $documentsOf($payload['documents'] ?? []);
    $downloadLabel = $isAr ? 'تحميل الملف' : 'Download the file';
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
                $columns = array_filter($examColumns, static fn (string $label, string $key): bool => collect($rows)->contains(static fn (array $row): bool => trim((string) ($row[$key] ?? '')) !== ''), ARRAY_FILTER_USE_BOTH);
            @endphp
            <article class="mx-auto {{ $loop->first ? '' : 'mt-10' }} max-w-[1080px] rounded-[8px] border border-slate-100 bg-white p-6 shadow-[0_12px_40px_rgba(9,17,68,0.06)] md:p-8">
                <h2 class="text-xl font-bold text-spu-blue">{{ $schedule['title'] }}</h2>
                @if (trim((string) ($schedule['note'] ?? '')) !== '')
                    <x-public.rich-text class="mt-3 text-sm leading-7 text-slate-600" :text="$schedule['note']" />
                @endif
                @if ($rows !== [] && $columns !== [])
                    <div class="mt-6 overflow-x-auto rounded-[6px] border border-slate-100">
                        <table class="w-full min-w-[560px] text-start text-sm">
                            <thead class="bg-section text-xs font-bold uppercase tracking-[0.08em] text-spu-blue/70">
                                <tr>
                                    @foreach ($columns as $label)
                                        <th scope="col" class="px-4 py-3 text-start">{{ $label }}</th>
                                    @endforeach
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100 text-slate-700">
                                @foreach ($rows as $row)
                                    <tr>
                                        @foreach (array_keys($columns) as $key)
                                            <td class="px-4 py-3 {{ $key === 'course' ? 'font-bold text-spu-blue' : 'font-semibold' }}" dir="auto">{{ $row[$key] ?? '' }}</td>
                                        @endforeach
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
                @if ($scheduleDocuments !== [])
                    <div class="mt-6 flex flex-wrap gap-3">
                        @foreach ($scheduleDocuments as $document)
                            <a href="{{ $document['url'] }}" target="_blank" rel="noopener" class="inline-flex h-11 items-center gap-2 rounded-[6px] px-5 text-sm font-bold text-white transition-all hover:-translate-y-0.5 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-spu-blue" style="background-color: {{ $accent }}">
                                <span aria-hidden="true">⤓</span>{{ trim((string) ($document['label'] ?? '')) !== '' ? $document['label'] : $downloadLabel }}
                            </a>
                        @endforeach
                    </div>
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
