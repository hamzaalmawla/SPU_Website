@if ($assets !== [])
    <fieldset class="spu-recent-media">
        <legend>{{ __('admin.media_picker.recent_images') }}</legend>
        <p>{{ __('admin.media_picker.recent_help') }}</p>
        <div class="spu-recent-media__grid">
            @foreach ($assets as $asset)
                <label class="spu-recent-media__card">
                    <input
                        type="radio"
                        value="{{ $asset['id'] }}"
                        wire:model.live="{{ $generateRelativeStatePath($targetField) }}"
                    >
                    <img src="{{ $asset['url'] }}" alt="" loading="lazy">
                    <span>{{ $asset['label'] }}</span>
                    <strong aria-hidden="true">{{ __('admin.media_picker.selected') }}</strong>
                </label>
            @endforeach
        </div>
    </fieldset>
@endif
