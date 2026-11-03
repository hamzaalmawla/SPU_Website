{{--
    The AI assistant: a floating 3D robot that opens a chat panel.

    Rendered only when the assistant is live (switched on, an OpenAI key
    saved and at least one knowledge file indexed). It starts `hidden` and
    resources/js/chatbot/chatbotWidget.js reveals it, so a visitor without
    JavaScript never sees a button that does nothing. The flat SVG robot shows
    until the WebGL one has loaded, and instead of it where WebGL is
    unavailable or the visitor saves data.
--}}
@if ($chatbot)
    @php($chatbotText = trans('chatbot.widget', [], $chatbot->locale))
    <div
        class="spu-chatbot"
        data-chatbot
        data-endpoint="{{ $chatbot->endpoint }}"
        data-locale="{{ $chatbot->locale }}"
        data-max-length="{{ $chatbot->maxMessageLength }}"
        data-welcome="{{ $chatbot->welcome }}"
        data-suggestions="{{ json_encode($chatbot->suggestions, JSON_UNESCAPED_UNICODE) }}"
        data-contact="{{ json_encode(array_values(array_filter([$chatbot->contactPhone, $chatbot->contactEmail])), JSON_UNESCAPED_UNICODE) }}"
        data-i18n="{{ json_encode([
            'thinking' => $chatbotText['thinking'],
            'sources' => $chatbotText['sources'],
            'page' => $chatbotText['page'],
            'copy' => $chatbotText['copy'],
            'copied' => $chatbotText['copied'],
            'you' => $chatbotText['you'],
            'assistant' => $chatbot->assistantName,
            'tooLong' => str_replace(':max', (string) $chatbot->maxMessageLength, $chatbotText['too_long']),
            'rateLimited' => $chatbotText['rate_limited'],
            'offline' => $chatbotText['offline'],
            'expired' => $chatbotText['expired'],
            'suggestions' => $chatbotText['suggestions'],
        ], JSON_UNESCAPED_UNICODE) }}"
        hidden
    >
        <div class="spu-chatbot__teaser" data-chatbot-teaser hidden>
            <button type="button" class="spu-chatbot__teaser-text" data-chatbot-teaser-open>{{ $chatbotText['teaser'] }}</button>
            <button type="button" class="spu-chatbot__teaser-close" data-chatbot-teaser-close aria-label="{{ $chatbotText['dismiss_teaser'] }}">
                <svg viewBox="0 0 20 20" aria-hidden="true" focusable="false"><path d="M5 5l10 10M15 5L5 15" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>
            </button>
        </div>

        <button
            type="button"
            class="spu-chatbot__launcher"
            data-chatbot-launcher
            aria-haspopup="dialog"
            aria-expanded="false"
            aria-controls="spu-chatbot-panel"
            aria-label="{{ $chatbotText['open'] }}"
        >
            <span class="spu-chatbot__stage" data-chatbot-stage aria-hidden="true">
                <svg class="spu-chatbot__robot-flat" viewBox="0 0 120 140" focusable="false">
                    <defs>
                        <linearGradient id="spu-bot-shell" x1="0" y1="0" x2="0" y2="1">
                            <stop offset="0" stop-color="#ffffff"/>
                            <stop offset="1" stop-color="#d9deef"/>
                        </linearGradient>
                        <radialGradient id="spu-bot-glow" cx="0.5" cy="0.5" r="0.5">
                            <stop offset="0" stop-color="#8be9ff" stop-opacity="0.9"/>
                            <stop offset="1" stop-color="#8be9ff" stop-opacity="0"/>
                        </radialGradient>
                    </defs>
                    <ellipse cx="60" cy="132" rx="30" ry="5" fill="#202759" opacity="0.18"/>
                    <line x1="60" y1="14" x2="60" y2="26" stroke="#e2b864" stroke-width="4" stroke-linecap="round"/>
                    <circle cx="60" cy="11" r="6" fill="#e2b864"/>
                    <rect x="14" y="44" width="10" height="20" rx="5" fill="#e2b864"/>
                    <rect x="96" y="44" width="10" height="20" rx="5" fill="#e2b864"/>
                    <rect x="22" y="24" width="76" height="62" rx="24" fill="url(#spu-bot-shell)"/>
                    <rect x="31" y="35" width="58" height="40" rx="16" fill="#18204a"/>
                    <rect class="spu-chatbot__flat-eye" x="44" y="45" width="9" height="15" rx="4.5" fill="#8be9ff"/>
                    <rect class="spu-chatbot__flat-eye" x="67" y="45" width="9" height="15" rx="4.5" fill="#8be9ff"/>
                    <path d="M53 66q7 6 14 0" fill="none" stroke="#8be9ff" stroke-width="3" stroke-linecap="round"/>
                    <rect x="34" y="90" width="52" height="36" rx="18" fill="url(#spu-bot-shell)"/>
                    <circle cx="60" cy="107" r="8" fill="#18204a"/>
                    <circle cx="60" cy="107" r="4" fill="url(#spu-bot-glow)"/>
                </svg>
            </span>
            <span class="spu-chatbot__pulse" aria-hidden="true"></span>
        </button>

        <section
            id="spu-chatbot-panel"
            class="spu-chatbot__panel"
            role="dialog"
            aria-modal="false"
            aria-labelledby="spu-chatbot-title"
            aria-describedby="spu-chatbot-status"
            data-chatbot-panel
            hidden
        >
            <header class="spu-chatbot__header">
                <span class="spu-chatbot__avatar" data-chatbot-avatar aria-hidden="true"></span>
                <div class="spu-chatbot__heading">
                    <h2 id="spu-chatbot-title" class="spu-chatbot__title" tabindex="-1">{{ $chatbot->assistantName }}</h2>
                    <p id="spu-chatbot-status" class="spu-chatbot__status"><span class="spu-chatbot__status-dot" aria-hidden="true"></span><span class="spu-chatbot__status-text">{{ $chatbotText['status'] }}</span></p>
                </div>
                <button type="button" class="spu-chatbot__icon-button" data-chatbot-reset aria-label="{{ $chatbotText['new_chat'] }}" title="{{ $chatbotText['new_chat'] }}">
                    <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M4 12a8 8 0 1 0 2.4-5.7M4 4v4h4" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
                </button>
                <button type="button" class="spu-chatbot__icon-button" data-chatbot-close aria-label="{{ $chatbotText['close'] }}" title="{{ $chatbotText['close'] }}">
                    <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M6 6l12 12M18 6L6 18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>
                </button>
            </header>

            <div class="spu-chatbot__log" role="log" aria-live="polite" aria-relevant="additions" aria-label="{{ $chatbot->assistantName }}" tabindex="0" data-chatbot-log></div>

            <form class="spu-chatbot__composer" data-chatbot-form novalidate>
                <label class="spu-chatbot__sr" for="spu-chatbot-input">{{ $chatbotText['label'] }}</label>
                <textarea
                    id="spu-chatbot-input"
                    class="spu-chatbot__input"
                    rows="1"
                    dir="auto"
                    maxlength="{{ $chatbot->maxMessageLength }}"
                    placeholder="{{ $chatbotText['placeholder'] }}"
                    enterkeyhint="send"
                    autocomplete="off"
                    data-chatbot-input
                ></textarea>
                <button type="submit" class="spu-chatbot__send" data-chatbot-send aria-label="{{ $chatbotText['send'] }}" title="{{ $chatbotText['send'] }}">
                    <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M4 12l16-8-6 16-2.5-6.5L4 12z" fill="currentColor"/></svg>
                </button>
            </form>
            <p class="spu-chatbot__disclaimer">{{ $chatbotText['disclaimer'] }}</p>
        </section>
    </div>
@endif
