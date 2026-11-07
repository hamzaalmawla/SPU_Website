import '../../css/frontend/chatbot-panel.css';
import { markdownToText, renderMarkdown } from './chatMarkdown.js';
import { canRecord, failureKey, historyFor, loadMemory, loadMessages, revealSteps, sanitizeSources, saveMemory, saveMessages } from './chatState.js';

/**
 * The public AI assistant: launcher, teaser bubble, chat panel and the 3D
 * host. Plain DOM code (no Alpine) so the whole widget is one lazily loaded
 * chunk, and the Three.js scene is a second one loaded when the browser is idle.
 *
 * Voice (chatVoice.js, a third chunk loaded on the first voice control): the
 * microphone starts a voice conversation. The host listens, the question is
 * written down and sent like a typed one, the answer is read aloud, and the
 * host listens again, until the visitor stops, types, closes the panel or
 * says nothing. Any answer can also be played with its Listen button.
 */

const REQUEST_TIMEOUT_MS = 60_000;
const TEASER_DELAY_MS = 4_500;
const TEASER_VISIBLE_MS = 14_000;
// Where the panel is a full-screen sheet (see chatbot.css).
const MOBILE_QUERY = '(max-width: 640px), (max-height: 560px)';

const ICON_SOURCES = '<svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M4 5a2 2 0 0 1 2-2h9l5 5v11a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2z" fill="none" stroke="currentColor" stroke-width="2" stroke-linejoin="round"/><path d="M8 12h8M8 16h5" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>';
const ICON_LINK = '<svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M10 14a4 4 0 0 0 5.7 0l3-3a4 4 0 0 0-5.7-5.7l-1 1M14 10a4 4 0 0 0-5.7 0l-3 3a4 4 0 0 0 5.7 5.7l1-1" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>';
const ICON_DOCUMENT = '<svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M6 3h8l4 4v14H6z" fill="none" stroke="currentColor" stroke-width="2" stroke-linejoin="round"/></svg>';
const ICON_LISTEN = '<svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M4 9v6h4l5 4V5L8 9z" fill="currentColor"/><path d="M16 8.5a5 5 0 0 1 0 7M18.5 6a8.5 8.5 0 0 1 0 12" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>';
const ICON_STOP = '<svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><rect x="6" y="6" width="12" height="12" rx="2" fill="currentColor"/></svg>';
const ICON_COPY = '<svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><rect x="8" y="8" width="12" height="12" rx="2" fill="none" stroke="currentColor" stroke-width="2"/><path d="M16 8V6a2 2 0 0 0-2-2H6a2 2 0 0 0-2 2v8a2 2 0 0 0 2 2h2" fill="none" stroke="currentColor" stroke-width="2"/></svg>';

const instances = new WeakMap();

/**
 * Start the widget on its root (once), and return it. Called by
 * bootstrap.js on the visitor's first interaction; `awake` when that
 * interaction already happened, so the host and teaser do not wait for
 * another one.
 */
export function initChatbot(root = document.querySelector('[data-chatbot]'), { awake = false } = {}) {
    if (!root) {
        return null;
    }

    let widget = instances.get(root);
    if (!widget) {
        widget = new ChatbotWidget(root);
        instances.set(root, widget);
        widget.start();
    }

    if (awake) {
        widget.wake();
    }

    return widget;
}

class ChatbotWidget {
    constructor(root) {
        this.root = root;
        this.locale = root.dataset.locale === 'en' ? 'en' : 'ar';
        this.endpoint = root.dataset.endpoint;
        this.maxLength = Number.parseInt(root.dataset.maxLength ?? '1000', 10) || 1000;
        this.welcome = root.dataset.welcome ?? '';
        this.suggestions = parseJson(root.dataset.suggestions, []);
        this.contact = parseJson(root.dataset.contact, []);
        this.text = parseJson(root.dataset.i18n, {});

        this.launcher = root.querySelector('[data-chatbot-launcher]');
        this.stage = root.querySelector('[data-chatbot-stage]');
        this.panel = root.querySelector('[data-chatbot-panel]');
        this.avatar = root.querySelector('[data-chatbot-avatar]');
        this.log = root.querySelector('[data-chatbot-log]');
        this.form = root.querySelector('[data-chatbot-form]');
        this.input = root.querySelector('[data-chatbot-input]');
        this.sendButton = root.querySelector('[data-chatbot-send]');
        this.teaser = root.querySelector('[data-chatbot-teaser]');
        this.counter = root.querySelector('[data-chatbot-counter]');
        this.pageDirection = this.locale === 'ar' ? 'rtl' : 'ltr';
        this.title = root.querySelector('#spu-chatbot-title');

        this.storage = safeSessionStorage();
        this.messages = loadMessages(this.storage, this.locale);
        this.memory = loadMemory(this.storage, this.locale);

        this.voiceConfig = parseJson(root.dataset.voice, null);
        this.micButton = root.querySelector('[data-chatbot-mic]');
        this.voiceBar = root.querySelector('[data-chatbot-voice]');
        this.voiceStatus = root.querySelector('[data-chatbot-voice-status]');
        this.voiceTime = root.querySelector('[data-chatbot-voice-time]');
        this.voiceMeter = root.querySelector('[data-chatbot-voice-meter]');
        this.voiceStop = root.querySelector('[data-chatbot-voice-stop]');
        this.voiceStopLabel = root.querySelector('[data-chatbot-voice-stop-label]');
        this.voiceCancel = root.querySelector('[data-chatbot-voice-cancel]');
        this.canTalk = Boolean(this.voiceConfig?.transcribe && this.micButton && this.voiceBar && canRecord(window));
        this.canListen = Boolean(this.voiceConfig && (this.voiceConfig.server || 'speechSynthesis' in window));
        this.voiceMode = 'off';
        this.voiceSession = false;
        this.voiceTurns = 0;
        this.recorder = null;
        this.player = null;
        this.audioContext = null;
        this.voiceModule = null;
        this.playingButton = null;
        this.playingMessage = null;
        this.announcer = root.querySelector('[data-chatbot-voice-announcer]');
        this.busy = false;
        this.host = null;
        this.hostLoading = null;
        this.reducedMotion = matchMedia('(prefers-reduced-motion: reduce)');
        this.mobile = matchMedia(MOBILE_QUERY);
    }

