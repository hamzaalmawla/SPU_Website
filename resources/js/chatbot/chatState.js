/**
 * Conversation state helpers, free of the DOM so they can be unit tested.
 *
 * The conversation lives in sessionStorage (per tab, per language), so it
 * survives moving between pages and is gone when the tab closes. Nothing is
 * kept on the server.
 */

export const MAX_STORED_MESSAGES = 30;
export const MAX_HISTORY_MESSAGES = 6;

export function storageKey(locale) {
    return `spu-assistant:v1:${locale === 'en' ? 'en' : 'ar'}`;
}

/**
 * A stored message: { role: 'user'|'assistant', content, status?, sources? }.
 * Anything else read back from storage is dropped.
 */
export function sanitizeMessages(value) {
    if (!Array.isArray(value)) {
        return [];
    }

    return value
        .filter((message) => message && (message.role === 'user' || message.role === 'assistant') && typeof message.content === 'string' && message.content.trim() !== '')
        .map((message) => ({
            role: message.role,
            content: message.content.slice(0, 8000),
            status: typeof message.status === 'string' ? message.status : 'ok',
            sources: Array.isArray(message.sources) ? sanitizeSources(message.sources) : [],
        }))
        .slice(-MAX_STORED_MESSAGES);
}

export function sanitizeSources(sources) {
    return sources
        .filter((source) => source && typeof source.title === 'string' && source.title.trim() !== '')
        .slice(0, 6)
        .map((source) => ({
            title: source.title.slice(0, 255),
            pages: Array.isArray(source.pages) ? source.pages.filter((page) => Number.isInteger(page) && page > 0).slice(0, 6) : [],
        }));
}

/**
 * The turns sent with a new question: answered exchanges only (a failed
 * reply is not context), newest last, at most MAX_HISTORY_MESSAGES.
 */
export function historyFor(messages) {
    const turns = [];
    for (const message of messages) {
        if (message.role === 'assistant' && message.status !== 'ok') {
            // Drop the question the failed reply belonged to as well.
            if (turns.length && turns[turns.length - 1].role === 'user') {
                turns.pop();
            }
            continue;
        }
        turns.push({ role: message.role, content: message.content.slice(0, 1500) });
    }

    const recent = turns.slice(-MAX_HISTORY_MESSAGES);
    while (recent.length && recent[0].role === 'assistant') {
        recent.shift();
    }

    return recent;
}

export function loadMessages(storage, locale) {
    try {
        const raw = storage?.getItem(storageKey(locale));

        return raw ? sanitizeMessages(JSON.parse(raw)) : [];
    } catch {
        return [];
    }
}

export function saveMessages(storage, locale, messages) {
    try {
        storage?.setItem(storageKey(locale), JSON.stringify(sanitizeMessages(messages)));
    } catch {
        // Private mode or full storage: the chat still works for this page.
    }
}

/**
 * Steps of a typing reveal: cumulative cut points at word boundaries, about
 * `steps` of them whatever the answer's length.
 */
export function revealSteps(text, steps = 40) {
    const boundaries = [];
    const pattern = /\s+/gu;
    let match;
    while ((match = pattern.exec(text)) !== null) {
        boundaries.push(match.index);
    }
    boundaries.push(text.length);

    if (boundaries.length <= steps) {
        return boundaries;
    }

    const stride = boundaries.length / steps;
    const picked = [];
    for (let step = 1; step <= steps; step++) {
        picked.push(boundaries[Math.min(boundaries.length - 1, Math.round(step * stride) - 1)]);
    }
    picked[picked.length - 1] = text.length;

    return [...new Set(picked)];
}

/**
 * The visitor-facing reason for a failed request.
 */
export function failureKey(status) {
    if (status === 419) {
        return 'expired';
    }
    if (status === 429) {
        return 'rateLimited';
    }
    if (status === 422) {
        return 'tooLong';
    }

    return 'offline';
}
