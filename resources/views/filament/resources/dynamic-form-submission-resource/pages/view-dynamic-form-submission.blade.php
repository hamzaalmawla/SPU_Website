@php
    $submittedSections = collect($details->sections)->where('key', 'submitted_fields');
    $contextSection = collect($details->sections)->firstWhere('key', 'context');
    $technicalSection = collect($details->sections)->firstWhere('key', 'technical_request');
    $safeContextFields = $contextSection
        ? collect($contextSection->fields)->whereIn('key', ['source', 'event_title', 'job_title'])
        : collect();
    $hasHiddenLegacyContext = $contextSection
        ? collect($contextSection->fields)->contains(fn ($field): bool => $field->isLegacyField)
        : false;
    $submittedAt = $details->submittedAt !== ''
        ? \Illuminate\Support\Carbon::parse($details->submittedAt)->locale(app()->getLocale())->translatedFormat('j F Y، H:i')
        : __('form_submissions.values.not_available');
@endphp

<x-filament-panels::page
    @class([
        'fi-resource-view-record-page',
        'fi-resource-' . str_replace('/', '-', $this->getResource()::getSlug()),
        'fi-resource-record-' . $record->getKey(),
    ])
>
    <div class="flex flex-col gap-6">
        {{-- Top Summary Workspace Card --}}
        <section class="spu-workspace" aria-labelledby="submission-summary-heading">
            <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                <div class="min-w-0">
                    <p class="spu-workspace__eyebrow">{{ $details->inboxLabel }}</p>
                    <h2 id="submission-summary-heading" class="mt-1 text-2xl font-bold tracking-tight text-gray-950 dark:text-white">
                        {{ $details->applicantName ?: __('form_submissions.values.unknown_applicant') }}
                    </h2>
                    @if ($details->applicantEmail)
                        <p class="mt-1 text-sm font-medium text-gray-600 dark:text-gray-300">{{ $details->applicantEmail }}</p>
                    @endif
                </div>

                <div>
                    <x-filament::badge :color="$this->statusColor($details->status)" size="lg">
                        {{ $details->status ? $details->statusLabel : __('form_submissions.values.unknown_status') }}
                    </x-filament::badge>
                </div>
            </div>

            {{-- Responsive metadata tiles --}}
            <dl class="mt-5 grid grid-cols-2 gap-3 pt-4 border-t border-gray-100 sm:grid-cols-3 xl:grid-cols-6 dark:border-gray-800">
                <div class="rounded-lg border border-gray-200/70 bg-gray-50/70 p-2.5 dark:border-gray-700/70 dark:bg-gray-800/40">
                    <dt class="text-[11px] font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">{{ __('form_submissions.detail.form_type') }}</dt>
                    <dd class="mt-0.5 truncate text-xs font-bold text-gray-950 dark:text-white" title="{{ $details->formLabel }}">{{ $details->formLabel }}</dd>
                </div>
                <div class="rounded-lg border border-gray-200/70 bg-gray-50/70 p-2.5 dark:border-gray-700/70 dark:bg-gray-800/40">
                    <dt class="text-[11px] font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">{{ __('form_submissions.detail.reference') }}</dt>
                    <dd class="mt-0.5 truncate font-mono text-xs font-bold text-gray-950 dark:text-white" dir="ltr" title="{{ $details->referenceNumber }}">{{ $details->referenceNumber ?: __('form_submissions.values.not_available') }}</dd>
                </div>
                <div class="rounded-lg border border-gray-200/70 bg-gray-50/70 p-2.5 dark:border-gray-700/70 dark:bg-gray-800/40">
                    <dt class="text-[11px] font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">{{ __('form_submissions.detail.inbox') }}</dt>
                    <dd class="mt-0.5 truncate text-xs font-bold text-gray-950 dark:text-white" title="{{ $details->inboxLabel }}">{{ $details->inboxLabel }}</dd>
                </div>
                <div class="rounded-lg border border-gray-200/70 bg-gray-50/70 p-2.5 dark:border-gray-700/70 dark:bg-gray-800/40">
                    <dt class="text-[11px] font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">{{ __('form_submissions.detail.assigned_to') }}</dt>
                    <dd class="mt-0.5 truncate text-xs font-bold text-gray-950 dark:text-white">{{ $details->assignedToName ?: __('form_submissions.values.unassigned') }}</dd>
                </div>
                <div class="rounded-lg border border-gray-200/70 bg-gray-50/70 p-2.5 dark:border-gray-700/70 dark:bg-gray-800/40">
                    <dt class="text-[11px] font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">{{ __('form_submissions.detail.locale') }}</dt>
                    <dd class="mt-0.5 text-xs font-bold text-gray-950 dark:text-white">{{ in_array($details->submissionLocale, ['ar', 'en'], true) ? __('form_submissions.locales.' . $details->submissionLocale) : __('form_submissions.values.not_available') }}</dd>
                </div>
                <div class="rounded-lg border border-gray-200/70 bg-gray-50/70 p-2.5 dark:border-gray-700/70 dark:bg-gray-800/40">
                    <dt class="text-[11px] font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">{{ __('form_submissions.detail.submitted_at') }}</dt>
                    <dd class="mt-0.5 truncate text-xs font-bold text-gray-950 dark:text-white" title="{{ $submittedAt }}">{{ $submittedAt }}</dd>
                </div>
            </dl>
        </section>

        {{-- Submitted Fields Sections --}}
        @foreach ($submittedSections as $section)
            <section class="fi-section p-5" aria-labelledby="submission-section-{{ $section->key }}">
                <div class="flex items-center gap-2.5 pb-3.5 mb-4 border-b border-gray-100 dark:border-gray-800">
                    <span class="flex items-center justify-center w-7 h-7 rounded-lg bg-primary-50 text-primary-600 dark:bg-primary-950/60 dark:text-primary-400 shrink-0">
                        <svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z" />
                        </svg>
                    </span>
                    <h2 id="submission-section-{{ $section->key }}" class="text-base sm:text-lg font-bold text-gray-950 dark:text-white">
                        {{ $section->label }}
                    </h2>
                </div>

                @if ($section->fields === [])
                    <p class="text-sm text-gray-500 dark:text-gray-400">{{ __('form_submissions.values.no_submitted_fields') }}</p>
                @else
                    <dl class="grid grid-cols-1 gap-3.5 sm:grid-cols-2 lg:grid-cols-3">
                        @foreach ($section->fields as $field)
                            @php
                                $val = is_array($field->rawValue) || is_object($field->rawValue)
                                    ? __('form_submissions.values.structured_hidden')
                                    : ($field->displayValue !== '' ? $field->displayValue : __('form_submissions.values.not_provided'));
                                $isLong = mb_strlen((string) $val) > 70 || str_contains((string) $val, "\n") || $field->isLegacyValue;
                            @endphp
                            <div @class([
                                'rounded-xl border border-gray-200/80 bg-gray-50/60 p-3.5 transition-colors hover:border-gray-300 dark:border-gray-700/80 dark:bg-gray-800/40 dark:hover:border-gray-600',
                                'sm:col-span-2 lg:col-span-3' => $isLong,
                                'col-span-1' => ! $isLong,
                            ])>
                                <dt class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">{{ $field->label }}</dt>
                                <dd class="mt-1.5 whitespace-pre-wrap break-words text-sm font-semibold text-gray-950 dark:text-white leading-relaxed">{{ $val }}</dd>
                                @if ($field->isLegacyValue)
                                    <div class="mt-2.5 flex items-center gap-1.5 rounded-md bg-amber-50 px-2.5 py-1.5 text-xs font-medium text-amber-800 dark:bg-amber-950/40 dark:text-amber-200">
                                        <svg class="w-3.5 h-3.5 text-amber-600 shrink-0" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                                            <path fill-rule="evenodd" d="M8.485 2.495c.673-1.167 2.357-1.167 3.03 0l6.28 10.875c.673 1.167-.17 2.625-1.516 2.625H3.72c-1.347 0-2.189-1.458-1.515-2.625L8.485 2.495zM10 5a.75.75 0 01.75.75v3.5a.75.75 0 01-1.5 0v-3.5A.75.75 0 0110 5zm0 9a1 1 0 100-2 1 1 0 000 2z" clip-rule="evenodd" />
                                        </svg>
                                        <span>{{ __('form_submissions.detail.legacy_value_warning') }}</span>
                                    </div>
                                @endif
                            </div>
                        @endforeach
                    </dl>
                @endif
            </section>
        @endforeach

        {{-- Request Context Section ("سياق الطلب") --}}
        @if ($contextSection)
            <section class="fi-section p-5" aria-labelledby="submission-context-heading">
                <div class="flex items-center gap-2.5 pb-3.5 mb-4 border-b border-gray-100 dark:border-gray-800">
                    <span class="flex items-center justify-center w-7 h-7 rounded-lg bg-amber-50 text-amber-600 dark:bg-amber-950/60 dark:text-amber-400 shrink-0">
                        <svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M13.19 8.688a4.5 4.5 0 011.242 7.244l-4.5 4.5a4.5 4.5 0 01-6.364-6.364l1.757-1.757m13.35-.622l1.757-1.757a4.5 4.5 0 00-6.364-6.364l-4.5 4.5a4.5 4.5 0 001.242 7.244" />
                        </svg>
                    </span>
                    <h2 id="submission-context-heading" class="text-base sm:text-lg font-bold text-gray-950 dark:text-white">
                        {{ $contextSection->label }}
                    </h2>
                </div>

                {{-- Compact contextual advisory banner --}}
                <div class="flex items-center gap-2 px-3.5 py-2.5 mb-4 rounded-lg bg-amber-50/80 border border-amber-200/80 text-amber-900 text-xs font-medium dark:bg-amber-950/30 dark:border-amber-800/60 dark:text-amber-200">
                    <svg class="w-4 h-4 text-amber-600 shrink-0" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                        <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a.75.75 0 000 1.5h.253a.25.25 0 01.244.304l-.459 2.066A1.75 1.75 0 0010.747 15H11a.75.75 0 000-1.5h-.253a.25.25 0 01-.244-.304l.459-2.066A1.75 1.75 0 009.253 9H9z" clip-rule="evenodd" />
                    </svg>
                    <span>
                        {{ __('form_submissions.detail.context_warning') }}
                        @if ($hasHiddenLegacyContext)
                            {{ __('form_submissions.detail.hidden_context_warning') }}
                        @endif
                    </span>
                </div>

                @if ($safeContextFields->isNotEmpty())
                    <dl class="grid gap-3.5 sm:grid-cols-3">
                        @foreach ($safeContextFields as $field)
                            @php
                                $isPrimary = in_array($field->key, ['event_title', 'job_title'], true);
                            @endphp
                            <div @class([
                                'rounded-xl border p-4 transition-colors',
                                'sm:col-span-2 border-primary-200/90 bg-gradient-to-br from-primary-50/40 to-transparent dark:border-primary-800/60 dark:from-primary-950/20' => $isPrimary,
                                'sm:col-span-1 border-gray-200/80 bg-gray-50/60 dark:border-gray-700/80 dark:bg-gray-800/40' => ! $isPrimary,
                            ])>
                                <dt class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400 flex items-center gap-1.5">
                                    @if ($isPrimary)
                                        <svg class="w-3.5 h-3.5 text-primary-600 dark:text-primary-400 shrink-0" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 012.25-2.25h13.5A2.25 2.25 0 0121 7.5v11.25m-18 0A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75m-18 0v-7.5A2.25 2.25 0 015.25 9h13.5A2.25 2.25 0 0121 11.25v7.5" />
                                        </svg>
                                    @endif
                                    {{ $field->label }}
                                </dt>
                                <dd @class([
                                    'mt-1.5 break-words',
                                    'text-base sm:text-lg font-bold text-gray-950 dark:text-white leading-snug' => $isPrimary,
                                    'text-sm font-semibold text-gray-900 dark:text-gray-100' => ! $isPrimary,
                                ])>
                                    {{ $field->displayValue !== '' ? $field->displayValue : __('form_submissions.values.not_provided') }}
                                </dd>
                            </div>
                        @endforeach
                    </dl>
                @endif
            </section>
        @endif

        {{-- Attachments Section --}}
        @if ($details->attachments !== [])
            <section class="fi-section p-5" aria-labelledby="submission-attachments-heading">
                <div class="flex items-center gap-2.5 pb-3.5 mb-4 border-b border-gray-100 dark:border-gray-800">
                    <span class="flex items-center justify-center w-7 h-7 rounded-lg bg-blue-50 text-blue-600 dark:bg-blue-950/60 dark:text-blue-400 shrink-0">
                        <svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M18.375 12.739l-7.693 7.693a4.5 4.5 0 01-6.364-6.364l10.94-10.94A3 3 0 1119.5 7.372L8.552 18.32m.009-.01l-.01.01m5.699-9.941l-7.81 7.81a1.5 1.5 0 002.112 2.13" />
                        </svg>
                    </span>
                    <h2 id="submission-attachments-heading" class="text-base sm:text-lg font-bold text-gray-950 dark:text-white">
                        {{ __('form_submissions.sections.attachments') }}
                    </h2>
                </div>

                <div class="grid gap-3.5 sm:grid-cols-2 lg:grid-cols-3">
                    @foreach ($details->attachments as $attachment)
                        <article class="flex flex-col justify-between rounded-xl border border-gray-200/80 bg-gray-50/60 p-4 dark:border-gray-700/80 dark:bg-gray-800/40">
                            <div>
                                <h3 class="text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">{{ $attachment->label }}</h3>
                                <p class="mt-1.5 break-all text-sm font-bold text-gray-950 dark:text-white">{{ $attachment->originalName }}</p>
                                @if ($attachment->size !== null)
                                    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ __('form_submissions.detail.file_size', ['size' => number_format($attachment->size / 1024, 1)]) }}</p>
                                @endif
                            </div>
                            <div class="mt-4 pt-3 border-t border-gray-200/60 dark:border-gray-700/60">
                                <x-filament::button
                                    tag="a"
                                    :href="route('admin.form-submissions.attachments.download', ['submission' => $details->id, 'field' => $attachment->field])"
                                    icon="heroicon-o-arrow-down-tray"
                                    size="sm"
                                    color="gray"
                                >
                                    {{ __('form_submissions.actions.download_attachment') }}
                                </x-filament::button>
                            </div>
                        </article>
                    @endforeach
                </div>
            </section>
        @endif

        {{-- Technical Details Accordion --}}
        @if ($technicalSection)
            <details class="fi-section p-4 group">
                <summary class="flex items-center justify-between cursor-pointer list-none text-sm font-bold text-gray-950 dark:text-white select-none">
                    <span class="flex items-center gap-2">
                        <svg class="w-4 h-4 text-gray-500 dark:text-gray-400" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 17.25v1.007a3 3 0 01-.879 2.122L7.5 21h9l-.621-.621A3 3 0 0115 18.257V17.25m6-12V15a2.25 2.25 0 01-2.25 2.25H5.25A2.25 2.25 0 013 15V5.25m18 0A2.25 2.25 0 0018.75 3H5.25A2.25 2.25 0 003 5.25m18 0V12a2.25 2.25 0 01-2.25 2.25H5.25A2.25 2.25 0 013 12V5.25" />
                        </svg>
                        {{ $technicalSection->label }}
                    </span>
                    <svg class="w-4 h-4 text-gray-400 transition-transform duration-200 group-open:rotate-180" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 8.25l-7.5 7.5-7.5-7.5" />
                    </svg>
                </summary>
                <dl class="mt-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                    @foreach ($technicalSection->fields as $field)
                        <div class="rounded-lg border border-gray-200/70 bg-gray-50/70 p-3 dark:border-gray-700/70 dark:bg-gray-800/40">
                            <dt class="text-[11px] font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">{{ $field->label }}</dt>
                            <dd class="mt-1 break-words font-mono text-xs font-semibold text-gray-950 dark:text-white">{{ $field->displayValue !== '' ? $field->displayValue : __('form_submissions.values.not_available') }}</dd>
                        </div>
                    @endforeach
                </dl>
            </details>
        @endif

        {{-- Internal Notes Section --}}
        <section class="fi-section p-5" aria-labelledby="submission-notes-heading">
            <div class="flex items-center gap-2.5 pb-3.5 mb-3 border-b border-gray-100 dark:border-gray-800">
                <span class="flex items-center justify-center w-7 h-7 rounded-lg bg-gray-100 dark:bg-gray-800 text-gray-600 dark:text-gray-300 shrink-0">
                    <svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M16.862 4.487l1.687-1.688a1.875 1.875 0 112.652 2.652L10.582 16.07a4.5 4.5 0 01-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 011.13-1.897l8.932-8.931zm0 0L19.5 7.125M18 14v4.75A2.25 2.25 0 0115.75 21H5.25A2.25 2.25 0 013 18.75V8.25A2.25 2.25 0 015.25 6H10" />
                    </svg>
                </span>
                <h2 id="submission-notes-heading" class="text-base sm:text-lg font-bold text-gray-950 dark:text-white">
                    {{ __('form_submissions.detail.internal_notes') }}
                </h2>
            </div>

            @if ($details->internalNotes)
                <div class="rounded-xl border border-amber-200/80 bg-amber-50/30 p-4 text-sm font-medium text-gray-900 leading-relaxed whitespace-pre-wrap dark:border-amber-800/50 dark:bg-amber-950/20 dark:text-gray-100">
                    {{ $details->internalNotes }}
                </div>
            @else
                <p class="text-sm text-gray-500 dark:text-gray-400 italic">
                    {{ __('form_submissions.values.no_internal_notes') }}
                </p>
            @endif
        </section>
    </div>
</x-filament-panels::page>
