@extends('public.staff-intake.layout')

@php($fields = $form->fields)
@php($educationRows = old('educations', $form->educations))
@php($educationRows = is_array($educationRows) ? array_values(array_filter($educationRows, 'is_array')) : [])

@section('content')
    <section class="rounded-2xl bg-white p-6 shadow-sm sm:p-8">
        <h2 class="mb-2 text-2xl font-bold text-spu-blue">{{ __($form->mode === 'update' ? 'staff_intake.public.form_heading_update' : 'staff_intake.public.form_heading_create', [], $locale) }}</h2>
        <p class="text-slate-600">{{ $form->facultyName }}</p>
        <p class="mt-2 text-slate-600">{{ __($form->mode === 'update' ? 'staff_intake.public.form_intro_update' : 'staff_intake.public.form_intro_create', [], $locale) }}</p>
        @if ($form->relatedFacultyName)
            <p class="mt-3 rounded-lg bg-amber-50 px-4 py-3 text-sm text-amber-900">{{ __($form->isCenter ? 'staff_intake.center.from_other' : 'staff_intake.public.from_other_faculty', ['faculty' => $form->relatedFacultyName], $locale) }}</p>
        @endif

        @if ($errors->has('form'))
            <p class="mt-4 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm font-bold text-red-800" role="alert">{{ $errors->first('form') }}</p>
        @endif

        @if (! empty($duplicates))
            <div class="mt-6 rounded-xl border border-amber-300 bg-amber-50 p-5" role="alert">
                <h3 class="mb-2 text-lg font-bold text-amber-900">{{ __('staff_intake.public.duplicates_heading', [], $locale) }}</h3>
                <p class="mb-4 text-sm text-amber-900">{{ __('staff_intake.public.duplicates_intro', [], $locale) }}</p>
                <ul class="space-y-2">
                    @foreach ($duplicates as $candidate)
                        <li>
                            <a href="{{ $candidate['url'] }}" class="block rounded-lg border border-amber-200 bg-white px-4 py-3 hover:border-spu-blue focus:outline-none focus:ring-4 focus:ring-spu-blue/10">
                                <span class="font-bold">{{ $candidate['name'] }}</span>
                                <span class="block text-sm text-slate-500">{{ collect([$candidate['title'], $candidate['department'], $candidate['faculty']])->filter()->implode(' · ') }}</span>
                                <span class="text-sm font-bold text-spu-red">{{ __('staff_intake.public.this_is_me', [], $locale) }}</span>
                            </a>
                        </li>
                    @endforeach
                </ul>
            </div>
        @endif

        <form method="POST" action="{{ route('public.staff-intake.submit', ['locale' => $locale, 'token' => $token]) }}" enctype="multipart/form-data" class="mt-8 space-y-10" data-staff-intake-form>
            @csrf
            <input type="hidden" name="ref" value="{{ $form->ref }}">
            <input type="text" name="website" value="" tabindex="-1" autocomplete="off" class="sr-only" aria-hidden="true">

            <fieldset class="space-y-4">
                <legend class="mb-2 text-lg font-bold text-spu-blue">{{ __('staff_intake.public.section_names', [], $locale) }}</legend>
                <div class="grid gap-4 sm:grid-cols-2">
                    @include('public.staff-intake.field', ['name' => 'name_ar', 'value' => $fields['name_ar'], 'required' => true])
                    @include('public.staff-intake.field', ['name' => 'name_en', 'value' => $fields['name_en'], 'required' => true])
                    @include('public.staff-intake.field', ['name' => 'title_ar', 'value' => $fields['title_ar'], 'required' => in_array('title_ar', $form->requiredFields, true)])
                    @include('public.staff-intake.field', ['name' => 'title_en', 'value' => $fields['title_en'], 'required' => in_array('title_en', $form->requiredFields, true)])
                    {{-- Which fields are required is picked per link (StaffIntakeFields::required); both names always are. --}}
                    @include('public.staff-intake.field', ['name' => 'position_ar', 'value' => $fields['position_ar'], 'required' => in_array('position_ar', $form->requiredFields, true), 'hint' => $form->isCenter ? __('staff_intake.center.position_hint', [], $locale) : null])
                    @include('public.staff-intake.field', ['name' => 'position_en', 'value' => $fields['position_en'], 'required' => in_array('position_en', $form->requiredFields, true)])
                </div>
            </fieldset>

            <fieldset class="space-y-4">
                <legend class="mb-2 text-lg font-bold text-spu-blue">{{ __('staff_intake.public.section_about', [], $locale) }}</legend>
                <div class="grid gap-4 sm:grid-cols-2">
                    @include('public.staff-intake.field', ['name' => 'bio_ar', 'value' => $fields['bio_ar'], 'type' => 'textarea', 'required' => in_array('bio_ar', $form->requiredFields, true)])
                    @include('public.staff-intake.field', ['name' => 'bio_en', 'value' => $fields['bio_en'], 'type' => 'textarea', 'required' => in_array('bio_en', $form->requiredFields, true)])
                    @include('public.staff-intake.field', ['name' => 'specializations_ar', 'value' => implode('، ', $fields['specializations_ar']), 'max' => 1000, 'required' => in_array('specializations_ar', $form->requiredFields, true), 'hint' => __('staff_intake.public.specializations_hint', [], $locale)])
                    @include('public.staff-intake.field', ['name' => 'specializations_en', 'value' => implode(', ', $fields['specializations_en']), 'max' => 1000, 'required' => in_array('specializations_en', $form->requiredFields, true), 'hint' => __('staff_intake.public.specializations_hint', [], $locale)])
                </div>
            </fieldset>

            <fieldset class="space-y-4">
                <legend class="mb-2 text-lg font-bold text-spu-blue">{{ __($form->isCenter ? 'staff_intake.center.section_contact' : 'staff_intake.public.section_contact', [], $locale) }}</legend>
                <div class="grid gap-4 sm:grid-cols-2">
                    @unless ($form->isCenter)
                    <div class="space-y-1">
                        <label for="si-department-id" class="block text-sm font-bold text-slate-700">{{ __('staff_intake.fields.department_id', [], $locale) }}@if (in_array('department_id', $form->requiredFields, true))<span class="text-spu-red" aria-hidden="true"> *</span>@endif</label>
                        <select id="si-department-id" name="department_id" class="w-full rounded-lg border border-slate-300 px-3 py-2 outline-none focus:border-spu-blue focus:ring-4 focus:ring-spu-blue/10"
                            @if (in_array('department_id', $form->requiredFields, true)) required aria-required="true" @endif
                            aria-invalid="{{ $errors->has('department_id') ? 'true' : 'false' }}">
                            <option value="">{{ __('staff_intake.public.no_department', [], $locale) }}</option>
                            @foreach ($form->departments as $departmentId => $departmentName)
                                <option value="{{ $departmentId }}" @selected((string) old('department_id', $fields['department_id']) === (string) $departmentId)>{{ $departmentName }}</option>
                            @endforeach
                        </select>
                        @error('department_id')<p class="text-xs font-bold text-spu-red" role="alert">{{ $message }}</p>@enderror
                    </div>
                    @endunless
                    @include('public.staff-intake.field', ['name' => 'office_location', 'value' => $fields['office_location'], 'required' => in_array('office_location', $form->requiredFields, true)])
                    @include('public.staff-intake.field', ['name' => 'email', 'value' => $fields['email'], 'type' => 'email', 'dir' => 'ltr', 'autocomplete' => 'email', 'required' => in_array('email', $form->requiredFields, true)])
                    @include('public.staff-intake.field', ['name' => 'phone', 'value' => $fields['phone'], 'type' => 'tel', 'dir' => 'ltr', 'max' => 30, 'autocomplete' => 'tel', 'required' => in_array('phone', $form->requiredFields, true)])
                </div>
            </fieldset>

            <fieldset class="space-y-4">
                <legend class="mb-2 text-lg font-bold text-spu-blue">{{ __('staff_intake.public.section_links', [], $locale) }}</legend>
                <div class="grid gap-4 sm:grid-cols-2">
                    {{-- Plain text, not type="url": the browser refused "orcid.org/…" or an old link without https:// with a bubble phones barely show. The server completes and checks them. --}}
                    @foreach (['linkedin', 'orcid', 'scopus', 'researchgate', 'scholar', 'twitter'] as $linkField)
                        @include('public.staff-intake.field', ['name' => $linkField, 'value' => $fields[$linkField], 'dir' => 'ltr', 'inputmode' => 'url', 'required' => in_array($linkField, $form->requiredFields, true)])
                    @endforeach
                </div>
            </fieldset>

            <fieldset class="space-y-4" data-educations data-next-index="{{ count($educationRows) + 1 }}">
                <legend class="mb-2 text-lg font-bold text-spu-blue">{{ __('staff_intake.public.section_education', [], $locale) }}</legend>
                <div class="space-y-4" data-education-list>
                    @foreach ($educationRows as $index => $row)
                        @include('public.staff-intake.education-row', ['index' => $index, 'row' => $row])
                    @endforeach
                    {{-- An empty row, so the form works without JavaScript too. --}}
                    @include('public.staff-intake.education-row', ['index' => count($educationRows), 'row' => []])
                </div>
                <template data-education-template>
                    @include('public.staff-intake.education-row', ['index' => '__INDEX__', 'row' => []])
                </template>
                <button type="button" data-add-education hidden class="rounded-lg border border-spu-blue px-4 py-2 text-sm font-bold text-spu-blue hover:bg-spu-blue hover:text-white">{{ __('staff_intake.public.add_education', [], $locale) }}</button>
            </fieldset>

            <fieldset class="space-y-4">
                <legend class="mb-2 text-lg font-bold text-spu-blue">{{ __('staff_intake.public.section_files', [], $locale) }}</legend>
                <div class="grid gap-6 sm:grid-cols-2">
                    <div class="space-y-2">
                        <label for="si-photo" class="block text-sm font-bold text-slate-700">{{ __('staff_intake.fields.photo', [], $locale) }}@if (in_array('photo', $form->requiredFields, true))<span class="text-spu-red" aria-hidden="true"> *</span>@endif</label>
                        @if ($form->photoUrl)
                            <img src="{{ $form->photoUrl }}" alt="{{ __('staff_intake.public.current_photo', [], $locale) }}" class="h-24 w-24 rounded-full object-cover">
                        @endif
                        <input id="si-photo" name="photo" type="file" accept="image/jpeg,image/png,image/webp" aria-describedby="si-photo-hint" class="block w-full text-sm" @if (in_array('photo', $form->requiredFields, true) && ! $form->photoUrl && ! isset($heldFiles['photo'])) required aria-required="true" @endif>
                        @if (isset($heldFiles['photo']))
                            <input type="hidden" name="photo_held" value="{{ $heldFiles['photo']->handle }}">
                            <p class="rounded-lg bg-emerald-50 px-3 py-2 text-sm font-bold text-emerald-800" role="status">{{ __('staff_intake.public.held_file', ['name' => $heldFiles['photo']->name], $locale) }}</p>
                        @endif
                        <p id="si-photo-hint" class="text-xs text-slate-500">{{ __('staff_intake.public.photo_hint', [], $locale) }}</p>
                        @error('photo')<p class="text-xs font-bold text-spu-red" role="alert">{{ $message }}</p>@enderror
                    </div>
                    <div class="space-y-2">
                        <label for="si-cv" class="block text-sm font-bold text-slate-700">{{ __('staff_intake.fields.cv', [], $locale) }}@if (in_array('cv', $form->requiredFields, true))<span class="text-spu-red" aria-hidden="true"> *</span>@endif</label>
                        @if ($form->cvUrl)
                            <a href="{{ $form->cvUrl }}" target="_blank" rel="noopener noreferrer" class="block text-sm font-bold text-spu-blue underline">{{ __('staff_intake.public.current_cv', [], $locale) }}</a>
                        @endif
                        <input id="si-cv" name="cv" type="file" accept="application/pdf" aria-describedby="si-cv-hint" class="block w-full text-sm" @if (in_array('cv', $form->requiredFields, true) && ! $form->cvUrl && ! isset($heldFiles['cv'])) required aria-required="true" @endif>
                        @if (isset($heldFiles['cv']))
                            <input type="hidden" name="cv_held" value="{{ $heldFiles['cv']->handle }}">
                            <p class="rounded-lg bg-emerald-50 px-3 py-2 text-sm font-bold text-emerald-800" role="status">{{ __('staff_intake.public.held_file', ['name' => $heldFiles['cv']->name], $locale) }}</p>
                        @endif
                        <p id="si-cv-hint" class="text-xs text-slate-500">{{ __('staff_intake.public.cv_hint', [], $locale) }}</p>
                        @error('cv')<p class="text-xs font-bold text-spu-red" role="alert">{{ $message }}</p>@enderror
                    </div>
                </div>
            </fieldset>

            @if (empty($duplicates) && old('confirm_not_duplicate'))
                {{-- Already confirmed on an earlier try: a later return (a missing field) must not ask again. --}}
                <input type="hidden" name="confirm_not_duplicate" value="1">
            @endif
            @if (! empty($duplicates))
                <label class="flex items-start gap-3 rounded-lg border border-amber-300 bg-amber-50 p-4 text-sm font-bold text-amber-900">
                    <input type="checkbox" name="confirm_not_duplicate" value="1" required class="mt-1">
                    <span>{{ __('staff_intake.public.confirm_not_duplicate', [], $locale) }}</span>
                </label>
            @endif

            <div class="border-t border-slate-100 pt-6">
                <p class="mb-4 text-sm text-slate-500">{{ __($form->isCenter ? 'staff_intake.center.review_note' : 'staff_intake.public.review_note', [], $locale) }}</p>
                <button type="submit" class="rounded-lg bg-spu-red px-6 py-3 font-bold text-white hover:bg-spu-red/90 focus:outline-none focus:ring-4 focus:ring-spu-red/30">{{ __('staff_intake.public.submit', [], $locale) }}</button>
            </div>
        </form>
    </section>
@endsection
