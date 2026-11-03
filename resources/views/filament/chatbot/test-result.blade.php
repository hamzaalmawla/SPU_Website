@php($result = is_array($result ?? null) ? $result : [])
<div class="spu-assistant__test">
    <p class="spu-assistant__test-question" dir="auto">{{ $result['question'] ?? '' }}</p>

    <section>
        <h3 class="spu-assistant__card-title">
            {{ __('admin_chatbot.test.answer') }}
            @if (($result['status'] ?? 'ok') !== 'ok')
                <x-filament::badge color="warning">{{ __('admin_chatbot.test.status', ['status' => $result['status'] ?? '']) }}</x-filament::badge>
            @endif
            @if (! empty($result['reason']))
                <x-filament::badge color="danger">{{ __('admin_chatbot.test.reason', ['reason' => __('admin_chatbot.test.reasons.'.$result['reason'])]) }}</x-filament::badge>
            @endif
        </h3>
        <div class="spu-assistant__preview" dir="auto">{{ $result['answer'] ?? '' }}</div>
    </section>

    <section>
        <h3 class="spu-assistant__card-title">{{ __('admin_chatbot.test.sources') }}</h3>
        @forelse (($result['passages'] ?? []) as $passage)
            <article class="spu-assistant__passage">
                <header>
                    <span class="spu-assistant__doc-title" dir="auto">{{ $passage['title'] }}</span>
                    @if ($passage['page'])
                        <span class="spu-assistant__muted">· {{ __('admin_chatbot.preview_page', ['page' => $passage['page']]) }}</span>
                    @endif
                    <x-filament::badge color="gray"><span dir="ltr">{{ number_format((float) $passage['score'], 3) }}</span></x-filament::badge>
                </header>
                @if ($passage['heading'])
                    <p class="spu-assistant__muted" dir="auto">{{ $passage['heading'] }}</p>
                @endif
                <p dir="auto">{{ $passage['excerpt'] }}…</p>
            </article>
        @empty
            <p class="spu-assistant__muted">{{ __('admin_chatbot.test.no_sources') }}</p>
        @endforelse
    </section>
</div>
