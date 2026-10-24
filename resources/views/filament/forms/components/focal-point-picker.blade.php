@php
    $imageUrl = $getImageUrl();
    $focalYStatePath = $generateRelativeStatePath($getFocalYPath());
    $displayFitStatePath = $generateRelativeStatePath($getDisplayFitPath());
@endphp

<x-dynamic-component :component="$getFieldWrapperView()" :field="$field">
    <div
        wire:key="focal-picker-{{ md5((string) $imageUrl) }}"
        x-data="spuFocalPointPicker({
            x: $wire.entangle('{{ $getStatePath() }}'),
            y: $wire.entangle('{{ $focalYStatePath }}'),
            fit: $wire.entangle('{{ $displayFitStatePath }}'),
        })"
        class="spu-focal-picker"
    >
        @if ($imageUrl)
            <div>
                <p id="{{ $getId() }}-instructions" class="spu-focal-picker__instructions">
                    {{ __('admin.media_picker.focal_instructions') }}
                </p>
                <div class="spu-focal-picker__source-wrap">
                    <div
                        class="spu-focal-picker__source"
                        role="slider"
                        tabindex="0"
                        aria-label="Choose the image focal point"
                        aria-describedby="{{ $getId() }}-instructions"
                        aria-valuemin="0"
                        aria-valuemax="100"
                        x-bind:aria-valuenow="Math.round(x) + ', ' + Math.round(y)"
                        x-on:pointerdown="startDrag($event)"
                        x-on:pointermove="drag($event)"
                        x-on:pointerup="endDrag($event)"
                        x-on:pointercancel="endDrag($event)"
                        x-on:keydown="handleKeydown($event)"
                    >
                        <img src="{{ $imageUrl }}" alt="" class="spu-focal-picker__source-image" loading="lazy">
                        <span class="spu-focal-picker__marker" x-bind:style="markerStyle" aria-hidden="true"></span>
                    </div>
                </div>
                <div class="spu-focal-picker__toolbar">
                    <fieldset class="spu-focal-picker__fit">
                        <legend>{{ __('admin.media_picker.fit_question') }}</legend>
                        <label><input type="radio" value="cover" x-model="fit"> {{ __('admin.media_picker.fill_frame') }}</label>
                        <label><input type="radio" value="contain" x-model="fit"> {{ __('admin.media_picker.complete_image') }}</label>
                    </fieldset>
                    <button type="button" class="spu-focal-picker__center" x-on:click="center()">{{ __('admin.media_picker.center_marker') }}</button>
                </div>
                <div class="spu-focal-picker__preview">
                    <span>{{ __('admin.media_picker.display_preview') }}</span>
                    <div class="spu-focal-picker__preview-frame">
                        <img src="{{ $imageUrl }}" alt="" x-bind:style="previewStyle" loading="lazy">
                    </div>
                </div>
                <output class="sr-only" aria-live="polite" x-text="announcement"></output>
            </div>
        @else
            <div class="spu-focal-picker__empty">{{ __('admin.media_picker.choose_image_first') }}</div>
        @endif
    </div>
</x-dynamic-component>
