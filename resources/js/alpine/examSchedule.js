import { planEdgePaths } from './studyPlan.js';

const SVG_NS = 'http://www.w3.org/2000/svg';
const DAY_MS = 24 * 60 * 60 * 1000;

/** The same folding as App\Support\SearchTextNormalizer, so a query matches the indexed card text. */
export function foldExamText(value) {
    return String(value ?? '')
        .toLowerCase()
        .replace(/[ً-ْٰـ]/g, '')
        .replace(/[أإآٱ]/g, 'ا')
        .replace(/[ىئ]/g, 'ي')
        .replace(/ة/g, 'ه')
        .replace(/ؤ/g, 'و')
        .replace(/[٠-٩]/g, (digit) => String(digit.charCodeAt(0) - 0x0660))
        .replace(/\s+/g, ' ')
        .trim();
}

/** Whether an exam shows under the picked year and the search words (every word, any order). */
export function examMatches(exam, { year = '', query = '' } = {}) {
    if (year !== '' && exam.year !== year) return false;

    const words = foldExamText(query).split(' ').filter(Boolean);

    return words.every((word) => (exam.search || '').includes(word));
}

/** Whole days from `today` to `iso` (both `Y-m-d`), or null when either can't be read. */
export function daysUntil(iso, today) {
    const parse = (value) => {
        const match = /^(\d{4})-(\d{2})-(\d{2})$/.exec(value || '');

        return match ? Date.UTC(Number(match[1]), Number(match[2]) - 1, Number(match[3])) : null;
    };
    const target = parse(iso);
    const start = parse(today);

    return target === null || start === null ? null : Math.round((target - start) / DAY_MS);
}

/**
 * Each exam day's state against today: 'done', 'today', 'next' (the first day still to
 * come) or '' (later, or a date that can't be read). `isos` are in board order.
 */
export function examDayStates(isos, today) {
    let nextFound = false;

    return isos.map((iso) => {
        const days = daysUntil(iso, today);
        if (days === null) return '';
        if (days < 0) return 'done';
        if (days === 0) {
            nextFound = true;
            return 'today';
        }
        if (!nextFound) {
            nextFound = true;
            return 'next';
        }

        return '';
    });
}

function localToday() {
    const now = new Date();
    const pad = (value) => String(value).padStart(2, '0');

    return `${now.getFullYear()}-${pad(now.getMonth() + 1)}-${pad(now.getDate())}`;
}

function cardExam(card) {
    return {
        id: card.dataset.exam,
        year: card.dataset.year || '',
        previous: card.dataset.previous || '',
        search: card.dataset.search || '',
    };
}

