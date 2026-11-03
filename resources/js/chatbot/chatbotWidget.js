import { markdownToText, renderMarkdown } from './chatMarkdown.js';
import { failureKey, historyFor, loadMessages, revealSteps, sanitizeSources, saveMessages } from './chatState.js';

/**
 * The public AI assistant: launcher, teaser bubble, chat panel and the 3D
 * robot. Plain DOM code (no Alpine) so the whole widget is one lazily loaded
 * chunk, and the Three.js scene is a second one loaded when the browser is idle.
 */

const REQUEST_TIMEOUT_MS = 60_000;
const TEASER_DELAY_MS = 4_500;
const TEASER_VISIBLE_MS = 14_000;
// Where the panel is a full-screen sheet (see chatbot.css).
const MOBILE_QUERY = '(max-width: 640px), (max-height: 560px)';

const ICON_SOURCES = '<svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M4 5a2 2 0 0 1 2-2h9l5 5v11a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2z" fill="none" stroke="currentColor" stroke-width="2" stroke-linejoin="round"/><path d="M8 12h8M8 16h5" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>';
const ICON_LINK = '<svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M10 14a4 4 0 0 0 5.7 0l3-3a4 4 0 0 0-5.7-5.7l-1 1M14 10a4 4 0 0 0-5.7 0l-3 3a4 4 0 0 0 5.7 5.7l1-1" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>';
const ICON_DOCUMENT = '<svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M6 3h8l4 4v14H6z" fill="none" stroke="currentColor" stroke-width="2" stroke-linejoin="round"/></svg>';
const ICON_COPY = '<svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><rect x="8" y="8" width="12" height="12" rx="2" fill="none" stroke="currentColor" stroke-width="2"/><path d="M16 8V6a2 2 0 0 0-2-2H6a2 2 0 0 0-2 2v8a2 2 0 0 0 2 2h2" fill="none" stroke="currentColor" stroke-width="2"/></svg>';

export function initChatbot(root = document.querySelector('[data-chatbot]')) {
    if (!root || root.dataset.chatbotReady === '1') {
        return null;
    }
    root.dataset.chatbotReady = '1';

    const widget = new ChatbotWidget(root);
    widget.start();

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
        this.busy = false;
        this.robot = null;
        this.robotLoading = null;
        this.reducedMotion = matchMedia('(prefers-reduced-motion: reduce)');
        this.mobile = matchMedia(MOBILE_QUERY);
    }

    start() {
        this.root.hidden = false;
        this.renderLog();
        this.bind();
        this.scheduleRobot();
        this.scheduleTeaser();

        if (this.storage?.getItem(`${storageKeyPrefix(this.locale)}:open`) === '1') {
            this.open({ focus: false });
        }
    }

    bind() {
        this.launcher.addEventListener('click', () => this.open());
        this.launcher.addEventListener('pointerenter', () => {
            this.loadRobot();
            this.robot?.wave();
        });
        this.launcher.addEventListener('focus', () => this.loadRobot(), { once: true });

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
        });

        this.panel.addEventListener('keydown', (event) => {
            if (event.key === 'Escape') {
                event.stopPropagation();
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

        window.addEventListener('pointermove', (event) => this.robot?.lookAt(event.clientX, event.clientY), { passive: true });
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

        this.loadRobot().then(() => this.robot?.mount(this.avatar));
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

        this.panel.hidden = true;
        this.root.classList.remove('is-open');
        this.launcher.setAttribute('aria-expanded', 'false');
        document.documentElement.classList.remove('spu-chatbot-locked');
        this.storage?.removeItem(`${storageKeyPrefix(this.locale)}:open`);
        this.robot?.mount(this.stage);
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
        this.messages = [];
        saveMessages(this.storage, this.locale, this.messages);
        this.renderLog();
        this.robot?.setState('happy');
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
            this.robot?.wave();
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

    async send(rawText) {
        const text = String(rawText ?? '').trim();
        if (this.busy || text === '') {
            return;
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
            reply = await this.request(text, history);
        } finally {
            typing.remove();
        }

        const message = {
            role: 'assistant',
            content: reply.answer,
            status: reply.status,
            sources: reply.status === 'ok' ? reply.sources : [],
            ...(reply.signature ? { signature: reply.signature } : {}),
        };
        this.messages.push(message);
        saveMessages(this.storage, this.locale, this.messages);

        const bubble = this.renderMessage(message, { reveal: reply.status === 'ok' });
        this.log.append(bubble.element);
        this.scrollToEnd(true);

        if (reply.status === 'ok') {
            this.robot?.setState('talking');
            await bubble.revealed;
            this.robot?.setState('happy');
        } else {
            this.robot?.setState('idle');
        }

        this.setBusy(false);
        if (!this.panel.hidden && !window.matchMedia('(pointer: coarse)').matches) {
            this.input.focus({ preventScroll: true });
        }
    }

    async request(message, history) {
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
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '',
                },
                body: JSON.stringify({ message, history }),
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
        this.form.classList.toggle('is-busy', busy);
        this.log.setAttribute('aria-busy', busy ? 'true' : 'false');
        if (busy) {
            this.robot?.setState('thinking');
        }
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

        const finish = () => {
            body.replaceChildren(renderMarkdown(message.content));
            if (!welcome) {
                this.appendFooter(bubble, message);
            }
        };

        if (!reveal || this.reducedMotion.matches) {
            finish();

            return { element, revealed: Promise.resolve() };
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

        return { element, revealed };
    }

    appendFooter(bubble, message) {
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

    /* ── 3D robot ──────────────────────────────────────────────────────── */

    scheduleRobot() {
        const saveData = navigator.connection?.saveData === true;
        if (saveData || !supportsWebGL()) {
            return;
        }

        const idle = window.requestIdleCallback ?? ((callback) => window.setTimeout(callback, 1200));
        idle(() => this.loadRobot(), { timeout: 3500 });
    }

    loadRobot() {
        if (this.robotLoading) {
            return this.robotLoading;
        }
        if (navigator.connection?.saveData === true || !supportsWebGL()) {
            this.robotLoading = Promise.resolve(null);

            return this.robotLoading;
        }

        this.robotLoading = import('./robotScene.js')
            .then(({ createRobot }) => {
                this.robot = createRobot({ reducedMotion: this.reducedMotion.matches });
                this.robot.mount(this.panel.hidden ? this.stage : this.avatar);
                this.stage.classList.add('is-3d');
                this.avatar.classList.add('is-3d');

                return this.robot;
            })
            .catch(() => {
                this.robot = null;

                return null;
            });

        return this.robotLoading;
    }
}

function storageKeyPrefix(locale) {
    return `spu-assistant:${locale}`;
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
