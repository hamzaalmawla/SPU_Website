/**
 * The small Markdown subset the assistant writes, parsed into a tree and
 * rendered with DOM APIs only. No HTML string from the model ever reaches
 * innerHTML, so an answer cannot inject markup or script.
 *
 * Blocks: paragraphs, headings (#..######, shown as a bold line), bullet and
 * numbered lists, tables, horizontal rules.
 * Inline: **bold**, *italic*, `code`, [text](url) and bare http(s) links.
 * Links are kept only for http, https, mailto and tel, and same-site paths.
 */

const LIST_BULLET = /^\s*[-*•]\s+(.*)$/u;
const LIST_NUMBER = /^\s*([0-9٠-٩]+)[.)]\s+(.*)$/u;
const HEADING = /^\s*(#{1,6})\s+(.*)$/u;
const TABLE_ROW = /^\s*\|(.+)\|\s*$/u;
const TABLE_RULE = /^\s*\|?\s*:?-{2,}:?\s*(\|\s*:?-{2,}:?\s*)*\|?\s*$/u;
const RULE = /^\s*(?:-{3,}|\*{3,}|_{3,})\s*$/u;

export function parseMarkdown(source) {
    const lines = String(source ?? '').replace(/\r\n?/g, '\n').split('\n');
    const blocks = [];
    let paragraph = [];
    let list = null;

    const flushParagraph = () => {
        if (paragraph.length) {
            blocks.push({ type: 'paragraph', children: parseInline(paragraph.join('\n')) });
            paragraph = [];
        }
    };
    const flushList = () => {
        if (list) {
            blocks.push(list);
            list = null;
        }
    };

    for (let index = 0; index < lines.length; index++) {
        const line = lines[index];

        if (line.trim() === '') {
            flushParagraph();
            flushList();
            continue;
        }

        if (RULE.test(line)) {
            flushParagraph();
            flushList();
            blocks.push({ type: 'rule' });
            continue;
        }

        const heading = line.match(HEADING);
        if (heading) {
            flushParagraph();
            flushList();
            blocks.push({ type: 'heading', children: parseInline(heading[2].replace(/\s+#+\s*$/u, '')) });
            continue;
        }

        if (TABLE_ROW.test(line) && index + 1 < lines.length && TABLE_RULE.test(lines[index + 1])) {
            flushParagraph();
            flushList();
            const header = splitRow(line);
            const rows = [];
            index += 2;
            while (index < lines.length && TABLE_ROW.test(lines[index])) {
                rows.push(splitRow(lines[index]));
                index++;
            }
            index--;
            blocks.push({
                type: 'table',
                header: header.map(parseInline),
                rows: rows.map((row) => header.map((_, column) => parseInline(row[column] ?? ''))),
            });
            continue;
        }

        const bullet = line.match(LIST_BULLET);
        const number = bullet ? null : line.match(LIST_NUMBER);
        if (bullet || number) {
            flushParagraph();
            const ordered = Boolean(number);
            if (!list || list.ordered !== ordered) {
                flushList();
                list = { type: 'list', ordered, start: ordered ? toNumber(number[1]) : 1, items: [] };
            }
            list.items.push(parseInline((bullet ? bullet[1] : number[2]).trim()));
            continue;
        }

        // An indented line right after a list item continues that item.
        if (list && /^\s{2,}\S/u.test(line)) {
            const last = list.items[list.items.length - 1];
            last.push({ type: 'text', text: ' ' }, ...parseInline(line.trim()));
            continue;
        }

        flushList();
        paragraph.push(line.trim());
    }

    flushParagraph();
    flushList();

    return blocks;
}

function splitRow(line) {
    return line.trim().replace(/^\|/u, '').replace(/\|$/u, '').split('|').map((cell) => cell.trim());
}

function toNumber(digits) {
    const western = digits.replace(/[٠-٩]/gu, (digit) => String(digit.charCodeAt(0) - 0x0660));
    const value = Number.parseInt(western, 10);

    return Number.isFinite(value) && value > 0 ? value : 1;
}

const INLINE = new RegExp(
    [
        '\\[([^\\]\\n]{1,300})\\]\\(([^)\\s]{1,2000})\\)', // 1,2 link
        '\\*\\*([^\\n]+?)\\*\\*', // 3 bold
        '__([^\\n]+?)__', // 4 bold
        '`([^`\\n]{1,300})`', // 5 code
        '(?<![\\p{L}\\p{N}*])\\*(?!\\s)([^*\\n]+?)(?<!\\s)\\*(?![\\p{L}\\p{N}*])', // 6 italic
        '(https?:\\/\\/[^\\s<>()]+[^\\s<>().,;:!?\\u060C\\u061B\\u061F"\'])', // 7 bare url
    ].join('|'),
    'gu',
);

export function parseInline(text) {
    const nodes = [];
    const source = String(text ?? '');
    let last = 0;

    INLINE.lastIndex = 0;
    for (const match of source.matchAll(INLINE)) {
        if (match.index > last) {
            pushText(nodes, source.slice(last, match.index));
        }

        if (match[1] !== undefined) {
            const href = safeHref(match[2]);
            const children = parseInline(match[1]).filter((node) => node.type !== 'link');
            nodes.push(href ? { type: 'link', href, children } : { type: 'text', text: match[1] });
        } else if (match[3] !== undefined || match[4] !== undefined) {
            nodes.push({ type: 'strong', children: parseInline(match[3] ?? match[4]) });
        } else if (match[5] !== undefined) {
            nodes.push({ type: 'code', text: match[5] });
        } else if (match[6] !== undefined) {
            nodes.push({ type: 'em', children: parseInline(match[6]) });
        } else if (match[7] !== undefined) {
            const href = safeHref(match[7]);
            nodes.push(href ? { type: 'link', href, children: [{ type: 'text', text: match[7] }] } : { type: 'text', text: match[7] });
        }

        last = match.index + match[0].length;
    }

    if (last < source.length) {
        pushText(nodes, source.slice(last));
    }

    return nodes;
}

function pushText(nodes, text) {
    const previous = nodes[nodes.length - 1];
    if (previous && previous.type === 'text') {
        previous.text += text;
    } else if (text !== '') {
        nodes.push({ type: 'text', text });
    }
}

/**
 * Returns a link target that is safe to put in href, or null.
 */
export function safeHref(raw, origin = typeof window !== 'undefined' ? window.location.origin : 'https://spu.edu.sy') {
    const value = String(raw ?? '').trim();
    if (value === '' || /[\u0000-\u001f\s]/u.test(value)) {
        return null;
    }

    if (/^\/(?!\/)/u.test(value)) {
        return value;
    }

    if (/^(mailto|tel):/iu.test(value)) {
        return value.replace(/^(mailto|tel):/iu, (scheme) => scheme.toLowerCase());
    }

    try {
        const url = new URL(value, origin);

        return url.protocol === 'https:' || url.protocol === 'http:' ? url.href : null;
    } catch {
        return null;
    }
}

export function isExternal(href, origin = typeof window !== 'undefined' ? window.location.origin : '') {
    if (!/^https?:/iu.test(href)) {
        return false;
    }

    try {
        return new URL(href).origin !== origin;
    } catch {
        return true;
    }
}

/**
 * Plain text of a Markdown answer, for the copy button.
 */
export function markdownToText(source) {
    const inline = (nodes) => nodes.map((node) => (node.type === 'text' || node.type === 'code' ? node.text : inline(node.children ?? []))).join('');

    return parseMarkdown(source)
        .map((block) => {
            switch (block.type) {
                case 'list':
                    return block.items.map((item, index) => `${block.ordered ? `${block.start + index}.` : '•'} ${inline(item)}`).join('\n');
                case 'table':
                    return [block.header, ...block.rows].map((row) => row.map(inline).join(' | ')).join('\n');
                case 'rule':
                    return '';
                default:
                    return inline(block.children);
            }
        })
        .filter((text) => text !== '')
        .join('\n\n');
}

export function renderMarkdown(source, doc = document) {
    const fragment = doc.createDocumentFragment();

    for (const block of parseMarkdown(source)) {
        fragment.append(renderBlock(block, doc));
    }

    return fragment;
}

function renderBlock(block, doc) {
    switch (block.type) {
        case 'heading': {
            const element = doc.createElement('p');
            element.className = 'spu-chatbot__md-heading';
            appendInline(element, block.children, doc);

            return element;
        }
        case 'list': {
            const element = doc.createElement(block.ordered ? 'ol' : 'ul');
            if (block.ordered && block.start !== 1) {
                element.setAttribute('start', String(block.start));
            }
            for (const item of block.items) {
                const li = doc.createElement('li');
                appendInline(li, item, doc);
                element.append(li);
            }

            return element;
        }
        case 'table': {
            const wrapper = doc.createElement('div');
            wrapper.className = 'spu-chatbot__md-table';
            const table = doc.createElement('table');
            const head = doc.createElement('thead');
            const headRow = doc.createElement('tr');
            for (const cell of block.header) {
                const th = doc.createElement('th');
                th.setAttribute('scope', 'col');
                appendInline(th, cell, doc);
                headRow.append(th);
            }
            head.append(headRow);
            const body = doc.createElement('tbody');
            for (const row of block.rows) {
                const tr = doc.createElement('tr');
                for (const cell of row) {
                    const td = doc.createElement('td');
                    appendInline(td, cell, doc);
                    tr.append(td);
                }
                body.append(tr);
            }
            table.append(head, body);
            wrapper.append(table);

            return wrapper;
        }
        case 'rule':
            return doc.createElement('hr');
        default: {
            const element = doc.createElement('p');
            appendInline(element, block.children, doc);

            return element;
        }
    }
}

function appendInline(parent, nodes, doc) {
    for (const node of nodes) {
        switch (node.type) {
            case 'strong':
            case 'em': {
                const element = doc.createElement(node.type);
                appendInline(element, node.children, doc);
                parent.append(element);
                break;
            }
            case 'code': {
                const element = doc.createElement('code');
                element.textContent = node.text;
                parent.append(element);
                break;
            }
            case 'link': {
                const element = doc.createElement('a');
                element.href = node.href;
                if (isExternal(node.href)) {
                    element.target = '_blank';
                    element.rel = 'noopener noreferrer nofollow';
                }
                appendInline(element, node.children, doc);
                parent.append(element);
                break;
            }
            default:
                appendText(parent, node.text, doc);
        }
    }
}

function appendText(parent, text, doc) {
    const parts = String(text).split('\n');
    parts.forEach((part, index) => {
        if (index > 0) {
            parent.append(doc.createElement('br'));
        }
        if (part !== '') {
            parent.append(doc.createTextNode(part));
        }
    });
}