function createExamSchedule(root) {
    const cards = Array.from(root.querySelectorAll('[data-exam]'));
    const cardById = new Map(cards.map((card) => [card.dataset.exam, card]));
    const days = Array.from(root.querySelectorAll('[data-exam-day]'));
    const yearButtons = Array.from(root.querySelectorAll('[data-exam-year]'));
    const viewButtons = Array.from(root.querySelectorAll('[data-exam-view]'));
    const search = root.querySelector('[data-exam-search]');
    const dialog = root.querySelector('[data-exam-dialog]');
    const board = root.querySelector('[data-exam-board]');
    const svg = root.querySelector('[data-exam-edges]');
    const labels = root.dataset;
    const today = localToday();
    const state = { year: '', query: '', view: 'board' };
    let opened = null;

    const visibleCards = () => cards.filter((card) => !card.hidden);

    const drawEdges = () => {
        if (!board || !svg || state.view !== 'board') return;

        const origin = board.getBoundingClientRect();
        const shown = visibleCards();
        const shownIds = new Set(shown.map((card) => card.dataset.exam));
        const geometry = new Map(shown.map((card) => {
            const rect = card.getBoundingClientRect();

            return [card.dataset.exam, {
                left: rect.left - origin.left,
                right: rect.right - origin.left,
                centerY: rect.top - origin.top + (rect.height / 2),
                height: rect.height,
            }];
        }));

        // A hidden exam is skipped, so the line joins the year's closest shown exams.
        const byId = new Map(cards.map((card) => [card.dataset.exam, cardExam(card)]));
        const previousShown = (exam) => {
            let previous = exam.previous;
            while (previous && !shownIds.has(previous)) previous = byId.get(previous)?.previous || '';

            return previous;
        };
        const exams = shown.map((card) => {
            const exam = cardExam(card);
            const previous = previousShown(exam);

            return { id: exam.id, prerequisites: previous ? [previous] : [] };
        });

        svg.setAttribute('viewBox', `0 0 ${Math.round(origin.width)} ${Math.round(origin.height)}`);
        svg.replaceChildren(...planEdgePaths(geometry, exams).map((edge) => {
            const path = document.createElementNS(SVG_NS, 'path');
            path.setAttribute('d', edge.path);
            const target = cardById.get(edge.targetId);
            path.dataset.year = target?.dataset.year || '';
            path.style.setProperty('--es-year', target?.style.getPropertyValue('--es-year') || '');

            return path;
        }));
        svg.toggleAttribute('data-year-picked', state.year !== '');
    };

    const apply = () => {
        let count = 0;
        cards.forEach((card) => {
            const show = examMatches(cardExam(card), state);
            card.hidden = !show;
            const row = root.querySelector(`[data-exam-row="${CSS.escape(card.dataset.exam)}"]`);
            if (row) row.hidden = !show;
            if (show) count += 1;
        });
        days.forEach((day) => {
            day.hidden = !day.querySelector('[data-exam]:not([hidden])');
        });

        yearButtons.forEach((button) => button.setAttribute('aria-pressed', String(button.dataset.examYear === state.year)));
        root.querySelector('[data-exam-count]').textContent = String(count);
        root.querySelector('[data-exam-empty]').hidden = count > 0;
        root.querySelectorAll('[data-exam-panel]').forEach((panel) => {
            panel.hidden = count === 0 || panel.dataset.examPanel !== state.view;
        });
        viewButtons.forEach((button) => button.setAttribute('aria-pressed', String(button.dataset.examView === state.view)));

        drawEdges();
    };

    const highlight = (card) => {
        const year = card.dataset.year;
        root.dataset.highlighting = 'true';
        cards.forEach((other) => {
            other.toggleAttribute('data-active', other === card);
            other.toggleAttribute('data-related', other !== card && year !== '' && other.dataset.year === year);
        });
        svg?.querySelectorAll('path').forEach((path) => path.toggleAttribute('data-active', year !== '' && path.dataset.year === year));
    };

    const clear = () => {
        delete root.dataset.highlighting;
        cards.forEach((card) => {
            card.removeAttribute('data-active');
            card.removeAttribute('data-related');
        });
        svg?.querySelectorAll('path[data-active]').forEach((path) => path.removeAttribute('data-active'));
    };

    const statusLabel = (iso) => {
        const days = daysUntil(iso, today);
        if (days === null) return '';
        if (days < 0) return labels.labelDone;
        if (days === 0) return labels.labelToday;
        if (days === 1) return labels.labelTomorrow;

        return (labels.labelInDays || '').replace(':n', String(days));
    };

    const fillDialog = (card) => {
        const data = card.dataset;
        const yearBadge = dialog.querySelector('[data-dialog-year]');
        yearBadge.textContent = data.yearLabel || '';
        yearBadge.hidden = !data.yearLabel;
        dialog.style.setProperty('--es-year', card.style.getPropertyValue('--es-year'));
        dialog.querySelector('[data-dialog-course]').textContent = data.course || '';

        const status = dialog.querySelector('[data-dialog-status]');
        const statusText = statusLabel(data.iso);
        status.textContent = statusText;
        status.hidden = statusText === '';
        status.toggleAttribute('data-done', (daysUntil(data.iso, today) ?? 0) < 0);

        dialog.querySelectorAll('[data-dialog-fact]').forEach((fact) => {
            const value = data[fact.dataset.dialogFact] || '';
            fact.querySelector('[data-dialog-value]').textContent = value;
            fact.hidden = value === '';
        });

        const path = dialog.querySelector('[data-dialog-path]');
        const sameYear = data.year ? cards.filter((other) => other.dataset.year === data.year) : [];
        dialog.querySelector('[data-dialog-path-wrap]').hidden = sameYear.length < 2;
        path.replaceChildren(...sameYear.map((other) => {
            const item = document.createElement('li');
            const button = document.createElement('button');
            button.type = 'button';
            button.className = 'es-path-item';
            if (other === card) button.setAttribute('aria-current', 'true');
            const when = document.createElement('span');
            when.className = 'es-path-when';
            when.textContent = [other.dataset.day, other.dataset.date, other.dataset.time].filter(Boolean).join(' · ');
            const course = document.createElement('span');
            course.className = 'es-path-course';
            course.dir = 'auto';
            course.textContent = other.dataset.course || '';
            button.append(course, when);
            button.addEventListener('click', () => {
                opened = other;
                fillDialog(other);
            });
            item.append(button);

            return item;
        }));
    };

    const open = (card) => {
        if (!dialog || typeof dialog.showModal !== 'function') return;
        opened = card;
        fillDialog(card);
        dialog.showModal();
    };

    return {
        init() {
            // Day badges: done, today and the next exam day, against the visitor's own date.
            const states = examDayStates(days.map((day) => day.dataset.iso || ''), today);
            days.forEach((day, index) => {
                const badge = day.querySelector('[data-exam-day-badge]');
                const dayState = states[index];
                if (dayState) day.dataset.state = dayState;
                if (badge && dayState) {
                    badge.textContent = { done: labels.labelDone, today: labels.labelToday, next: labels.labelNext }[dayState];
                    badge.hidden = false;
                }
            });

            cards.forEach((card) => {
                card.addEventListener('mouseenter', () => highlight(card));
                card.addEventListener('mouseleave', clear);
                card.addEventListener('focus', () => highlight(card));
                card.addEventListener('blur', clear);
                card.addEventListener('click', () => open(card));
            });

            yearButtons.forEach((button) => button.addEventListener('click', () => {
                state.year = button.dataset.examYear || '';
                apply();
            }));

            viewButtons.forEach((button) => button.addEventListener('click', () => {
                state.view = button.dataset.examView;
                apply();
            }));

            search?.addEventListener('input', () => {
                state.query = search.value;
                apply();
            });

            root.querySelector('[data-exam-reset]')?.addEventListener('click', () => {
                state.year = '';
                state.query = '';
                if (search) search.value = '';
                apply();
            });

            if (dialog) {
                dialog.addEventListener('click', (event) => {
                    if (event.target === dialog) dialog.close();
                });
                dialog.querySelector('[data-exam-close]')?.addEventListener('click', () => dialog.close());
                dialog.addEventListener('close', () => {
                    if (opened && !opened.hidden) opened.focus({ preventScroll: true });
                });
            }

            apply();

            // Scroll the next exam day into view on narrow screens, where days scroll sideways.
            const upcoming = days.find((day) => day.dataset.state === 'today' || day.dataset.state === 'next');
            const scroller = root.querySelector('.es-scroller');
            if (upcoming && scroller && scroller.scrollWidth > scroller.clientWidth) {
                scroller.scrollLeft = upcoming.offsetLeft - 16;
            }

            if (typeof ResizeObserver === 'function' && board) {
                new ResizeObserver(drawEdges).observe(board);
            } else {
                window.addEventListener('resize', drawEdges);
            }
            document.fonts?.ready?.then(drawEdges);
        },
    };
}

export function initExamSchedules() {
    document.querySelectorAll('[data-exam-schedule]').forEach((root) => {
        createExamSchedule(root).init();
    });
}
