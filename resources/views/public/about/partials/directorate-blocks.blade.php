{{--
    The page body an editor built for a directorate, block by block: headings,
    text boxes, images and buttons. Buttons next to each other share one row.
    A button to one of our forms opens that form in a dialog on this page; the
    form is mounted only while its dialog is open, so several forms can sit on
    one page without sharing state.
--}}
@php
    $isArabic = $locale === 'ar';
    $groups = [];
    foreach ($blocks as $block) {
        $lastIndex = array_key_last($groups);
        if ($block->type === 'button' && $lastIndex !== null && $groups[$lastIndex]['type'] === 'buttons') {
            $groups[$lastIndex]['items'][] = $block;
        } else {
            $groups[] = $block->type === 'button' ? ['type' => 'buttons', 'items' => [$block]] : ['type' => $block->type, 'block' => $block];
        }
    }
@endphp
<section class="dir-blocks-section" aria-label="{{ $isArabic ? 'محتوى المديرية' : 'About the directorate' }}">
    <div class="container mx-auto max-w-6xl px-6">
        <div class="dir-blocks">
            @foreach ($groups as $groupIndex => $group)
                @switch($group['type'])
                    @case('heading')
                        <h2 class="dir-section-title dir-block-heading reveal reveal-up" dir="auto">{{ $group['block']->text }}</h2>
                        @break
                    @case('text')
                        <x-public.rich-text class="dir-block-text reveal reveal-up" dir="auto" :text="$group['block']->text" />
                        @break
                    @case('image')
                        <figure class="dir-block-figure reveal reveal-up">
                            <img src="{{ $group['block']->image }}" alt="{{ $group['block']->alt }}" class="dir-block-image" loading="lazy" decoding="async">
                            @if ($group['block']->caption !== '')
                                <figcaption class="dir-block-caption" dir="auto">{{ $group['block']->caption }}</figcaption>
                            @endif
                        </figure>
                        @break
                    @case('buttons')
                        <div class="dir-block-buttons reveal reveal-up">
                            @foreach ($group['items'] as $buttonIndex => $button)
                                @if ($button->formKey !== null)
                                    @php($dialogId = 'directorate-form-'.$groupIndex.'-'.$buttonIndex)
                                    <div class="contents" x-data="directorateFormDialog" data-form-key="{{ $button->formKey }}" x-effect="watchForm">
                                        <button type="button" class="dir-btn dir-btn-primary" aria-haspopup="dialog" aria-controls="{{ $dialogId }}" x-on:click="show" dir="auto">{{ $button->label }}</button>
                                        <dialog id="{{ $dialogId }}" x-ref="dialog" class="dir-form-dialog font-hacen" dir="{{ $isArabic ? 'rtl' : 'ltr' }}" aria-labelledby="{{ $dialogId }}-title" x-on:close="onClose" x-on:click="closeOnBackdrop">
                                            <div class="dir-form-dialog-panel">
                                                <div class="dir-form-dialog-head">
                                                    <h2 id="{{ $dialogId }}-title" class="dir-form-dialog-title" dir="auto">{{ $button->label }}</h2>
                                                    <button type="button" class="dir-form-dialog-close" x-on:click="hide" aria-label="{{ $isArabic ? 'إغلاق' : 'Close' }}">
                                                        <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M18 6 6 18M6 6l12 12"></path></svg>
                                                    </button>
                                                </div>
                                                <template x-if="open">
                                                    <div x-data="dynamicFormShell()" data-form-id="{{ $button->formKey }}" data-locale="{{ $locale }}" data-preview="{{ $isPreview ? '1' : '0' }}">
                                                        @include('public.forms.dynamic-form', ['formKey' => $button->formKey])
                                                    </div>
                                                </template>
                                            </div>
                                        </dialog>
                                    </div>
                                @else
                                    <a href="{{ $button->url }}" class="dir-btn dir-btn-primary" dir="auto" @if ($button->isExternal) target="_blank" rel="noopener noreferrer" @endif>
                                        {{ $button->label }}
                                        @if ($button->isExternal)
                                            <svg class="h-4 w-4 rtl:-scale-x-100" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5h5v5M19 5l-8 8M10 5H6a1 1 0 00-1 1v12a1 1 0 001 1h12a1 1 0 001-1v-4"/></svg>
                                        @endif
                                    </a>
                                @endif
                            @endforeach
                        </div>
                        @break
                @endswitch
            @endforeach
        </div>
    </div>
</section>
