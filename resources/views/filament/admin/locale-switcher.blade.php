@php
    $currentLocale = app()->getLocale();
    $targetLocale = $currentLocale === 'ar' ? 'en' : 'ar';
@endphp

<form method="POST" action="{{ route('admin.locale', ['locale' => $targetLocale]) }}" class="fi-admin-locale-switcher">
    @csrf
    <button
        type="submit"
        class="inline-flex items-center gap-2 rounded-lg px-3 py-2 text-sm font-semibold text-gray-700 transition hover:bg-gray-100 hover:text-primary-600 dark:text-gray-200 dark:hover:bg-white/5"
        aria-label="{{ __('admin.panel.language_switcher') }}"
    >
        <span>{{ $targetLocale === 'ar' ? __('admin.panel.switch_to_ar') : __('admin.panel.switch_to_en') }}</span>
    </button>
</form>
