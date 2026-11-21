@props([
    'url',
    'title' => '',
    'locale' => app()->getLocale(),
    'heading' => null,
])

@php
    $isAr = $locale === 'ar';
    $encodedUrl = urlencode($url);
    $encodedTitle = urlencode($title);
    $encodedText = urlencode(trim($title.' '.$url));
    $networks = [
        [
            'label' => $isAr ? 'مشاركة على فيسبوك' : 'Share on Facebook',
            'href' => 'https://www.facebook.com/sharer/sharer.php?u='.$encodedUrl,
            'color' => '#1877F2',
            'icon' => '<path d="M24 12.07C24 5.41 18.63 0 12 0S0 5.4 0 12.07C0 18.1 4.39 23.1 10.13 24v-8.44H7.08v-3.49h3.04V9.41c0-3.02 1.8-4.7 4.54-4.7 1.31 0 2.68.24 2.68.24v2.97h-1.5c-1.5 0-1.96.93-1.96 1.89v2.26h3.32l-.53 3.5h-2.8V24C19.62 23.1 24 18.1 24 12.07"/>',
        ],
        [
            'label' => $isAr ? 'مشاركة عبر واتساب' : 'Share on WhatsApp',
            'href' => 'https://wa.me/?text='.$encodedText,
            'color' => '#25D366',
            'icon' => '<path d="M17.47 14.38c-.3-.15-1.76-.87-2.03-.97-.27-.1-.47-.15-.67.15-.2.3-.77.97-.94 1.16-.17.2-.35.22-.64.07-.3-.15-1.26-.46-2.39-1.48-.88-.79-1.48-1.76-1.65-2.06-.17-.3-.02-.46.13-.6.13-.14.3-.35.45-.52.15-.17.2-.3.3-.5.1-.2.05-.37-.03-.52-.07-.15-.67-1.6-.92-2.2-.24-.58-.49-.5-.67-.5h-.57c-.2 0-.52.07-.8.37-.27.3-1.04 1.02-1.04 2.48s1.07 2.88 1.21 3.07c.15.2 2.1 3.2 5.08 4.49.71.3 1.26.49 1.7.63.71.22 1.36.19 1.87.12.57-.09 1.76-.72 2-1.41.25-.7.25-1.29.18-1.41-.07-.13-.27-.2-.57-.35m-5.42 7.4h-.01a9.87 9.87 0 0 1-5.03-1.38l-.36-.21-3.74.98 1-3.65-.24-.37a9.86 9.86 0 0 1-1.51-5.26c0-5.45 4.44-9.88 9.89-9.88 2.64 0 5.12 1.03 6.99 2.9a9.82 9.82 0 0 1 2.89 6.99c0 5.45-4.44 9.88-9.88 9.88m8.41-18.3A11.81 11.81 0 0 0 12.05 0C5.5 0 .16 5.34.16 11.89c0 2.1.55 4.14 1.59 5.95L.06 24l6.3-1.65a11.88 11.88 0 0 0 5.68 1.45h.01c6.55 0 11.89-5.34 11.89-11.89 0-3.18-1.24-6.16-3.48-8.41"/>',
        ],
        [
            'label' => $isAr ? 'مشاركة على تيليغرام' : 'Share on Telegram',
            'href' => 'https://t.me/share/url?url='.$encodedUrl.'&text='.$encodedTitle,
            'color' => '#229ED9',
            'icon' => '<path d="M11.94 0A12 12 0 1 0 24 12 12 12 0 0 0 11.94 0Zm5.88 8.18-1.97 9.28c-.15.66-.54.82-1.09.51l-3-2.21-1.45 1.4c-.16.16-.3.3-.6.3l.21-3.05 5.56-5.02c.24-.21-.05-.33-.38-.12l-6.87 4.32-2.96-.92c-.64-.2-.66-.64.14-.95l11.56-4.46c.53-.19 1 .13.85.92Z"/>',
        ],
        [
            'label' => $isAr ? 'مشاركة على لينكدإن' : 'Share on LinkedIn',
            'href' => 'https://www.linkedin.com/sharing/share-offsite/?url='.$encodedUrl,
            'color' => '#0A66C2',
            'icon' => '<path d="M20.45 20.45h-3.55v-5.57c0-1.33-.03-3.04-1.85-3.04-1.85 0-2.14 1.45-2.14 2.94v5.67H9.35V9h3.41v1.56h.05c.48-.9 1.64-1.85 3.37-1.85 3.6 0 4.27 2.37 4.27 5.46v6.28ZM5.34 7.43a2.06 2.06 0 1 1 0-4.13 2.06 2.06 0 0 1 0 4.13ZM7.12 20.45H3.56V9h3.56v11.45ZM22.23 0H1.77C.79 0 0 .77 0 1.73v20.54C0 23.23.79 24 1.77 24h20.45c.98 0 1.78-.77 1.78-1.73V1.73C24 .77 23.2 0 22.22 0h.01Z"/>',
        ],
        [
            'label' => $isAr ? 'مشاركة على إكس' : 'Share on X',
            'href' => 'https://x.com/intent/post?url='.$encodedUrl.'&text='.$encodedTitle,
            'color' => '#0F1419',
            'icon' => '<path d="M18.24 2.25h3.31l-7.23 8.26 8.5 11.24h-6.65l-5.21-6.82-5.97 6.82H1.68l7.73-8.84L1.25 2.25h6.83l4.71 6.23Zm-1.16 17.52h1.83L7.08 4.13H5.12Z"/>',
        ],
    ];
