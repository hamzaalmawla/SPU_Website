{{-- One education entry; $index is a number, or __INDEX__ inside the template. --}}
<fieldset class="rounded-xl border border-slate-200 p-4" data-education-row>
    <legend class="sr-only">{{ __('staff_intake.fields.educations', [], $locale) }}</legend>
    <input type="hidden" name="educations[{{ $index }}][id]" value="{{ $row['id'] ?? '' }}">
    <div class="grid gap-3 sm:grid-cols-2">
        @foreach (['degree_ar', 'degree_en', 'institution_ar', 'institution_en', 'field_ar', 'field_en'] as $key)
            <div class="space-y-1">
                <label for="si-edu-{{ $index }}-{{ $key }}" class="block text-xs font-bold text-slate-600">{{ __('staff_intake.education.'.$key, [], $locale) }}</label>
                <input id="si-edu-{{ $index }}-{{ $key }}" name="educations[{{ $index }}][{{ $key }}]" type="text" maxlength="255" value="{{ $row[$key] ?? '' }}"
                    dir="{{ str_ends_with($key, '_en') ? 'ltr' : 'rtl' }}"
                    class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm outline-none focus:border-spu-blue focus:ring-4 focus:ring-spu-blue/10">
            </div>
        @endforeach
        @foreach (['year_start', 'year_end'] as $key)
            <div class="space-y-1">
                <label for="si-edu-{{ $index }}-{{ $key }}" class="block text-xs font-bold text-slate-600">{{ __('staff_intake.education.'.$key, [], $locale) }}</label>
                <input id="si-edu-{{ $index }}-{{ $key }}" name="educations[{{ $index }}][{{ $key }}]" type="text" inputmode="numeric" maxlength="4" value="{{ $row[$key] ?? '' }}" dir="ltr"
                    class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm outline-none focus:border-spu-blue focus:ring-4 focus:ring-spu-blue/10">
            </div>
        @endforeach
    </div>
    @foreach (['degree_ar', 'year_end'] as $key)
        @error('educations.'.$index.'.'.$key)<p class="mt-2 text-xs font-bold text-spu-red" role="alert">{{ $message }}</p>@enderror
    @endforeach
    <button type="button" data-remove-education class="mt-3 text-sm font-bold text-spu-red underline">{{ __('staff_intake.public.remove_education', [], $locale) }}</button>
</fieldset>
