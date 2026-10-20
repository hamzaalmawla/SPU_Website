@php
    $memberUrl = fn (int $id): string => \App\Filament\Resources\FacultyMemberResource::getUrl('edit', ['record' => $id]);
@endphp

<x-filament-panels::page>
    <div class="flex flex-col gap-6">
        <section class="spu-workspace" aria-labelledby="intake-summary-heading">
            <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                <div class="min-w-0">
                    <p class="spu-workspace__eyebrow">{{ $details->facultyName }} · {{ __('staff_intake.admin.types.'.$details->type) }}</p>
                    <h2 id="intake-summary-heading" class="mt-1 text-xl font-bold text-gray-950 dark:text-white">{{ $details->submitterName }}</h2>
                    <p class="mt-1 text-sm text-gray-600 dark:text-gray-300">{{ __('staff_intake.admin.columns.reference') }}: <span dir="ltr">{{ $details->reference }}</span> · {{ $details->submittedAt }}</p>
                </div>
                <x-filament::badge :color="\App\Filament\Resources\StaffIntakeRequestResource::statusColor($details->status)">
                    {{ __('staff_intake.admin.request_status.'.$details->status) }}
                </x-filament::badge>
            </div>

            <div class="mt-4 flex flex-col gap-2 text-sm">
                @if ($details->claimedMember)
                    <p>{{ __('staff_intake.admin.detail.claimed') }}:
                        <a class="font-semibold text-primary-600 underline" href="{{ $memberUrl($details->claimedMember['id']) }}">{{ $details->claimedMember['name'] }}</a></p>
                @endif
                @if ($details->relatedMember)
                    <p>{{ __('staff_intake.admin.detail.related') }}:
                        <a class="font-semibold text-primary-600 underline" href="{{ $memberUrl($details->relatedMember['id']) }}">{{ $details->relatedMember['name'] }}</a> ({{ $details->relatedMember['faculty'] }})</p>
                @endif
                @if ($details->linkedPersonSlug)
                    <p class="rounded-lg bg-warning-50 p-3 font-semibold text-warning-800 dark:bg-warning-500/10 dark:text-warning-300">{{ __('staff_intake.admin.detail.linked_person', ['slug' => $details->linkedPersonSlug]) }}</p>
                @endif
                @if ($details->reviewerName)
                    <p>{{ __('staff_intake.admin.detail.reviewed', ['name' => $details->reviewerName, 'date' => $details->reviewedAt]) }}</p>
                @endif
                @if ($details->reviewNote)
                    <p class="whitespace-pre-line">{{ $details->reviewNote }}</p>
                @endif
                @if ($details->appliedMemberId)
                    <p><a class="font-semibold text-primary-600 underline" href="{{ $memberUrl($details->appliedMemberId) }}">{{ __('staff_intake.admin.actions.open_profile') }}</a></p>
                @endif
            </div>
        </section>

        @if ($details->candidates !== [])
            <section class="spu-workspace border-danger-300" aria-labelledby="intake-candidates-heading">
                <h3 id="intake-candidates-heading" class="text-lg font-bold text-danger-700 dark:text-danger-400">{{ __('staff_intake.admin.detail.candidates') }}</h3>
                <p class="mt-1 text-sm text-gray-600 dark:text-gray-300">{{ __('staff_intake.admin.detail.candidates_intro') }}</p>
                <ul class="mt-3 list-disc ps-6 text-sm">
                    @foreach ($details->candidates as $candidate)
                        <li><a class="font-semibold text-primary-600 underline" href="{{ $memberUrl($candidate['id']) }}">{{ $candidate['name'] }}</a> — {{ $candidate['faculty'] }}@if ($candidate['sameFaculty']) ({{ __('staff_intake.admin.detail.same_faculty') }})@endif</li>
                    @endforeach
                </ul>
            </section>
        @endif

        <section class="spu-workspace" aria-labelledby="intake-changes-heading">
            <h3 id="intake-changes-heading" class="text-lg font-bold text-gray-950 dark:text-white">{{ __('staff_intake.admin.detail.changes') }}</h3>
            @if ($details->changes === [] && ! $details->hasNewPhoto && ! $details->hasNewCv)
                <p class="mt-2 text-sm text-gray-600">{{ __('staff_intake.admin.detail.no_changes') }}</p>
            @else
                <div class="mt-3 overflow-x-auto">
                    <table class="w-full text-start text-sm">
                        <thead>
                            <tr class="border-b border-gray-200 dark:border-white/10">
                                <th class="p-2 text-start">{{ __('staff_intake.admin.detail.field') }}</th>
                                @if ($details->type === 'update')<th class="p-2 text-start">{{ __('staff_intake.admin.detail.before') }}</th>@endif
                                <th class="p-2 text-start">{{ __('staff_intake.admin.detail.after') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($details->changes as $change)
                                <tr class="border-b border-gray-100 align-top dark:border-white/5">
                                    <th class="p-2 text-start font-semibold">{{ __('staff_intake.fields.'.$change['field']) }}</th>
                                    @if ($details->type === 'update')<td class="whitespace-pre-line p-2 text-gray-500">{{ $change['before'] !== '' ? $change['before'] : '—' }}</td>@endif
                                    <td class="whitespace-pre-line p-2 font-medium">{{ $change['after'] !== '' ? $change['after'] : '—' }}</td>
                                </tr>
                            @endforeach
                            @foreach (['photo' => $details->hasNewPhoto, 'cv' => $details->hasNewCv] as $kind => $hasFile)
                                @if ($hasFile)
                                    <tr class="border-b border-gray-100 dark:border-white/5">
                                        <th class="p-2 text-start font-semibold">{{ __('staff_intake.fields.'.$kind) }}</th>
                                        @if ($details->type === 'update')<td class="p-2 text-gray-500">—</td>@endif
                                        <td class="p-2"><a class="font-semibold text-primary-600 underline" target="_blank" rel="noopener" href="{{ route('admin.staff-intake-requests.files.download', ['staffIntakeRequest' => $details->id, 'kind' => $kind]) }}">{{ __('staff_intake.admin.actions.download_'.$kind) }}</a></td>
                                    </tr>
                                @endif
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </section>

        <section class="spu-workspace" aria-labelledby="intake-log-heading">
            <h3 id="intake-log-heading" class="text-lg font-bold text-gray-950 dark:text-white">{{ __('staff_intake.admin.detail.access_log') }}</h3>
            <div class="mt-3 overflow-x-auto">
                <table class="w-full text-start text-sm">
                    <thead>
                        <tr class="border-b border-gray-200 dark:border-white/10">
                            <th class="p-2 text-start">{{ __('staff_intake.admin.columns.time') }}</th>
                            <th class="p-2 text-start">{{ __('staff_intake.admin.columns.event') }}</th>
                            <th class="p-2 text-start">{{ __('staff_intake.admin.columns.name') }}</th>
                            <th class="p-2 text-start">{{ __('staff_intake.admin.columns.changed_fields') }}</th>
                            <th class="p-2 text-start">{{ __('staff_intake.admin.columns.ip') }}</th>
                            <th class="p-2 text-start">{{ __('staff_intake.admin.columns.user_agent') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($details->accessLog as $entry)
                            <tr class="border-b border-gray-100 align-top dark:border-white/5">
                                <td class="p-2 whitespace-nowrap" dir="ltr">{{ $entry['at'] }}</td>
                                <td class="p-2">{{ __('staff_intake.admin.events.'.$entry['event']) }}</td>
                                <td class="p-2">{{ $entry['name'] ?? '—' }}</td>
                                <td class="p-2">{{ collect($entry['changedFields'])->map(fn (string $field): string => __('staff_intake.fields.'.$field))->implode('، ') ?: '—' }}</td>
                                <td class="p-2" dir="ltr">{{ $entry['ip'] ?? '—' }}</td>
                                <td class="p-2 text-xs text-gray-500" dir="ltr">{{ \Illuminate\Support\Str::limit((string) $entry['userAgent'], 60) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </section>
    </div>
</x-filament-panels::page>