@endphp

<div x-data="newsShare" data-share-url="{{ $url }}" data-share-title="{{ $title }}" {{ $attributes->class('share-links') }}>
    @if ($heading !== false)
        <p class="share-links-heading">{{ $heading ?? ($isAr ? 'شارك الملف الشخصي' : 'Share this profile') }}</p>
    @endif
    <div class="share-links-row">
        @foreach ($networks as $network)
            <a href="{{ $network['href'] }}" target="_blank" rel="noopener noreferrer" class="share-links-btn" style="--share-color: {{ $network['color'] }}" aria-label="{{ $network['label'] }}" title="{{ $network['label'] }}">
                <svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">{!! $network['icon'] !!}</svg>
            </a>
        @endforeach
        <a href="mailto:?subject={{ rawurlencode($title) }}&body={{ rawurlencode($url) }}" class="share-links-btn" style="--share-color: #6f1616" aria-label="{{ $isAr ? 'مشاركة عبر البريد الإلكتروني' : 'Share by email' }}" title="{{ $isAr ? 'مشاركة عبر البريد الإلكتروني' : 'Share by email' }}">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="2" y="4" width="20" height="16" rx="2"/><path d="m22 7-10 6L2 7"/></svg>
        </a>
    </div>
    <div class="share-links-actions">
        <button type="button" x-on:click="copy" class="share-links-copy" x-bind:class="copyClass()" aria-live="polite">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71"/><path d="M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71"/></svg>
            <span x-text="copyLabel">{{ $isAr ? 'نسخ الرابط' : 'Copy link' }}</span>
        </button>
        {{-- The device's own share sheet, where the browser has one (phones). --}}
        <button type="button" x-show="canShare" x-cloak x-on:click="share" class="share-links-btn" style="--share-color: #202759" aria-label="{{ $isAr ? 'مشاركة عبر تطبيق آخر' : 'Share via another app' }}" title="{{ $isAr ? 'مشاركة عبر تطبيق آخر' : 'Share via another app' }}">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="18" cy="5" r="3"/><circle cx="6" cy="12" r="3"/><circle cx="18" cy="19" r="3"/><path d="m8.6 13.5 6.8 4M15.4 6.5l-6.8 4"/></svg>
        </button>
    </div>
</div>
