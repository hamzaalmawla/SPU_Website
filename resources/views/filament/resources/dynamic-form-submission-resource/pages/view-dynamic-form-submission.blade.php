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
        <section class="spu-workspace" aria-labelledby="submission-summary-heading">
            <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                <div class="min-w-0">
                    <p class="spu-workspace__eyebrow">{{ $details->inboxLabel }}</p>
                    <h2 id="submission-summary-heading" class="mt-1 text-xl font-bold text-gray-950 dark:text-white">
                        {{ $details->applicantName ?: __('form_submissions.values.unknown_applicant') }}
                    </h2>
                    @if ($details->applicantEmail)
                        <p class="mt-1 text-sm text-gray-600 dark:text-gray-300">{{ $details->applicantEmail }}</p>
                    @endif
                </div>

                <x-filament::badge :color="$this->statusColor($details->status)">
                    {{ $details->status ? $details->statusLabel : __('form_submissions.values.unknown_status') }}
                </x-filament::badge>
            </div>

            <dl class="mt-5 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                <div>
                    <dt class="text-xs font-semibold text-gray-500 dark:text-gray-400">{{ __('form_submissions.detail.form_type') }}</dt>
                    <dd class="mt-1 text-sm font-medium text-gray-950 dark:text-white">{{ $details->formLabel }}</dd>
                </div>
                <div>
                    <dt class="text-xs font-semibold text-gray-500 dark:text-gray-400">{{ __('form_submissions.detail.reference') }}</dt>
                    <dd class="mt-1 text-sm font-medium text-gray-950 dark:text-white">{{ $details->referenceNumber ?: __('form_submissions.values.not_available') }}</dd>
                </div>
                <div>
                    <dt class="text-xs font-semibold text-gray-500 dark:text-gray-400">{{ __('form_submissions.detail.inbox') }}</dt>
                    <dd class="mt-1 text-sm font-medium text-gray-950 dark:text-white">{{ $details->inboxLabel }}</dd>
                </div>
                <div>
                    <dt class="text-xs font-semibold text-gray-500 dark:text-gray-400">{{ __('form_submissions.detail.assigned_to') }}</dt>
                    <dd class="mt-1 text-sm font-medium text-gray-950 dark:text-white">{{ $details->assignedToName ?: __('form_submissions.values.unassigned') }}</dd>
                </div>
                <div>
                    <dt class="text-xs font-semibold text-gray-500 dark:text-gray-400">{{ __('form_submissions.detail.locale') }}</dt>
                    <dd class="mt-1 text-sm font-medium text-gray-950 dark:text-white">{{ in_array($details->submissionLocale, ['ar', 'en'], true) ? __('form_submissions.locales.' . $details->submissionLocale) : __('form_submissions.values.not_available') }}</dd>
                </div>
                <div>
                    <dt class="text-xs font-semibold text-gray-500 dark:text-gray-400">{{ __('form_submissions.detail.submitted_at') }}</dt>
                    <dd class="mt-1 text-sm font-medium text-gray-950 dark:text-white">{{ $submittedAt }}</dd>
                </div>
            </dl>
        </section>

        @foreach ($submittedSections as $section)
            <section class="fi-section p-5" aria-labelledby="submission-section-{{ $section->key }}">
                <h2 id="submission-section-{{ $section->key }}" class="text-base font-bold text-gray-950 dark:text-white">
                    {{ $section->label }}
                </h2>

                @if ($section->fields === [])
                    <p class="mt-4 text-sm text-gray-500 dark:text-gray-400">{{ __('form_submissions.values.no_submitted_fields') }}</p>
                @else
                    <dl class="mt-4 grid gap-4 md:grid-cols-2">
                        @foreach ($section->fields as $field)
                            <div class="rounded-xl border border-gray-200 p-4 dark:border-gray-700">
                                <dt class="text-xs font-semibold text-gray-500 dark:text-gray-400">{{ $field->label }}</dt>
                                <dd class="mt-2 whitespace-pre-wrap break-words text-sm text-gray-950 dark:text-white">{{ is_array($field->rawValue) || is_object($field->rawValue) ? __('form_submissions.values.structured_hidden') : ($field->displayValue !== '' ? $field->displayValue : __('form_submissions.values.not_provided')) }}</dd>
                                @if ($field->isLegacyValue)
                                    <p class="mt-2 text-xs text-amber-700 dark:text-amber-300">{{ __('form_submissions.detail.legacy_value_warning') }}</p>
                                @endif
                            </div>
                        @endforeach
                    </dl>
                @endif
            </section>
        @endforeach

        @if ($contextSection)
            <section class="fi-section p-5" aria-labelledby="submission-context-heading">
                <h2 id="submission-context-heading" class="text-base font-bold text-gray-950 dark:text-white">{{ $contextSection->label }}</h2>
                <div class="mt-4 rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900 dark:border-amber-800 dark:bg-amber-950/30 dark:text-amber-200">
                    {{ __('form_submissions.detail.context_warning') }}
                    @if ($hasHiddenLegacyContext)
                        {{ __('form_submissions.detail.hidden_context_warning') }}
                    @endif
                </div>

                @if ($safeContextFields->isNotEmpty())
                    <dl class="mt-4 grid gap-4 md:grid-cols-2">
                        @foreach ($safeContextFields as $field)
                            <div class="rounded-xl border border-gray-200 p-4 dark:border-gray-700">
                                <dt class="text-xs font-semibold text-gray-500 dark:text-gray-400">{{ $field->label }}</dt>
                                <dd class="mt-2 break-words text-sm text-gray-950 dark:text-white">{{ $field->displayValue !== '' ? $field->displayValue : __('form_submissions.values.not_provided') }}</dd>
                            </div>
                        @endforeach
                    </dl>
                @endif
            </section>
        @endif

        @if ($details->attachments !== [])
            <section class="fi-section p-5" aria-labelledby="submission-attachments-heading">
                <h2 id="submission-attachments-heading" class="text-base font-bold text-gray-950 dark:text-white">{{ __('form_submissions.sections.attachments') }}</h2>
                <div class="mt-4 grid gap-4 md:grid-cols-2">
                    @foreach ($details->attachments as $attachment)
                        <article class="rounded-xl border border-gray-200 p-4 dark:border-gray-700">
                            <h3 class="text-sm font-bold text-gray-950 dark:text-white">{{ $attachment->label }}</h3>
                            <p class="mt-1 break-all text-sm text-gray-600 dark:text-gray-300">{{ $attachment->originalName }}</p>
                            @if ($attachment->size !== null)
                                <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ __('form_submissions.detail.file_size', ['size' => number_format($attachment->size / 1024, 1)]) }}</p>
                            @endif
                            <div class="mt-4">
                                <x-filament::button
                                    tag="a"
                                    :href="route('admin.form-submissions.attachments.download', ['submission' => $details->id, 'field' => $attachment->field])"
                                    icon="heroicon-o-arrow-down-tray"
                                >
                                    {{ __('form_submissions.actions.download_attachment') }}
                                </x-filament::button>
                            </div>
                        </article>
                    @endforeach
                </div>
            </section>
        @endif

        @if ($technicalSection)
            <details class="fi-section p-5">
                <summary class="cursor-pointer text-sm font-bold text-gray-950 dark:text-white">{{ $technicalSection->label }}</summary>
                <dl class="mt-4 grid gap-4 md:grid-cols-2">
                    @foreach ($technicalSection->fields as $field)
                        <div>
                            <dt class="text-xs font-semibold text-gray-500 dark:text-gray-400">{{ $field->label }}</dt>
                            <dd class="mt-1 break-words text-sm text-gray-950 dark:text-white">{{ $field->displayValue !== '' ? $field->displayValue : __('form_submissions.values.not_available') }}</dd>
                        </div>
                    @endforeach
                </dl>
            </details>
        @endif

        <section class="fi-section p-5" aria-labelledby="submission-notes-heading">
            <h2 id="submission-notes-heading" class="text-base font-bold text-gray-950 dark:text-white">{{ __('form_submissions.detail.internal_notes') }}</h2>
            <p class="mt-3 whitespace-pre-wrap text-sm text-gray-700 dark:text-gray-300">{{ $details->internalNotes ?: __('form_submissions.values.no_internal_notes') }}</p>
        </section>
    </div>
</x-filament-panels::page>