    start() {
        this.root.hidden = false;
        if (this.canTalk) {
            this.micButton.hidden = false;
        }
        this.renderLog();
        this.bind();
        this.awaitFirstInteraction();

        if (this.storage?.getItem(`${storageKeyPrefix(this.locale)}:open`) === '1') {
            this.open({ focus: false });
        }
    }

    bind() {
        this.launcher.addEventListener('click', () => this.open());
        this.launcher.addEventListener('pointerenter', () => {
            this.wake();
            this.loadHost().then((host) => host?.wave());
        });
        this.launcher.addEventListener('focus', () => {
            this.wake();
            this.loadHost();
        }, { once: true });
        this.launcher.addEventListener('touchstart', () => {
            this.wake();
            this.loadHost();
        }, { once: true, passive: true });

        this.root.querySelector('[data-chatbot-close]').addEventListener('click', () => this.close());
        this.root.querySelector('[data-chatbot-reset]').addEventListener('click', () => this.reset());
        this.root.querySelector('[data-chatbot-teaser-open]').addEventListener('click', () => this.open());
        this.root.querySelector('[data-chatbot-teaser-close]').addEventListener('click', () => this.hideTeaser(true));

        this.form.addEventListener('submit', (event) => {
            event.preventDefault();
            this.send(this.input.value);
        });

        this.input.addEventListener('keydown', (event) => {
            if (event.key === 'Enter' && !event.shiftKey && !event.isComposing) {
                event.preventDefault();
                this.send(this.input.value);
            }
        });
        this.input.addEventListener('input', () => {
            this.autosize();
            this.syncDirection();
            this.syncCounter();
            // Typing takes over from talking.
            if (this.voiceSession && this.input.value.trim() !== '') {
                this.endVoice();
            }
        });

        if (this.canTalk) {
            this.micButton.addEventListener('click', () => this.onMicPress());
            this.voiceStop.addEventListener('click', () => this.onVoiceStop());
            this.voiceCancel.addEventListener('click', () => this.endVoice({ announce: true }));
            document.addEventListener('visibilitychange', () => {
                if (document.hidden && this.voiceMode === 'listening') {
                    this.endVoice();
                }
            });
        }

        this.panel.addEventListener('keydown', (event) => {
            if (event.key === 'Escape') {
                event.stopPropagation();
                // Escape first stops talking or reading, then closes.
                if (this.voiceMode !== 'off' || this.playingMessage) {
                    this.endVoice({ announce: true });
                    this.micButton?.focus({ preventScroll: true });

                    return;
                }
                this.close();
            } else if (event.key === 'Tab' && this.mobile.matches) {
                this.trapFocus(event);
            }
        });

        // Keep the composer above the on-screen keyboard on phones.
        if (window.visualViewport) {
            const update = () => {
                this.root.style.setProperty('--spu-chat-viewport', `${Math.round(window.visualViewport.height)}px`);
            };
            window.visualViewport.addEventListener('resize', update);
            update();
        }

        this.mobile.addEventListener?.('change', () => this.syncModal());

        window.addEventListener('pointermove', (event) => this.host?.lookAt(event.clientX, event.clientY), { passive: true });
    }

    /* ── Panel ─────────────────────────────────────────────────────────── */

    open({ focus = true } = {}) {
        if (!this.panel.hidden) {
            return;
        }

        this.hideTeaser(true);
        this.panel.hidden = false;
        this.root.classList.add('is-open');
        this.launcher.setAttribute('aria-expanded', 'true');
        this.syncModal();
        this.storage?.setItem(`${storageKeyPrefix(this.locale)}:open`, '1');

        this.loadHost().then(() => this.host?.mount(this.avatar));
        this.scrollToEnd(false);

        if (focus) {
            requestAnimationFrame(() => {
                // A phone's keyboard would cover half the panel on open; there
                // the title takes focus, and the field when tapped.
                (window.matchMedia('(pointer: coarse)').matches ? this.title : this.input).focus({ preventScroll: true });
            });
        }
    }

