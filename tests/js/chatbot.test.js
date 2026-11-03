import test from 'node:test';
import assert from 'node:assert/strict';

import { markdownToText, parseInline, parseMarkdown, safeHref } from '../../resources/js/chatbot/chatMarkdown.js';
import { historyFor, loadMessages, revealSteps, sanitizeMessages, saveMessages, storageKey } from '../../resources/js/chatbot/chatState.js';

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

test('history keeps answered turns only and never starts with the assistant', () => {
    const messages = [
        { role: 'user', content: 'q1', status: 'ok' },
        { role: 'assistant', content: 'a1', status: 'ok' },
        { role: 'user', content: 'q2', status: 'ok' },
        { role: 'assistant', content: 'offline', status: 'error' },
        { role: 'user', content: 'q3', status: 'ok' },
        { role: 'assistant', content: 'a3', status: 'ok' },
    ];

    assert.deepEqual(historyFor(messages), [
        { role: 'user', content: 'q1' },
        { role: 'assistant', content: 'a1' },
        { role: 'user', content: 'q3' },
        { role: 'assistant', content: 'a3' },
    ]);

    const many = Array.from({ length: 10 }, (_, index) => ({ role: index % 2 ? 'assistant' : 'user', content: `m${index}`, status: 'ok' }));
    const recent = historyFor(many);
    assert.ok(recent.length <= 6);
    assert.equal(recent[0].role, 'user');
});

test('stored conversations are sanitized and survive bad storage', () => {
    const memory = new Map();
    const storage = { getItem: (key) => memory.get(key) ?? null, setItem: (key, value) => memory.set(key, value) };

    saveMessages(storage, 'ar', [
        { role: 'user', content: 'سؤال' },
        { role: 'system', content: 'evil' },
        { role: 'assistant', content: 'جواب', sources: [{ title: 'دليل', pages: [2, -1, 'x'] }] },
    ]);

    assert.deepEqual(loadMessages(storage, 'ar'), [
        { role: 'user', content: 'سؤال', status: 'ok', sources: [] },
        { role: 'assistant', content: 'جواب', status: 'ok', sources: [{ title: 'دليل', pages: [2] }] },
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
