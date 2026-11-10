@if ($isPreview ?? false)
    <div class="border-b border-amber-400/30 bg-amber-400/10">
        <div class="mx-auto flex max-w-7xl items-center justify-between gap-3 px-4 py-3 text-sm text-amber-100 sm:px-6 lg:px-8">
            <p>
                {{ __('public.preview_mode') }}
                @isset($preview)
                    <span class="text-amber-200/80">{{ strtoupper($preview->targetType) }}</span>
                @endisset
            </p>
            @isset($preview)
                @if ($preview->expiresAt)
                    <p class="text-amber-200/80">{{ __('public.expires', ['time' => $preview->expiresAt]) }}</p>
                @endif
            @endisset
        </div>
    </div>
@endif
