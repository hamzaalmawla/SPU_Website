import test from 'node:test';
import assert from 'node:assert/strict';

import { markdownToText, parseInline, parseMarkdown, safeHref } from '../../resources/js/chatbot/chatMarkdown.js';
import { canRecord, historyFor, loadMemory, loadMessages, memoryKey, revealSteps, sanitizeMessages, saveMemory, saveMessages, storageKey } from '../../resources/js/chatbot/chatState.js';
import { createEndpointDetector, deviceChunks, isArabicText, levelFromSamples, pickDeviceVoice, pickRecorderType } from '../../resources/js/chatbot/chatVoice.js';

test('paragraphs, lists, headings and tables parse into blocks', () => {
    const blocks = parseMarkdown('### شروط القبول\n\nيجب توفر:\n- الشهادة الثانوية\n- **صورة** الهوية\n\n1. التسجيل\n2. الدفع\n\n| الكلية | المعدل |\n|---|---|\n| الطب | 230 |');

    assert.deepEqual(blocks.map((block) => block.type), ['heading', 'paragraph', 'list', 'list', 'table']);
    assert.equal(blocks[2].ordered, false);
    assert.equal(blocks[2].items.length, 2);
    assert.deepEqual(blocks[2].items[1][0], { type: 'strong', children: [{ type: 'text', text: 'صورة' }] });
    assert.equal(blocks[3].ordered, true);
    assert.equal(blocks[4].rows[0][1][0].text, '230');
});

test('arabic-indic numbered lists keep their start number', () => {
    const [list] = parseMarkdown('٣. الخطوة الثالثة\n٤. الخطوة الرابعة');

    assert.equal(list.ordered, true);
    assert.equal(list.start, 3);
});

test('links are kept only for safe schemes', () => {
    assert.equal(safeHref('javascript:alert(1)', 'https://spu.edu.sy'), null);
    assert.equal(safeHref('data:text/html,hi', 'https://spu.edu.sy'), null);
    assert.equal(safeHref('/ar/admissions', 'https://spu.edu.sy'), '/ar/admissions');
    assert.equal(safeHref('//evil.example', 'https://spu.edu.sy'), 'https://evil.example/');
    assert.equal(safeHref('TEL:+963116990200', 'https://spu.edu.sy'), 'tel:+963116990200');
    assert.equal(safeHref('https://spu.edu.sy/ar', 'https://spu.edu.sy'), 'https://spu.edu.sy/ar');

    const [link] = parseInline('[click](javascript:alert(1))');
    assert.equal(link.type, 'text');
});

test('bare urls stop before trailing punctuation', () => {
    const nodes = parseInline('زوروا https://spu.edu.sy/ar/admissions، شكراً.');

    assert.equal(nodes[1].type, 'link');
    assert.equal(nodes[1].href, 'https://spu.edu.sy/ar/admissions');
    assert.equal(nodes[2].text, '، شكراً.');
});

test('raw html stays text', () => {
    const [paragraph] = parseMarkdown('<img src=x onerror=alert(1)> **ok**');

    assert.equal(paragraph.children[0].type, 'text');
    assert.match(paragraph.children[0].text, /<img/);
});

test('markdown converts to plain text for copying', () => {
    assert.equal(markdownToText('**Fees**\n\n- One\n- Two [site](https://spu.edu.sy)'), 'Fees\n\n• One\n• Two site');
});

