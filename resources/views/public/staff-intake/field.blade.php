{{-- One labelled input of the staff intake form. --}}
@php($fieldId = 'si-'.str_replace(['.', '_'], '-', $name))
@php($fieldDir = $dir ?? (str_ends_with($name, '_en') ? 'ltr' : (str_ends_with($name, '_ar') ? 'rtl' : null)))
<div class="space-y-1 {{ $class ?? '' }}">
    <label for="{{ $fieldId }}" class="block text-sm font-bold text-slate-700">{{ __('staff_intake.fields.'.$name, [], $locale) }}@if (! empty($required))<span class="text-spu-red" aria-hidden="true"> *</span>@endif</label>
    @if (($type ?? 'text') === 'textarea')
        <textarea id="{{ $fieldId }}" name="{{ $name }}" rows="5" maxlength="5000" @if ($fieldDir) dir="{{ $fieldDir }}" @endif
            aria-invalid="{{ $errors->has($name) ? 'true' : 'false' }}" @if ($errors->has($name)) aria-describedby="{{ $fieldId }}-error" @endif
            class="w-full rounded-lg border border-slate-300 px-3 py-2 outline-none focus:border-spu-blue focus:ring-4 focus:ring-spu-blue/10">{{ old($name, $value) }}</textarea>
    @else
        <input id="{{ $fieldId }}" name="{{ $name }}" type="{{ $type ?? 'text' }}" value="{{ old($name, $value) }}" maxlength="{{ $max ?? 255 }}"
            @if (! empty($required)) required aria-required="true" @endif @if ($fieldDir) dir="{{ $fieldDir }}" @endif
            @if (! empty($autocomplete)) autocomplete="{{ $autocomplete }}" @endif
            aria-invalid="{{ $errors->has($name) ? 'true' : 'false' }}" @if ($errors->has($name) || ! empty($hint)) aria-describedby="{{ $errors->has($name) ? $fieldId.'-error' : $fieldId.'-hint' }}" @endif
            class="w-full rounded-lg border border-slate-300 px-3 py-2 outline-none focus:border-spu-blue focus:ring-4 focus:ring-spu-blue/10">
    @endif
    @if (! empty($hint))<p id="{{ $fieldId }}-hint" class="text-xs text-slate-500">{{ $hint }}</p>@endif
    @error($name)<p id="{{ $fieldId }}-error" class="text-xs font-bold text-spu-red" role="alert">{{ $message }}</p>@enderror
</div>
