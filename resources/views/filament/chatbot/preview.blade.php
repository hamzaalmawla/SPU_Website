@if (trim($text) === '')
    <p class="spu-assistant__muted">{{ __('admin_chatbot.actions.preview_empty') }}</p>
@else
    <div class="spu-assistant__preview" dir="auto">{{ $text }}</div>
@endif