test('history sends only signed question-and-answer pairs, unchanged', () => {
    const sig = (n) => String(n).repeat(64).slice(0, 64);
    const messages = [
        { role: 'user', content: 'q1', status: 'ok' },
        { role: 'assistant', content: 'a1', status: 'ok', signature: sig(1) },
        { role: 'user', content: 'q2', status: 'ok' },
        { role: 'assistant', content: 'offline', status: 'error' },
        { role: 'user', content: 'write code', status: 'ok' },
        { role: 'assistant', content: 'I can only help with SPU', status: 'refused' },
        { role: 'user', content: 'q3 '.repeat(800), status: 'ok' },
        { role: 'assistant', content: 'a3', status: 'ok', signature: sig(3) },
    ];

    assert.deepEqual(historyFor(messages), [
        { role: 'user', content: 'q1' },
        { role: 'assistant', content: 'a1', signature: sig(1) },
        { role: 'user', content: 'q3 '.repeat(800) },
        { role: 'assistant', content: 'a3', signature: sig(3) },
    ]);

    const many = Array.from({ length: 10 }, (_, index) => (index % 2
        ? { role: 'assistant', content: `a${index}`, status: 'ok', signature: sig(index) }
        : { role: 'user', content: `q${index}`, status: 'ok' }));
    const recent = historyFor(many);
    assert.equal(recent.length, 8);
    assert.equal(recent[0].role, 'user');
    assert.equal(recent[0].content, 'q2');
});

test('stored conversations are sanitized and survive bad storage', () => {
    const memory = new Map();
    const storage = { getItem: (key) => memory.get(key) ?? null, setItem: (key, value) => memory.set(key, value) };

    saveMessages(storage, 'ar', [
        { role: 'user', content: 'سؤال' },
        { role: 'system', content: 'evil' },
        { role: 'assistant', content: 'جواب', sources: [{ title: 'دليل', pages: [2, -1, 'x'] }, { title: 'القبول', url: '/ar/admissions' }, { title: 'x', url: 'https://evil.example' }], signature: 'not-hex' },
    ]);

    assert.deepEqual(loadMessages(storage, 'ar'), [
        { role: 'user', content: 'سؤال', status: 'ok', sources: [] },
        { role: 'assistant', content: 'جواب', status: 'ok', sources: [{ title: 'دليل', pages: [2] }, { title: 'القبول', pages: [], url: '/ar/admissions' }, { title: 'x', pages: [] }] },
    ]);
    assert.equal(storageKey('en'), 'spu-assistant:v1:en');
    assert.deepEqual(loadMessages({ getItem: () => '{not json' }, 'ar'), []);
    assert.deepEqual(sanitizeMessages('nope'), []);
});

test('reveal steps end at the full text and cut at word boundaries', () => {
    const text = 'one two three four five six seven eight nine ten eleven twelve';
    const steps = revealSteps(text, 5);

    assert.ok(steps.length <= 5);
    assert.equal(steps[steps.length - 1], text.length);
    for (const cut of steps.slice(0, -1)) {
        assert.equal(text[cut], ' ');
    }
});

test('the sealed conversation notes are kept per language and dropped when invalid', () => {
    const store = new Map();
    const storage = { getItem: (key) => store.get(key) ?? null, setItem: (key, value) => store.set(key, value), removeItem: (key) => store.delete(key) };

    saveMemory(storage, 'en', 'eyJpdiI6IjEyMyJ9');
    assert.equal(loadMemory(storage, 'en'), 'eyJpdiI6IjEyMyJ9');
    assert.equal(loadMemory(storage, 'ar'), null);
    assert.equal(memoryKey('ar'), 'spu-assistant:v1:ar:memory');

    saveMemory(storage, 'en', null);
    assert.equal(loadMemory(storage, 'en'), null);
    saveMemory(storage, 'en', 'x'.repeat(9000));
    assert.equal(loadMemory(storage, 'en'), null);
    assert.equal(loadMemory({ getItem: () => { throw new Error('blocked'); } }, 'en'), null);
});

test('stored replies keep the signature that lets them be read aloud', () => {
    const speech = 'b'.repeat(64);
    const [message] = sanitizeMessages([{ role: 'assistant', content: 'Hi', speech, signature: 'nope' }]);

    assert.equal(message.speech, speech);
    assert.equal('signature' in message, false);
});

