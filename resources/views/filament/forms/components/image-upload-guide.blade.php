@php
    $isLibrary = $isLibrary ?? false;
    $isBatch = $isBatch ?? false;
    $isReplacement = $isReplacement ?? false;
    $allProfiles = ($isLibrary || $isBatch) ? \App\Filament\Support\ImageDimensionGuide::allProfiles() : [];
@endphp

<div class="spu-image-guide" role="region" aria-label="{{ __('admin.image_guidance.title') }}">
    <div class="spu-image-guide__header">
        <div class="spu-image-guide__title-wrap">
            <span class="spu-image-guide__icon-glow" aria-hidden="true">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor">
                    <path d="M12 2.25a.75.75 0 01.75.75v2.25a.75.75 0 01-1.5 0V3a.75.75 0 01.75-.75zM7.5 12a4.5 4.5 0 119 0 4.5 4.5 0 01-9 0zM18.894 6.166a.75.75 0 00-1.06-1.06l-1.591 1.59a.75.75 0 101.06 1.061l1.591-1.59zM21.75 12a.75.75 0 01-.75.75h-2.25a.75.75 0 010-1.5H21a.75.75 0 01.75.75zM17.834 18.894a.75.75 0 001.06-1.06l-1.59-1.591a.75.75 0 10-1.061 1.06l1.59 1.591zM12 18a.75.75 0 01.75.75V21a.75.75 0 01-1.5 0v-2.25A.75.75 0 0112 18zM7.758 17.303a.75.75 0 00-1.061-1.06l-1.591 1.59a.75.75 0 001.06 1.061l1.591-1.59zM6 12a.75.75 0 01-.75.75H3a.75.75 0 010-1.5h2.25A.75.75 0 016 12zM6.697 7.757a.75.75 0 001.06-1.06l-1.59-1.591a.75.75 0 00-1.061 1.06l1.59 1.591z" />
                </svg>
            </span>
            <div>
                <h4 class="spu-image-guide__title">
                    {{ $isBatch ? __('admin.image_guidance.batch_title') : ($isLibrary ? __('admin.image_guidance.library_title') : __('admin.image_guidance.title')) }}
                </h4>
                <p class="spu-image-guide__subtitle">
                    {{ $isBatch ? __('admin.image_guidance.batch_subtitle') : ($isLibrary ? __('admin.image_guidance.library_subtitle') : __('admin.image_guidance.subtitle')) }}
                </p>
            </div>
        </div>

        @if (! $isLibrary && ! $isBatch)
            <span class="spu-image-guide__badge">
                {{ $guide['name'] }}
            </span>
        @endif
    </div>

    {{-- Primary Specs Chips --}}
    <div class="spu-image-guide__grid">
        <div class="spu-image-guide__chip spu-image-guide__chip--highlight">
            <span class="spu-image-guide__chip-label">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="spu-image-guide__chip-icon" aria-hidden="true">
                    <path d="M10 2a.75.75 0 01.75.75v1.5a.75.75 0 01-1.5 0v-1.5A.75.75 0 0110 2zM10 15a.75.75 0 01.75.75v1.5a.75.75 0 01-1.5 0v-1.5A.75.75 0 0110 15zM2 10a.75.75 0 01.75-.75h1.5a.75.75 0 010 1.5h-1.5A.75.75 0 012 10zM15 10a.75.75 0 01.75-.75h1.5a.75.75 0 010 1.5h-1.5A.75.75 0 0115 10zM4.75 4.75a.75.75 0 011.06 0l1.06 1.06a.75.75 0 01-1.06 1.06l-1.06-1.06a.75.75 0 010-1.06zM13.19 13.19a.75.75 0 011.06 0l1.06 1.06a.75.75 0 11-1.06 1.06l-1.06-1.06a.75.75 0 010-1.06zM4.75 15.25a.75.75 0 010-1.06l1.06-1.06a.75.75 0 111.06 1.06l-1.06 1.06a.75.75 0 01-1.06 0zM13.19 6.81a.75.75 0 010-1.06l1.06-1.06a.75.75 0 111.06 1.06l-1.06 1.06a.75.75 0 01-1.06 0z" />
                </svg>
                {{ __('admin.image_guidance.recommended_size') }}
            </span>
            <strong class="spu-image-guide__chip-value spu-image-guide__chip-value--primary" dir="ltr">
                {{ $guide['dimensions'] }}
            </strong>
        </div>

        <div class="spu-image-guide__chip">
            <span class="spu-image-guide__chip-label">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="spu-image-guide__chip-icon" aria-hidden="true">
                    <path fill-rule="evenodd" d="M3.75 3A1.75 1.75 0 002 4.75v10.5c0 .966.784 1.75 1.75 1.75h12.5A1.75 1.75 0 0018 15.25V4.75A1.75 1.75 0 0016.25 3H3.75zM3.5 4.75a.25.25 0 01.25-.25h12.5a.25.25 0 01.25.25v10.5a.25.25 0 01-.25.25H3.75a.25.25 0 01-.25-.25V4.75z" clip-rule="evenodd" />
                </svg>
                {{ __('admin.image_guidance.aspect_ratio') }}
            </span>
            <span class="spu-image-guide__chip-value">
                {{ $guide['ratio'] }}
            </span>
        </div>

        <div class="spu-image-guide__chip">
            <span class="spu-image-guide__chip-label">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="spu-image-guide__chip-icon" aria-hidden="true">
                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm.75-11.25a.75.75 0 00-1.5 0v4.59L7.3 9.74a.75.75 0 00-1.1 1.02l3.25 3.5a.75.75 0 001.1 0l3.25-3.5a.75.75 0 10-1.1-1.02l-1.95 2.1V6.75z" clip-rule="evenodd" />
                </svg>
                {{ __('admin.image_guidance.min_size') }}
            </span>
            <span class="spu-image-guide__chip-value" dir="ltr">
                {{ $guide['min'] }}
            </span>
        </div>

        <div class="spu-image-guide__chip">
            <span class="spu-image-guide__chip-label">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="spu-image-guide__chip-icon" aria-hidden="true">
                    <path d="M3 3.5A1.5 1.5 0 014.5 2h6.879a1.5 1.5 0 011.06.44l4.122 4.12A1.5 1.5 0 0117 7.622V16.5a1.5 1.5 0 01-1.5 1.5h-11A1.5 1.5 0 013 16.5v-13z" />
                </svg>
                {{ __('admin.image_guidance.formats') }}
            </span>
            <span class="spu-image-guide__chip-value">
                {{ $guide['formats'] }} ({{ $guide['size'] }})
            </span>
        </div>
    </div>

    {{-- Professional Quality Tip --}}
    <div class="spu-image-guide__tip">
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="spu-image-guide__tip-icon" aria-hidden="true">
            <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a.75.75 0 000 1.5h.253a.25.25 0 01.244.304l-.459 2.066A1.75 1.75 0 0010.747 15H11a.75.75 0 000-1.5h-.253a.25.25 0 01-.244-.304l.459-2.066A1.75 1.75 0 009.253 9H9z" clip-rule="evenodd" />
        </svg>
        <div class="spu-image-guide__tip-content">
            <strong class="spu-image-guide__tip-label">{{ __('admin.image_guidance.pro_tip') }}:</strong>
            <span>{{ $guide['tip'] }}</span>
        </div>
    </div>

    {{-- Full specifications reference table for Library / Batch views --}}
    @if (($isLibrary || $isBatch) && ! empty($allProfiles))
        <details class="spu-image-guide__all-specs">
            <summary class="spu-image-guide__all-specs-toggle">
                <span>{{ __('admin.image_guidance.all_specs_title') }}</span>
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="spu-image-guide__toggle-icon" aria-hidden="true">
                    <path fill-rule="evenodd" d="M5.23 7.21a.75.75 0 011.06.02L10 11.168l3.71-3.938a.75.75 0 111.08 1.04l-4.25 4.5a.75.75 0 01-1.08 0l-4.25-4.5a.75.75 0 01.02-1.06z" clip-rule="evenodd" />
                </svg>
            </summary>
            <div class="spu-image-guide__specs-table-wrap">
                <table class="spu-image-guide__specs-table">
                    <thead>
                        <tr>
                            <th>{{ __('admin.media_picker.image') }}</th>
                            <th>{{ __('admin.image_guidance.recommended_size') }}</th>
                            <th>{{ __('admin.image_guidance.aspect_ratio') }}</th>
                            <th>{{ __('admin.image_guidance.min_size') }}</th>
                            <th>{{ __('admin.image_guidance.pro_tip') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($allProfiles as $profile)
                            <tr>
                                <td><strong>{{ $profile['name'] }}</strong></td>
                                <td dir="ltr" class="spu-image-guide__table-dim">{{ $profile['dimensions'] }}</td>
                                <td>{{ $profile['ratio'] }}</td>
                                <td dir="ltr">{{ $profile['min'] }}</td>
                                <td class="spu-image-guide__table-tip">{{ $profile['tip'] }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </details>
    @endif
</div>
