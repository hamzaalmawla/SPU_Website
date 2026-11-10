@props([
    'area' => null,
    'description' => null,
    'state' => null,
    'stateColor' => 'gray',
    'locales' => ['ar', 'en'],
    'links' => [],
])

@php
    $descriptionText = $description ?? ($area ? __('admin.cms.areas.' . $area) : null);
    $localeLabels = [
        'ar' => ['label' => __('admin.locales.ar'), 'meta' => __('admin.cms.primary_locale')],
        'en' => ['label' => __('admin.locales.en'), 'meta' => __('admin.cms.secondary_locale')],
    ];
@endphp

<section class="spu-workspace">
    <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
        <div class="min-w-0">
            <p class="spu-workspace__eyebrow">
                {{ __('admin.cms.workspace') }}
            </p>

            @if ($descriptionText)
                <p class="spu-workspace__description">
                    {{ $descriptionText }}
                </p>
            @endif
        </div>

        <div class="spu-workspace__meta">
            @if ($state)
                <div class="inline-flex items-center gap-2 rounded-xl border border-gray-200 px-3 py-2 text-sm dark:border-gray-700">
                    <span class="text-gray-500 dark:text-gray-400">{{ __('admin.cms.current_state') }}</span>
                    <x-filament::badge :color="$stateColor">{{ $state }}</x-filament::badge>
                </div>
            @endif

            @if ($locales !== [])
                <div class="inline-flex flex-wrap items-center gap-2 rounded-xl border border-gray-200 px-3 py-2 dark:border-gray-700">
                    <span class="text-sm font-medium text-gray-700 dark:text-gray-200">{{ __('admin.cms.content_languages') }}</span>

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

    @if ($links !== [])
        <nav class="spu-workspace__nav" aria-label="{{ __('admin.cms.workspace_navigation') }}">
            @foreach ($links as $link)
                <a
                    class="spu-workspace__link"
                    href="{{ $link['url'] }}"
                    @if ($link['active'] ?? false) aria-current="page" @endif
                >
                    {{ $link['label'] }}
                </a>
            @endforeach
        </nav>
    @endif
</section>