test('recording needs a secure page, a microphone, MediaRecorder and Web Audio', () => {
    const win = { isSecureContext: true, navigator: { mediaDevices: { getUserMedia() {} } }, MediaRecorder: function MediaRecorder() {}, AudioContext: function AudioContext() {} };

    assert.equal(canRecord(win), true);
    assert.equal(canRecord({ ...win, isSecureContext: false }), false);
    assert.equal(canRecord({ ...win, MediaRecorder: undefined }), false);
    assert.equal(canRecord({ ...win, navigator: {} }), false);
});

test('the recorder picks Opus in WebM, else AAC in MP4 (Safari)', () => {
    assert.equal(pickRecorderType((type) => type.startsWith('audio/webm')), 'audio/webm;codecs=opus');
    assert.equal(pickRecorderType((type) => type === 'audio/mp4'), 'audio/mp4');
    assert.equal(pickRecorderType(() => false), '');
    assert.equal(pickRecorderType(() => { throw new Error('no'); }), '');
    assert.equal(pickRecorderType(undefined), '');
});

test('loudness is the root mean square of the samples', () => {
    assert.equal(levelFromSamples(new Float32Array(8)), 0);
    assert.equal(levelFromSamples(Float32Array.from([0.5, -0.5, 0.5, -0.5])), 0.5);
    assert.equal(levelFromSamples(null), 0);
});

test('a question ends after a pause that follows speech', () => {
    const detector = createEndpointDetector({ calibrationMs: 300, speechMs: 150, silenceMs: 1000, noSpeechMs: 5000 });
    let now = 0;
    const feed = (level, ms) => {
        let state;
        for (let elapsed = 0; elapsed < ms; elapsed += 50) {
            now += 50;
            state = detector.update(level, now);
        }

        return state;
    };

    assert.equal(feed(0.004, 300), 'waiting');
    assert.equal(feed(0.004, 600), 'waiting');
    // A cough is not speech.
    assert.equal(feed(0.2, 50), 'waiting');
    assert.equal(feed(0.004, 200), 'waiting');
    assert.equal(feed(0.15, 600), 'speaking');
    assert.equal(detector.heardSpeech, true);
    // A short breath between words keeps listening.
    assert.equal(feed(0.004, 500), 'speaking');
    assert.equal(feed(0.12, 400), 'speaking');
    assert.equal(feed(0.004, 1100), 'done');
});

test('nothing said ends with no-speech, and a recording never runs past the limit', () => {
    const silent = createEndpointDetector({ noSpeechMs: 2000 });
    let state;
    for (let now = 0; now <= 2100; now += 50) {
        state = silent.update(0.003, now);
    }
    assert.equal(state, 'no-speech');

    const talker = createEndpointDetector({ maxMs: 3000 });
    for (let now = 0; now <= 3100; now += 50) {
        state = talker.update(now < 300 ? 0.004 : 0.3, now);
    }
    assert.equal(state, 'done');
});

test('the browser voice is the best one for the language', () => {
    const voices = [
        { name: 'Samantha', lang: 'en-US', default: true },
        { name: 'Majed', lang: 'ar-SA' },
        { name: 'Google العربية', lang: 'ar_SY' },
        { name: 'Daniel', lang: 'en-GB' },
    ];

    assert.equal(pickDeviceVoice(voices, 'ar').name, 'Google العربية');
    assert.equal(pickDeviceVoice(voices, 'en').name, 'Samantha');
    assert.equal(pickDeviceVoice([{ name: 'Thomas', lang: 'fr-FR' }], 'ar'), null);
});

test('the browser voice reads short sentences in the right language', () => {
    const chunks = deviceChunks(`${'word '.repeat(80)}end. Second sentence! Third?`, 100);

    assert.ok(chunks.every((chunk) => chunk.length <= 100));
    assert.equal(chunks.at(-1), 'Third?');
    assert.equal(isArabicText('ما هي الرسوم في SPU؟'), true);
    assert.equal(isArabicText('What are the fees at SPU?'), false);
});