    close() {
        if (this.panel.hidden) {
            return;
        }

        this.endVoice();
        this.panel.hidden = true;
        this.root.classList.remove('is-open');
        this.launcher.setAttribute('aria-expanded', 'false');
        document.documentElement.classList.remove('spu-chatbot-locked');
        this.storage?.removeItem(`${storageKeyPrefix(this.locale)}:open`);
        this.host?.mount(this.stage);
        this.launcher.focus({ preventScroll: true });
    }

    syncModal() {
        const modal = this.mobile.matches && !this.panel.hidden;
        this.panel.setAttribute('aria-modal', modal ? 'true' : 'false');
        document.documentElement.classList.toggle('spu-chatbot-locked', modal);
    }

    trapFocus(event) {
        const focusable = [...this.panel.querySelectorAll('button:not([disabled]), textarea:not([disabled]), a[href], [tabindex="0"]')]
            .filter((element) => element.offsetParent !== null);
        if (!focusable.length) {
            return;
        }
        const first = focusable[0];
        const last = focusable[focusable.length - 1];
        if (event.shiftKey && document.activeElement === first) {
            event.preventDefault();
            last.focus();
        } else if (!event.shiftKey && document.activeElement === last) {
            event.preventDefault();
            first.focus();
        }
    }

    reset() {
        this.endVoice();
        this.messages = [];
        this.memory = null;
        saveMessages(this.storage, this.locale, this.messages);
        saveMemory(this.storage, this.locale, null);
        this.renderLog();
        this.host?.setState('happy');
        this.input.value = '';
        this.autosize();
        this.syncDirection();
        this.syncCounter();
        this.input.focus({ preventScroll: true });
    }

    /* ── Teaser ────────────────────────────────────────────────────────── */

    scheduleTeaser() {
        const key = `${storageKeyPrefix(this.locale)}:teaser`;
        if (this.storage?.getItem(key) === '1' || this.messages.length) {
            return;
        }

        this.teaserTimer = window.setTimeout(() => {
            if (!this.panel.hidden) {
                return;
            }
            this.teaser.hidden = false;
            this.root.classList.add('has-teaser');
            this.host?.wave();
            this.teaserTimer = window.setTimeout(() => this.hideTeaser(false), TEASER_VISIBLE_MS);
        }, TEASER_DELAY_MS);
    }

    hideTeaser(remember) {
        window.clearTimeout(this.teaserTimer);
        this.teaser.hidden = true;
        this.root.classList.remove('has-teaser');
        if (remember) {
            this.storage?.setItem(`${storageKeyPrefix(this.locale)}:teaser`, '1');
        }
    }

    /* ── Conversation ──────────────────────────────────────────────────── */

    /**
     * Ask a question. `spoken` when it came from the microphone: the answer
     * is then read aloud and, in a voice conversation, the host listens again.
     */
    async send(rawText, { spoken = false } = {}) {
        const text = String(rawText ?? '').trim();
        if (this.busy || text === '') {
            return;
        }
        if (!spoken) {
            this.endVoice();
        }
        if (text.length > this.maxLength) {
            this.appendNotice(this.text.tooLong);

            return;
        }

        const history = historyFor(this.messages);
        this.pushMessage({ role: 'user', content: text, status: 'ok', sources: [] });
        this.input.value = '';
        this.autosize();
        this.syncDirection();
        this.syncCounter();
        this.setBusy(true);

        const typing = this.appendTyping();
        let reply;

        try {
            reply = await this.request(text, history, { spoken });
        } finally {
            typing.remove();
        }

        const message = {
            role: 'assistant',
            content: reply.answer,
            status: reply.status,
            sources: reply.status === 'ok' ? reply.sources : [],
            ...(reply.signature ? { signature: reply.signature } : {}),
            ...(reply.speech ? { speech: reply.speech } : {}),
        };
        this.messages.push(message);
        saveMessages(this.storage, this.locale, this.messages);
        // The notes on the conversation so far; a reply without them keeps the last ones.
        if (reply.memory) {
            this.memory = reply.memory;
            saveMemory(this.storage, this.locale, this.memory);
        }

        const bubble = this.renderMessage(message, { reveal: reply.status === 'ok' });
        this.log.append(bubble.element);
        this.scrollToEnd(true);
        this.setBusy(false);

        if (spoken && this.voiceSession) {
            // The answer is read while it appears; the host listens again after it.
            const reading = this.speak(message);
            await bubble.revealed;
            await reading;
            if (this.voiceSession && (reply.status === 'ok' || reply.status === 'refused')) {
                this.listen();
            } else {
                this.endVoice();
            }

            return;
        }

        if (reply.status === 'ok') {
            this.host?.setState('talking');
            await bubble.revealed;
            this.host?.setState('happy');
        } else {
            this.host?.setState('idle');
        }

        if (!this.panel.hidden && !window.matchMedia('(pointer: coarse)').matches) {
            this.input.focus({ preventScroll: true });
        }
    }

