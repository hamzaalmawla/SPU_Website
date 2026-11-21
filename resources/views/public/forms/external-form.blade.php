{{-- Shown in place of one of our forms when the editor linked a form on another site. --}}
<div class="rounded-2xl border border-spu-blue/10 bg-white p-6 text-center">
    <p class="mb-5 text-sm font-bold leading-7 text-slate-600">{{ __('public.external_form_note') }}</p>
    <a href="{{ $url }}" target="_blank" rel="noopener noreferrer" class="inline-flex items-center justify-center gap-2 rounded-lg bg-spu-red px-8 py-3 text-sm font-bold text-white transition hover:bg-spu-blue focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-spu-red">
        {{ $label ?? __('public.external_form_button') }}
        <svg class="h-4 w-4 rtl:-scale-x-100" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5h5v5M19 5l-8 8M10 5H6a1 1 0 00-1 1v12a1 1 0 001 1h12a1 1 0 001-1v-4"/></svg>
    </a>
</div>
