{{--
    The AI assistant: a small 3D host (navy suit, ruby brooch, round glasses)
    standing by the page corner, who opens a chat panel.

    Rendered only when the assistant is live (switched on, an OpenAI key
    saved and at least one knowledge file indexed). The launcher shows from
    the first paint wherever JavaScript runs (html.js, see chatbot.css), so
    it never appears late; without JavaScript it is not shown at all. The
    widget's script and the panel's styles load on the visitor's first
    interaction (resources/js/chatbot/bootstrap.js). The drawn host (defined
    once below, used by the launcher and the panel's avatar) shows until the
    WebGL one has loaded, and instead of it where WebGL is unavailable or the
    visitor saves data.
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
        @if ($chatbot->voiceEnabled)
            data-voice="{{ json_encode([
                'transcribe' => $chatbot->transcriptionEndpoint,
                'speech' => $chatbot->speechEndpoint,
                'live' => $chatbot->liveTranscriptionEndpoint,
                'server' => $chatbot->serverSpeech,
                'maxSeconds' => $chatbot->maxRecordingSeconds,
            ], JSON_UNESCAPED_SLASHES) }}"
        @endif
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
            'speak' => $chatbotText['speak'],
            'stopListening' => $chatbotText['stop_listening'],
            'cancelVoice' => $chatbotText['cancel_voice'],
            'listening' => $chatbotText['listening'],
            'heard' => $chatbotText['heard'],
            'transcribing' => $chatbotText['transcribing'],
            'speaking' => $chatbotText['speaking'],
            'preparingVoice' => $chatbotText['preparing_voice'],
            'listen' => $chatbotText['listen'],
            'stopAudio' => $chatbotText['stop_audio'],
            'voiceEnded' => $chatbotText['voice_ended'],
            'micDenied' => $chatbotText['mic_denied'],
            'micMissing' => $chatbotText['mic_missing'],
            'micUnavailable' => $chatbotText['mic_unavailable'],
            'noSpeech' => $chatbotText['no_speech'],
        ], JSON_UNESCAPED_UNICODE) }}"
    >
        <svg class="spu-chatbot__sprite" width="0" height="0" aria-hidden="true" focusable="false">
            <defs>
                <linearGradient id="spu-host-skin" x1="0" y1="0" x2="0" y2="1">
                    <stop offset="0" stop-color="#f4c29c"/>
                    <stop offset="1" stop-color="#e09f77"/>
                </linearGradient>
                <linearGradient id="spu-host-suit" x1="0" y1="0" x2="0" y2="1">
                    <stop offset="0" stop-color="#2d3c80"/>
                    <stop offset="1" stop-color="#1a2350"/>
                </linearGradient>
                <linearGradient id="spu-host-hair" x1="0" y1="0" x2="0.3" y2="1">
                    <stop offset="0" stop-color="#3a2f2b"/>
                    <stop offset="0.45" stop-color="#17110e"/>
                    <stop offset="1" stop-color="#0f0b09"/>
                </linearGradient>
                <radialGradient id="spu-host-stand" cx="0.5" cy="0.5" r="0.5">
                    <stop offset="0" stop-color="#7fe6ff" stop-opacity="0.45"/>
                    <stop offset="1" stop-color="#7fe6ff" stop-opacity="0"/>
                </radialGradient>
                <g id="spu-host">
                    <!-- Stand and shadow -->
                    <ellipse cx="60" cy="131" rx="36" ry="7" fill="url(#spu-host-stand)"/>
                    <ellipse cx="60" cy="131" rx="30" ry="5" fill="none" stroke="#7fe6ff" stroke-width="1.4" opacity="0.8"/>
                    <ellipse cx="60" cy="131" rx="20" ry="3" fill="#202759" opacity="0.22"/>
                    <!-- Legs and shoes -->
                    <rect x="46.5" y="110" width="10" height="17" rx="5" fill="#1c2654"/>
                    <rect x="63.5" y="110" width="10" height="17" rx="5" fill="#1c2654"/>
                    <ellipse cx="51" cy="128" rx="7.5" ry="4.2" fill="#15161c"/>
                    <ellipse cx="69" cy="128" rx="7.5" ry="4.2" fill="#15161c"/>
                    <ellipse cx="49.5" cy="126.6" rx="3" ry="1.1" fill="#ffffff" opacity="0.35"/>
                    <ellipse cx="67.5" cy="126.6" rx="3" ry="1.1" fill="#ffffff" opacity="0.35"/>
                    <!-- Arms -->
                    <rect x="29" y="78" width="11" height="30" rx="5.5" fill="url(#spu-host-suit)" transform="rotate(8 34.5 79)"/>
                    <rect x="80" y="78" width="11" height="30" rx="5.5" fill="url(#spu-host-suit)" transform="rotate(-8 85.5 79)"/>
                    <rect x="30.5" y="104.5" width="9" height="3" rx="1.5" fill="#f4f5f9" transform="rotate(8 34.5 79)"/>
                    <rect x="80.5" y="104.5" width="9" height="3" rx="1.5" fill="#f4f5f9" transform="rotate(-8 85.5 79)"/>
                    <circle cx="32" cy="111" r="5.2" fill="url(#spu-host-skin)"/>
                    <circle cx="88" cy="111" r="5.2" fill="url(#spu-host-skin)"/>
                    <!-- Jacket -->
                    <path d="M33 90c0-10 6-15 15-16h24c9 1 15 6 15 16v14c0 11-11 16-27 16s-27-5-27-16z" fill="url(#spu-host-suit)"/>
                    <!-- Shirt, collar and lapels -->
                    <path d="M51 74h18l-9 26z" fill="#ffffff"/>
                    <path d="M51 74h7.5l-3.5 8.5z" fill="#fbfbfd" stroke="#d6dae6" stroke-width="0.6" stroke-linejoin="round"/>
                    <path d="M69 74h-7.5l3.5 8.5z" fill="#fbfbfd" stroke="#d6dae6" stroke-width="0.6" stroke-linejoin="round"/>
                    <path d="M51 74l-7.5 3.2 1.8 3.4-2.6 2.6 16.3 17.8 1-1.6z" fill="#16204c"/>
                    <path d="M69 74l7.5 3.2-1.8 3.4 2.6 2.6-16.3 17.8-1-1.6z" fill="#16204c"/>
                    <circle cx="60" cy="105.5" r="1.6" fill="#121a40"/>
                    <circle cx="60" cy="112" r="1.6" fill="#121a40"/>
                    <path d="M68.5 97.4l8-.6" stroke="#0c1233" stroke-width="1.1" stroke-linecap="round"/>
                    <!-- The brooch and its chain -->
                    <path d="M69.8 90q1.6 6.4 6.6 6.9M69.8 90q.2 8.6 6.6 6.9" fill="none" stroke="#e2b864" stroke-width="0.7" stroke-linecap="round"/>
                    <circle cx="69.8" cy="86.6" r="3.4" fill="none" stroke="#e2b864" stroke-width="1.5" stroke-dasharray="1.1 0.7"/>
                    <ellipse cx="69.8" cy="86.6" rx="1.8" ry="2.2" fill="#d3102e"/>
                    <circle cx="69.2" cy="85.8" r="0.6" fill="#ffffff" opacity="0.85"/>
                    <!-- Neck, ears, head -->
                    <rect x="54" y="64" width="12" height="11" rx="4" fill="#d8946d"/>
                    <ellipse cx="33.5" cy="45" rx="3.6" ry="6.3" fill="#e3a27a"/>
                    <ellipse cx="86.5" cy="45" rx="3.6" ry="6.3" fill="#e3a27a"/>
                    <path d="M33 41c0-16 12-25 27-25s27 9 27 25c0 15-9 28-27 28S33 56 33 41z" fill="url(#spu-host-skin)"/>
                    <!-- Hair: a swept-up quiff with a parting on his right -->
                    <path d="M33.4 41.5C31 27 38.5 13 54.5 9.5 70 6.4 84.5 13.6 87.3 28l-.2 12c-2-6.7-5.8-10.3-11.4-11-7.3-.9-12.3-3.5-17.8-3.8-8.6-.4-17.8 4.7-24.5 16.3z" fill="url(#spu-host-hair)"/>
                    <path d="M43.5 16.5c6-4.6 15-6.1 23-3.6M41 21c7.5-5 17.3-6.6 27-3.4M47 25.5c6.2-3 13-3.8 20.5-2" fill="none" stroke="#5a4c47" stroke-width="0.9" stroke-linecap="round" opacity="0.7"/>
                    <path d="M44.5 13.2c4-2 9-2.8 13.5-2.2" fill="none" stroke="#ffffff" stroke-width="1.4" stroke-linecap="round" opacity="0.35"/>
                    <!-- Cheeks -->
                    <circle cx="41" cy="53.5" r="4.2" fill="#ff8c74" opacity="0.28"/>
                    <circle cx="79" cy="53.5" r="4.2" fill="#ff8c74" opacity="0.28"/>
                    <!-- Eyebrows -->
                    <path d="M41.5 34.5c4.4-3.5 9.6-3.9 14.4-1.6" fill="none" stroke="#18110e" stroke-width="3" stroke-linecap="round"/>
                    <path d="M78.5 34.5c-4.4-3.5-9.6-3.9-14.4-1.6" fill="none" stroke="#18110e" stroke-width="3" stroke-linecap="round"/>
                    <!-- Eyes -->
                    <g class="spu-chatbot__flat-eye">
                        <ellipse cx="49" cy="44" rx="6.2" ry="6.9" fill="#fbfbfa"/>
                        <circle cx="49" cy="44.6" r="4.4" fill="#6a3a1a"/>
                        <circle cx="49" cy="44.6" r="2.3" fill="#120a06"/>
                        <circle cx="50.5" cy="42.9" r="1.25" fill="#ffffff"/>
                        <circle cx="47.6" cy="46" r="0.6" fill="#ffffff"/>
                    </g>
                    <g class="spu-chatbot__flat-eye">
                        <ellipse cx="71" cy="44" rx="6.2" ry="6.9" fill="#fbfbfa"/>
                        <circle cx="71" cy="44.6" r="4.4" fill="#6a3a1a"/>
                        <circle cx="71" cy="44.6" r="2.3" fill="#120a06"/>
                        <circle cx="72.5" cy="42.9" r="1.25" fill="#ffffff"/>
                        <circle cx="69.6" cy="46" r="0.6" fill="#ffffff"/>
                    </g>
                    <!-- Glasses -->
                    <g fill="none" stroke="#7d838e" stroke-width="1.3">
                        <circle cx="49" cy="44" r="9.4" fill="#eaf3ff" fill-opacity="0.14"/>
                        <circle cx="71" cy="44" r="9.4" fill="#eaf3ff" fill-opacity="0.14"/>
                        <path d="M58.4 43.4c1-1.2 2.2-1.2 3.2 0M39.6 43l-5.2-1.4M80.4 43l5.2-1.4" stroke-linecap="round"/>
                    </g>
                    <path d="M43.5 39.5l4-3.4" stroke="#ffffff" stroke-width="1.2" stroke-linecap="round" opacity="0.6"/>
                    <path d="M65.5 39.5l4-3.4" stroke="#ffffff" stroke-width="1.2" stroke-linecap="round" opacity="0.6"/>
                    <!-- Nose, mustache, smile, goatee -->
                    <ellipse cx="60" cy="51" rx="3.2" ry="2.6" fill="#e09b74"/>
                    <path d="M59 54.6c-4.6-.6-9.6.8-12.6 2.9-1.6 1.1-3.2.9-3.9-.6.8 3 5.2 4.4 9.6 3.1 2.9-.8 5.2-2 6.9-2.2zM61 54.6c4.6-.6 9.6.8 12.6 2.9 1.6 1.1 3.2.9 3.9-.6-.8 3-5.2 4.4-9.6 3.1-2.9-.8-5.2-2-6.9-2.2z" fill="#1a120f"/>
                    <path d="M51.5 61.6q8.5 4.6 17 0" fill="none" stroke="#a8483a" stroke-width="1.9" stroke-linecap="round"/>
                    <path d="M58.9 63.6h2.2l-.1 3.8c2.9 0 5.6-.5 7.2-1.4-.9 2.9-4.3 4.4-8.2 4.4s-7.3-1.5-8.2-4.4c1.6.9 4.3 1.4 7.2 1.4z" fill="#1d1511"/>
                </g>
            </defs>
        </svg>

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
                <svg class="spu-chatbot__host-flat" viewBox="0 0 120 140" focusable="false"><use href="#spu-host"/></svg>
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
                <span class="spu-chatbot__avatar" data-chatbot-avatar aria-hidden="true"><svg class="spu-chatbot__avatar-flat" viewBox="27 6 66 66" focusable="false"><use href="#spu-host"/></svg></span>
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
                <div class="spu-chatbot__field">
                    {{-- The page's direction, switched by the script to the
                         direction of the first letter typed (dir="auto" would
                         start an empty field left-to-right on the Arabic site). --}}
                    <textarea
                        id="spu-chatbot-input"
                        class="spu-chatbot__input"
                        rows="1"
                        dir="{{ $chatbot->locale === 'ar' ? 'rtl' : 'ltr' }}"
                        maxlength="{{ $chatbot->maxMessageLength }}"
                        placeholder="{{ $chatbotText['placeholder'] }}"
                        enterkeyhint="send"
                        autocomplete="off"
                        spellcheck="true"
                        data-chatbot-input
                    ></textarea>
                    <span class="spu-chatbot__counter" data-chatbot-counter aria-live="polite" hidden></span>
                </div>
                @if ($chatbot->voiceEnabled)
                    {{-- Shown by the script where the browser can record. --}}
                    <button type="button" class="spu-chatbot__mic" data-chatbot-mic aria-label="{{ $chatbotText['speak'] }}" title="{{ $chatbotText['speak'] }}" aria-pressed="false" hidden>
                        <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><rect x="9" y="3" width="6" height="11" rx="3" fill="currentColor"/><path d="M6 11a6 6 0 0 0 12 0M12 17v4M9 21h6" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>
                    </button>
                    <span class="spu-chatbot__sr" data-chatbot-voice-announcer role="status" aria-live="polite"></span>
                    {{-- Covers the composer while the assistant listens, writes the question down or reads aloud. --}}
                    <div class="spu-chatbot__voice" data-chatbot-voice hidden>
                        <span class="spu-chatbot__voice-meter" data-chatbot-voice-meter aria-hidden="true"><i></i><i></i><i></i><i></i><i></i></span>
                        <span class="spu-chatbot__voice-text">
                            <span class="spu-chatbot__voice-status" data-chatbot-voice-status role="status" aria-live="polite"></span>
                            <span class="spu-chatbot__voice-time" data-chatbot-voice-time dir="ltr" aria-hidden="true"></span>
                        </span>
                        <button type="button" class="spu-chatbot__voice-cancel" data-chatbot-voice-cancel aria-label="{{ $chatbotText['cancel_voice'] }}" title="{{ $chatbotText['cancel_voice'] }}">
                            <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M6 6l12 12M18 6L6 18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>
                        </button>
                        <button type="button" class="spu-chatbot__voice-stop" data-chatbot-voice-stop>
                            <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><rect x="7" y="7" width="10" height="10" rx="2" fill="currentColor"/></svg>
                            <span data-chatbot-voice-stop-label>{{ $chatbotText['stop_listening'] }}</span>
                        </button>
                    </div>
                @endif
                <button type="submit" class="spu-chatbot__send" data-chatbot-send aria-label="{{ $chatbotText['send'] }}" title="{{ $chatbotText['send'] }}">
                    <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M4 12l16-8-6 16-2.5-6.5L4 12z" fill="currentColor"/></svg>
                </button>
            </form>
            <p class="spu-chatbot__disclaimer">{{ $chatbotText['disclaimer'] }}</p>
        </section>
    </div>
@endif
