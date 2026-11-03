/**
 * Loads the AI assistant only when a visitor shows intent, so it is never on
 * a page's critical path.
 *
 * The launcher is in the HTML and styled from the first paint (chatbot.css),
 * so nothing appears late. The widget's script and the panel's styles
 * (chatbotWidget.js and chatbot-panel.css, ~10 KB) are fetched on the first
 * interaction: a pointer, touch, key or scroll anywhere, or the launcher
 * itself. A first click on the launcher opens the panel as soon as they
 * arrive. A visitor already in a conversation gets it back when the browser
 * is idle. A page that is only loaded (a crawler, a speed test) never
 * fetches them.
 */

const INTERACTIONS = ['pointerdown', 'pointermove', 'keydown', 'touchstart', 'scroll', 'wheel'];

export function bootChatbot(root = document.querySelector('[data-chatbot]')) {
    if (!root) {
        return;
    }

    let loading = null;
    const load = (options) => {
        loading ??= import('./chatbotWidget.js').then(({ initChatbot }) => initChatbot(root, options));

        return loading;
    };

    const launcher = root.querySelector('[data-chatbot-launcher]');
    launcher?.addEventListener('click', () => {
        load({ awake: true }).then((widget) => widget?.open());
    }, { once: true });

    for (const type of ['pointerenter', 'focus', 'touchstart']) {
        launcher?.addEventListener(type, () => load({ awake: true }), { once: true, passive: true });
    }

    if (inConversation(root.dataset.locale)) {
        const idle = window.requestIdleCallback ?? ((callback) => window.setTimeout(callback, 1500));
        idle(() => load({ awake: false }), { timeout: 3000 });

        return;
    }

    const onFirstInteraction = (event) => {
        for (const type of INTERACTIONS) {
            window.removeEventListener(type, onFirstInteraction);
        }
        // Moving the mouse is intent enough to fetch the code, not to wake the
        // robot or show the teaser; a press, key or scroll is.
        load({ awake: event.type !== 'pointermove' });
    };

    for (const type of INTERACTIONS) {
        window.addEventListener(type, onFirstInteraction, { passive: true });
    }
}

function inConversation(locale) {
    try {
        const key = `spu-assistant:v1:${locale === 'en' ? 'en' : 'ar'}`;
        const stored = window.sessionStorage.getItem(key);

        return window.sessionStorage.getItem(`spu-assistant:${locale}:open`) === '1' || (stored !== null && stored !== '[]');
    } catch {
        return false;
    }
}
