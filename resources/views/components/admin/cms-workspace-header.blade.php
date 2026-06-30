@props([
    'area' => null,
    'description' => null,
    'state' => null,
    'stateColor' => 'gray',
    'locales' => ['ar', 'en'],
])

@php
    $descriptionText = $description ?? ($area ? __('admin.cms.areas.' . $area) : null);
    $localeLabels = [
        'ar' => ['label' => 'العربية', 'meta' => __('admin.cms.primary_locale')],
        'en' => ['label' => 'English', 'meta' => __('admin.cms.secondary_locale')],
    ];
@endphp

<section class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-800 dark:bg-gray-900">
    <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
        <div class="min-w-0">
            <p class="text-xs font-semibold uppercase tracking-wide text-primary-600 dark:text-primary-400">
                {{ __('admin.cms.workspace') }}
            </p>

            @if ($descriptionText)
                <p class="mt-2 max-w-3xl text-sm leading-6 text-gray-600 dark:text-gray-300">
                    {{ $descriptionText }}
                </p>
            @endif
        </div>

        <div class="flex flex-wrap items-center gap-2">
            @if ($state)
                <div class="inline-flex items-center gap-2 rounded-xl border border-gray-200 px-3 py-2 text-sm dark:border-gray-700">
                    <span class="text-gray-500 dark:text-gray-400">{{ __('admin.cms.current_state') }}</span>
                    <x-filament::badge :color="$stateColor">{{ $state }}</x-filament::badge>
                </div>
            @endif

            @if ($locales !== [])
                <div class="inline-flex flex-wrap items-center gap-2 rounded-xl border border-gray-200 px-3 py-2 dark:border-gray-700">
                    <span class="text-sm font-medium text-gray-700 dark:text-gray-200">{{ __('admin.cms.language_status') }}</span>

                    @foreach ($locales as $locale)
                        @php($localeData = $localeLabels[$locale] ?? ['label' => strtoupper((string) $locale), 'meta' => null])
                        <span class="inline-flex items-center gap-1 rounded-lg bg-gray-50 px-2 py-1 text-xs font-semibold text-gray-700 ring-1 ring-gray-200 dark:bg-gray-800 dark:text-gray-200 dark:ring-gray-700" dir="{{ $locale === 'ar' ? 'rtl' : 'ltr' }}">
                            {{ $localeData['label'] }}
                            @if ($localeData['meta'])
                                <span class="text-gray-400">· {{ $localeData['meta'] }}</span>
                            @endif
                        </span>
                    @endforeach
                </div>
            @endif
        </div>
    </div>
</section>