    async request(message, history, { spoken = false } = {}) {
        const controller = new AbortController();
        const timer = window.setTimeout(() => controller.abort(), REQUEST_TIMEOUT_MS);

        try {
            const response = await fetch(this.endpoint, {
                method: 'POST',
                credentials: 'same-origin',
                signal: controller.signal,
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN': csrfToken(),
                },
                body: JSON.stringify({ message, history, ...(this.memory ? { memory: this.memory } : {}), ...(spoken ? { voice: true } : {}) }),
            });

            if (!response.ok) {
                return { status: 'error', answer: this.failureText(failureKey(response.status)), sources: [] };
            }

            const data = await response.json();
            if (typeof data?.answer !== 'string' || data.answer.trim() === '') {
                return { status: 'error', answer: this.failureText('offline'), sources: [] };
            }

            return {
                status: typeof data.status === 'string' ? data.status : 'error',
                answer: data.answer,
                sources: Array.isArray(data.sources) ? data.sources : [],
                signature: typeof data.signature === 'string' ? data.signature : null,
                memory: typeof data.memory === 'string' && data.memory !== '' ? data.memory : null,
                speech: typeof data.speech === 'string' ? data.speech : null,
            };
        } catch {
            return { status: 'error', answer: this.failureText('offline'), sources: [] };
        } finally {
            window.clearTimeout(timer);
        }
    }

    failureText(key) {
        const base = this.text[key] ?? this.text.offline ?? '';

        return this.contact.length && key !== 'expired' && key !== 'tooLong' ? `${base} (${this.contact.join(' · ')})` : base;
    }

    pushMessage(message) {
        this.messages.push(message);
        saveMessages(this.storage, this.locale, this.messages);
        const { element } = this.renderMessage(message, { reveal: false });
        this.log.querySelector('.spu-chatbot__suggestions')?.remove();
        this.log.append(element);
        this.scrollToEnd(true);
    }

    setBusy(busy) {
        this.busy = busy;
        this.sendButton.disabled = busy;
        if (this.micButton) {
            this.micButton.disabled = busy && this.voiceMode === 'off';
        }
        this.form.classList.toggle('is-busy', busy);
        this.log.setAttribute('aria-busy', busy ? 'true' : 'false');
        if (busy) {
            this.host?.setState('thinking');
        }
    }

    /* ── Voice ─────────────────────────────────────────────────────────── */

    /**
     * The microphone: starts a voice conversation; while listening, sends
     * what was said; while reading aloud, interrupts and listens; while
     * working, cancels.
     */
    onMicPress() {
        switch (this.voiceMode) {
            case 'listening':
                this.recorder?.finish();
                break;
            case 'preparing':
            case 'speaking':
                // The conversation goes on: the reading ends and he listens.
                this.player?.stop();
                break;
            case 'transcribing':
            case 'thinking':
                this.endVoice({ announce: true });
                break;
            default:
                if (this.busy) {
                    return;
                }
                this.voiceSession = true;
                this.voiceTurns = 0;
                this.unlockAudio();
                this.listen();
        }
    }

    onVoiceStop() {
        if (this.voiceMode === 'listening') {
            this.recorder?.finish();
        } else {
            this.endVoice({ announce: true });
        }
    }

    /**
     * Audio may only start from a tap; the context made here keeps playing
     * after the network round trips that follow. On engines that need it, a
     * silent utterance does the same for the browser's own voice.
     */
    unlockAudio() {
        const Context = window.AudioContext || window.webkitAudioContext;
        if (!Context) {
            return null;
        }
        try {
            this.audioContext ??= new Context();
            if (this.audioContext.state === 'suspended') {
                this.audioContext.resume().catch(() => {});
            }
        } catch {
            this.audioContext = null;
        }
        if (!this.voiceConfig?.server && 'speechSynthesis' in window && !this.deviceVoiceUnlocked) {
            this.deviceVoiceUnlocked = true;
            const silence = new SpeechSynthesisUtterance(' ');
            silence.volume = 0;
            window.speechSynthesis.speak(silence);
        }

        return this.audioContext;
    }

    loadVoice() {
        this.voiceModule ??= import('./chatVoice.js');

        return this.voiceModule;
    }

    async listen() {
        if (this.recorder) {
            return;
        }
        if (!this.voiceSession || this.busy || this.panel.hidden || !this.audioContext) {
            this.endVoice();

            return;
        }

        let VoiceRecorder;
        try {
            ({ VoiceRecorder } = await this.loadVoice());
        } catch {
            this.endVoice();
            this.appendNotice(this.text.micUnavailable);

            return;
        }
        if (!this.voiceSession) {
            return;
        }

        this.player?.stop();
        this.setVoiceMode('listening');
        const recorder = new VoiceRecorder({
            audioContext: this.audioContext,
            maxSeconds: Number(this.voiceConfig.maxSeconds) || 60,
            // After an answer the visitor may take a moment to think.
            noSpeechMs: this.voiceTurns ? 9000 : 7000,
            onLevel: (level) => this.showLevel(level),
            onSpeech: () => this.setVoiceStatus(this.text.listening),
        });
        this.recorder = recorder;
        this.startVoiceClock();

        let recording;
        try {
            recording = await recorder.record();
        } catch (error) {
            this.stopVoiceClock();
            if (this.recorder === recorder) {
                this.recorder = null;
                this.endVoice();
                this.appendNotice(this.microphoneError(error));
            }

            return;
        }
        this.stopVoiceClock();
        if (this.recorder !== recorder) {
            return;
        }
        this.recorder = null;
        if (!this.voiceSession) {
            return;
        }

        if (!recording) {
            const firstTurn = this.voiceTurns === 0;
            this.endVoice({ announce: !firstTurn });
            if (firstTurn) {
                this.appendNotice(this.text.noSpeech);
            }

            return;
        }

        this.setVoiceMode('transcribing');
        const transcript = await this.transcribe(recording);
        if (!this.voiceSession) {
            return;
        }
        if (transcript.status !== 'ok') {
            this.endVoice();
            this.appendNotice(transcript.message || this.failureText('offline'));

            return;
        }

        this.voiceTurns++;
        this.setVoiceMode('thinking');
        await this.send(transcript.text, { spoken: true });
    }

    async transcribe(recording) {
        const form = new FormData();
        const extension = recording.mimeType.includes('mp4') ? 'mp4' : recording.mimeType.includes('ogg') ? 'ogg' : 'webm';
        form.append('audio', recording.blob, `question.${extension}`);
        form.append('duration', String(recording.durationMs));

        const controller = new AbortController();
        this.transcribing = controller;
        const timer = window.setTimeout(() => controller.abort(), REQUEST_TIMEOUT_MS);
        try {
            const response = await fetch(this.voiceConfig.transcribe, {
                method: 'POST',
                credentials: 'same-origin',
                signal: controller.signal,
                headers: {
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN': csrfToken(),
                },
                body: form,
            });
            if (!response.ok) {
                return { status: 'error', message: this.failureText(failureKey(response.status)) };
            }
            const data = await response.json();

            return {
                status: typeof data?.status === 'string' ? data.status : 'error',
                text: typeof data?.text === 'string' ? data.text : '',
                message: typeof data?.message === 'string' ? data.message : '',
            };
        } catch {
            return { status: 'error', message: controller.signal.aborted && !this.voiceSession ? '' : this.failureText('offline') };
        } finally {
            window.clearTimeout(timer);
            this.transcribing = null;
        }
    }

    /**
     * Read one message aloud (the server's voice, else the browser's).
     * Resolves when it has been read or was stopped.
     */
    async speak(message, button = null) {
        if (!this.canListen) {
            return;
        }

        let SpeechPlayer;
        try {
            ({ SpeechPlayer } = await this.loadVoice());
        } catch {
            return;
        }

        const context = this.audioContext ?? this.unlockAudio();
        this.player ??= new SpeechPlayer({
            audioContext: context,
            endpoint: this.voiceConfig.speech,
            serverSpeech: this.voiceConfig.server && context !== null,
            csrfToken: csrfToken(),
            locale: this.locale,
            onLevel: (level) => {
                this.host?.setVoiceLevel?.(level);
                if (this.voiceMode === 'speaking') {
                    this.showLevel(level);
                }
            },
        });

        this.playingMessage = message;
        this.setPlaying(button);
        // He thinks until the voice is ready, and talks only while it sounds.
        if (this.voiceSession) {
            this.setVoiceMode('preparing');
        }
        this.host?.setState('thinking');

        await this.player.play({
            text: message.content,
            signature: message.speech ?? null,
            plainText: markdownToText(message.content),
            onStart: () => {
                if (this.voiceSession && this.voiceMode === 'preparing') {
                    this.setVoiceMode('speaking');
                }
                if (this.playingMessage === message) {
                    this.host?.setState('talking');
                }
            },
        });

        if (this.playingMessage === message) {
            this.playingMessage = null;
            this.setPlaying(null);
        }
        this.host?.setVoiceLevel?.(0);
    }

    /** End the voice conversation and anything it is doing. */
    endVoice({ announce = false } = {}) {
        const wasOn = this.voiceSession || this.voiceMode !== 'off';
        this.voiceSession = false;
        this.voiceTurns = 0;
        this.recorder?.cancel();
        this.recorder = null;
        this.transcribing?.abort();
        this.player?.stop();
        this.playingMessage = null;
        this.setPlaying(null);
        this.stopVoiceClock();
        if (this.voiceMode !== 'off') {
            this.setVoiceMode('off');
        }
        if (wasOn && announce) {
            this.announce(this.text.voiceEnded);
        }
    }

    setVoiceMode(mode) {
        this.voiceMode = mode;
        if (!this.voiceBar) {
            return;
        }

        const on = mode !== 'off';
        this.voiceBar.hidden = !on;
        this.form.classList.toggle('is-voice', on);
        for (const name of ['listening', 'transcribing', 'thinking', 'preparing', 'speaking']) {
            this.voiceBar.classList.toggle(`is-${name}`, mode === name);
        }
        this.micButton.setAttribute('aria-pressed', on ? 'true' : 'false');
        const label = mode === 'listening' ? this.text.stopListening : on ? this.text.cancelVoice : this.text.speak;
        this.micButton.setAttribute('aria-label', label);
        this.micButton.title = label;
        this.micButton.disabled = false;

        this.voiceStop.hidden = mode !== 'listening' && mode !== 'speaking' && mode !== 'preparing';
        this.voiceStopLabel.textContent = mode === 'listening' ? this.text.stopListening : this.text.stopAudio;
        this.voiceStop.title = this.voiceStopLabel.textContent;
        this.voiceTime.hidden = mode !== 'listening';
        this.showLevel(0);

        const status = { listening: this.text.listening, transcribing: this.text.transcribing, thinking: this.text.thinking, preparing: this.text.preparingVoice, speaking: this.text.speaking }[mode] ?? '';
        this.setVoiceStatus(status);

        if (mode === 'listening') {
            this.host?.setState('listening');
        } else if (mode === 'transcribing' || mode === 'thinking' || mode === 'preparing') {
            this.host?.setState('thinking');
        } else if (mode === 'off') {
            this.host?.setVoiceLevel?.(0);
            if (!this.busy) {
                this.host?.setState('idle');
            }
        }
    }

    setVoiceStatus(text) {
        if (this.voiceStatus && this.voiceStatus.textContent !== text) {
            this.voiceStatus.textContent = text ?? '';
        }
    }

    /** A message for screen readers only (the voice bar may be hidden). */
    announce(text) {
        if (!this.announcer || !text) {
            return;
        }
        this.announcer.textContent = text;
        window.setTimeout(() => {
            if (this.announcer.textContent === text) {
                this.announcer.textContent = '';
            }
        }, 4000);
    }

    showLevel(level) {
        this.voiceMeter?.style.setProperty('--level', this.reducedMotion.matches ? '0.35' : String(Math.round(level * 100) / 100));
        if (this.voiceMode === 'listening') {
            this.host?.setVoiceLevel?.(level);
        }
    }

    startVoiceClock() {
        this.stopVoiceClock();
        const started = performance.now();
        const paint = () => {
            const seconds = Math.floor((performance.now() - started) / 1000);
            this.voiceTime.textContent = `${Math.floor(seconds / 60)}:${String(seconds % 60).padStart(2, '0')}`;
        };
        paint();
        this.voiceClock = window.setInterval(paint, 500);
    }

    stopVoiceClock() {
        window.clearInterval(this.voiceClock);
        this.voiceClock = null;
    }

    setPlaying(button) {
        if (this.playingButton && this.playingButton !== button) {
            this.paintListenButton(this.playingButton, false);
        }
        this.playingButton = button;
        if (button) {
            this.paintListenButton(button, true);
        }
    }

    paintListenButton(button, playing) {
        button.classList.toggle('is-playing', playing);
        button.setAttribute('aria-pressed', playing ? 'true' : 'false');
        button.innerHTML = playing ? ICON_STOP : ICON_LISTEN;
        const label = document.createElement('span');
        label.textContent = playing ? this.text.stopAudio : this.text.listen;
        button.append(label);
    }

    microphoneError(error) {
        if (error?.name === 'NotAllowedError' || error?.name === 'SecurityError') {
            return this.text.micDenied;
        }
        if (error?.name === 'NotFoundError' || error?.name === 'OverconstrainedError') {
            return this.text.micMissing;
        }

        return this.text.micUnavailable;
    }

    /* ── Rendering ─────────────────────────────────────────────────────── */

    renderLog() {
        this.log.replaceChildren();

        const welcome = this.renderMessage({ role: 'assistant', content: this.welcome, status: 'ok', sources: [] }, { reveal: false, welcome: true });
        this.log.append(welcome.element);

        if (!this.messages.length && this.suggestions.length) {
            this.log.append(this.renderSuggestions());
        }

        for (const message of this.messages) {
            this.log.append(this.renderMessage(message, { reveal: false }).element);
        }
    }

    renderSuggestions() {
        const wrapper = document.createElement('div');
        wrapper.className = 'spu-chatbot__suggestions';
        wrapper.setAttribute('role', 'group');
        wrapper.setAttribute('aria-label', this.text.suggestions ?? '');

        for (const suggestion of this.suggestions) {
            const button = document.createElement('button');
            button.type = 'button';
            button.className = 'spu-chatbot__chip';
            button.textContent = suggestion;
            button.addEventListener('click', () => this.send(suggestion));
            wrapper.append(button);
        }

        return wrapper;
    }

    renderMessage(message, { reveal = false, welcome = false } = {}) {
        const element = document.createElement('div');
        const isUser = message.role === 'user';
        element.className = `spu-chatbot__message spu-chatbot__message--${isUser ? 'user' : 'assistant'}${message.status !== 'ok' ? ' spu-chatbot__message--notice' : ''}${welcome ? ' spu-chatbot__message--welcome' : ''}`;

        const label = document.createElement('span');
        label.className = 'spu-chatbot__sr';
        label.textContent = `${isUser ? this.text.you : this.text.assistant}: `;
        element.append(label);

        const bubble = document.createElement('div');
        bubble.className = 'spu-chatbot__bubble';
        bubble.setAttribute('dir', 'auto');
        element.append(bubble);

        if (isUser || message.status !== 'ok') {
            bubble.textContent = message.content;

            return { element, revealed: Promise.resolve() };
        }

        const body = document.createElement('div');
        body.className = 'spu-chatbot__md';
        bubble.append(body);

        const result = { element, revealed: Promise.resolve(), listenButton: null };
        const finish = () => {
            body.replaceChildren(renderMarkdown(message.content));
            if (!welcome) {
                this.appendFooter(bubble, message, result);
            }
        };

        if (!reveal || this.reducedMotion.matches) {
            finish();

            return result;
        }

        // Screen readers wait for aria-busy to clear and read the answer once.
        element.setAttribute('aria-busy', 'true');
        const revealed = new Promise((resolve) => {
            const steps = revealSteps(message.content, 36);
            let step = 0;
            const tick = () => {
                if (step >= steps.length) {
                    finish();
                    element.removeAttribute('aria-busy');
                    this.scrollToEnd(true);
                    resolve();

                    return;
                }
                body.replaceChildren(renderMarkdown(message.content.slice(0, steps[step])));
                step++;
                this.scrollToEnd(false);
                window.setTimeout(tick, 28);
            };
            tick();
        });
        result.revealed = revealed;

        return result;
    }

    appendFooter(bubble, message, result = null) {
        const footer = document.createElement('div');
        footer.className = 'spu-chatbot__meta';

        const sources = sanitizeSources(message.sources ?? []);
        if (sources.length) {
            const block = document.createElement('div');
            block.className = 'spu-chatbot__sources';

            const label = document.createElement('p');
            label.className = 'spu-chatbot__sources-label';
            label.innerHTML = ICON_SOURCES;
            label.append(document.createTextNode(this.text.sources ?? ''));
            block.append(label);

            const list = document.createElement('ul');
            list.className = 'spu-chatbot__source-list';
            for (const source of sources) {
                const item = document.createElement('li');
                const chip = document.createElement(source.url ? 'a' : 'span');
                chip.className = 'spu-chatbot__source';
                chip.setAttribute('dir', 'auto');
                if (source.url) {
                    chip.href = source.url;
                    chip.innerHTML = ICON_LINK;
                } else {
                    chip.innerHTML = ICON_DOCUMENT;
                }

                const title = document.createElement('span');
                title.className = 'spu-chatbot__source-title';
                title.textContent = source.title;
                chip.title = source.title;
                chip.append(title);

                if (source.pages?.length) {
                    const pages = document.createElement('span');
                    pages.className = 'spu-chatbot__source-pages';
                    pages.textContent = `${this.text.page} ${source.pages.join('، ')}`;
                    chip.append(pages);
                }

                item.append(chip);
                list.append(item);
            }
            block.append(list);
            footer.append(block);
        }

        const actions = document.createElement('div');
        actions.className = 'spu-chatbot__actions';

        if (this.canListen) {
            const listen = document.createElement('button');
            listen.type = 'button';
            listen.className = 'spu-chatbot__copy spu-chatbot__listen';
            listen.setAttribute('aria-pressed', 'false');
            this.paintListenButton(listen, false);
            listen.addEventListener('click', () => {
                if (this.playingMessage === message) {
                    this.endVoice();

                    return;
                }
                this.endVoice();
                this.unlockAudio();
                this.speak(message, listen).then(() => {
                    if (!this.playingMessage) {
                        this.host?.setState('happy');
                    }
                });
            });
            actions.append(listen);
            if (result) {
                result.listenButton = listen;
            }
            // The answer may already be playing while it is revealed.
            if (this.playingMessage === message) {
                this.setPlaying(listen);
            }
        }

        const copy = document.createElement('button');
        copy.type = 'button';
        copy.className = 'spu-chatbot__copy';
        copy.innerHTML = ICON_COPY;
        const copyLabel = document.createElement('span');
        copyLabel.textContent = this.text.copy;
        copy.append(copyLabel);
        copy.addEventListener('click', async () => {
            try {
                await navigator.clipboard.writeText(markdownToText(message.content));
                copy.classList.add('is-copied');
                copyLabel.textContent = this.text.copied;
                window.setTimeout(() => {
                    copy.classList.remove('is-copied');
                    copyLabel.textContent = this.text.copy;
                }, 1600);
            } catch {
                // Clipboard refused (permissions, insecure context): nothing to undo.
            }
        });
        actions.append(copy);
        footer.append(actions);
        bubble.append(footer);
    }

    appendTyping() {
        const element = document.createElement('div');
        element.className = 'spu-chatbot__message spu-chatbot__message--assistant spu-chatbot__message--typing';
        element.innerHTML = '<div class="spu-chatbot__bubble"><span class="spu-chatbot__dots" aria-hidden="true"><i></i><i></i><i></i></span></div>';
        const label = document.createElement('span');
        label.className = 'spu-chatbot__sr';
        label.textContent = this.text.thinking ?? '';
        element.prepend(label);
        this.log.append(element);
        this.scrollToEnd(true);

        return element;
    }

    appendNotice(text) {
        const { element } = this.renderMessage({ role: 'assistant', content: text, status: 'notice', sources: [] });
        this.log.append(element);
        this.scrollToEnd(true);
    }

    /**
     * Grow with the text up to the CSS max-height, then scroll. The border
     * is added back because scrollHeight leaves it out; without it the field
     * is always 2px short and shows a scrollbar.
     */
    autosize() {
        const field = this.input;
        const max = Number.parseFloat(getComputedStyle(field).maxHeight) || 132;
        field.style.height = 'auto';
        const needed = field.scrollHeight + (field.offsetHeight - field.clientHeight);
        field.style.height = `${Math.min(needed, max)}px`;
        field.classList.toggle('is-scrolling', needed > max);
    }

    /**
     * Write right-to-left or left-to-right by the first letter typed, and
     * fall back to the page's direction while the field is empty.
     */
    syncDirection() {
        const first = this.input.value.match(/[\p{L}]/u)?.[0];
        let direction = this.pageDirection;
        if (first) {
            direction = /[\u0590-\u08FF\uFB1D-\uFDFF\uFE70-\uFEFF]/u.test(first) ? 'rtl' : 'ltr';
        }
        if (this.input.dir !== direction) {
            this.input.dir = direction;
        }
    }

    syncCounter() {
        const length = this.input.value.length;
        const near = length >= this.maxLength * 0.8;
        this.counter.hidden = !near;
        if (near) {
            this.counter.textContent = `${length} / ${this.maxLength}`;
            this.counter.classList.toggle('is-near', length >= this.maxLength * 0.95);
        }
    }

    scrollToEnd(smooth) {
        requestAnimationFrame(() => {
            this.log.scrollTo({ top: this.log.scrollHeight, behavior: smooth && !this.reducedMotion.matches ? 'smooth' : 'auto' });
        });
    }

    /* ── 3D host ───────────────────────────────────────────────────────── */

    awaitFirstInteraction() {
        const onInteract = () => {
            for (const type of ['pointerdown', 'keydown', 'touchstart', 'scroll']) {
                window.removeEventListener(type, onInteract);
            }
            this.wake();
        };

        for (const type of ['pointerdown', 'keydown', 'touchstart', 'scroll']) {
            window.addEventListener(type, onInteract, { once: true, passive: true });
        }
    }

    wake() {
        if (this.awake) {
            return;
        }
        this.awake = true;
        this.root.classList.add('is-awake');
        this.scheduleTeaser();
    }

    loadHost() {
        if (this.hostLoading) {
            return this.hostLoading;
        }
        if (navigator.connection?.saveData === true || !supportsWebGL()) {
            this.hostLoading = Promise.resolve(null);

            return this.hostLoading;
        }

        this.hostLoading = import('./characterScene.js')
            .then(({ createCharacter }) => {
                this.host = createCharacter({ reducedMotion: this.reducedMotion.matches });
                this.host.mount(this.panel.hidden ? this.stage : this.avatar);
                this.stage.classList.add('is-3d');
                this.avatar.classList.add('is-3d');

                return this.host;
            })
            .catch(() => {
                this.host = null;

                return null;
            });

        return this.hostLoading;
    }
}

function storageKeyPrefix(locale) {
    return `spu-assistant:${locale}`;
}

function csrfToken() {
    return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '';
}

function parseJson(value, fallback) {
    try {
        return value ? JSON.parse(value) : fallback;
    } catch {
        return fallback;
    }
}

function safeSessionStorage() {
    try {
        const storage = window.sessionStorage;
        const probe = '__spu_assistant__';
        storage.setItem(probe, '1');
        storage.removeItem(probe);

        return storage;
    } catch {
        return null;
    }
}

let webglSupport = null;

function supportsWebGL() {
    if (webglSupport !== null) {
        return webglSupport;
    }

    try {
        const canvas = document.createElement('canvas');
        const context = window.WebGL2RenderingContext ? canvas.getContext('webgl2') : null;
        webglSupport = Boolean(context);
        // Hand the probe's context back instead of waiting for collection.
        context?.getExtension('WEBGL_lose_context')?.loseContext();
    } catch {
        webglSupport = false;
    }

    return webglSupport;
}
