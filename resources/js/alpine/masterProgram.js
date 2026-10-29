import { planEdgePaths } from './studyPlan.js';

const SVG_NS = 'http://www.w3.org/2000/svg';

/**
 * Each course's directly related courses: its prerequisites and the courses it
 * opens. `courses` is [{code, prerequisites: [code]}].
 */
export function masterProgramRelations(courses) {
    const related = new Map(courses.map((course) => [course.code, new Set()]));

    courses.forEach((course) => {
        (course.prerequisites || []).forEach((code) => {
            if (!related.has(code)) return;
            related.get(course.code).add(code);
            related.get(code).add(course.code);
        });
    });

    return related;
}

function courseCards(root) {
    return Array.from(root.querySelectorAll('[data-master-course]'));
}

function cardCourse(card) {
    return {
        code: card.dataset.masterCourse,
        prerequisites: (card.dataset.prerequisites || '').split(' ').filter(Boolean),
    };
}

function drawEdges(root, cards) {
    const board = root.querySelector('[data-master-board]');
    const svg = root.querySelector('[data-master-edges]');
    if (!board || !svg) return;

    const origin = board.getBoundingClientRect();
    const geometry = new Map(cards.map((card) => {
        const rect = card.getBoundingClientRect();

        return [card.dataset.masterCourse, {
            left: rect.left - origin.left,
            right: rect.right - origin.left,
            centerY: rect.top - origin.top + (rect.height / 2),
            height: rect.height,
        }];
    }));
    const courses = cards.map((card) => {
        const course = cardCourse(card);

        return { id: course.code, prerequisites: course.prerequisites };
    });

    svg.setAttribute('viewBox', `0 0 ${Math.round(origin.width)} ${Math.round(origin.height)}`);
    svg.replaceChildren(...planEdgePaths(geometry, courses).map((edge) => {
        const path = document.createElementNS(SVG_NS, 'path');
        path.setAttribute('d', edge.path);
        path.dataset.source = edge.sourceId;
        path.dataset.target = edge.targetId;

        return path;
    }));
}

function createMasterProgramPage(root) {
    const cards = courseCards(root);
    const relations = masterProgramRelations(cards.map(cardCourse));

    const highlight = (code) => {
        const related = relations.get(code) || new Set();
        root.dataset.highlighting = 'true';
        cards.forEach((card) => {
            const own = card.dataset.masterCourse === code;
            card.toggleAttribute('data-active', own);
            card.toggleAttribute('data-related', related.has(card.dataset.masterCourse));
        });
        root.querySelectorAll('[data-master-edges] path').forEach((path) => {
            path.toggleAttribute('data-active', path.dataset.source === code || path.dataset.target === code);
        });
    };

    const clear = () => {
        delete root.dataset.highlighting;
        cards.forEach((card) => {
            card.removeAttribute('data-active');
            card.removeAttribute('data-related');
        });
        root.querySelectorAll('[data-master-edges] path[data-active]').forEach((path) => path.removeAttribute('data-active'));
    };

    const openCourse = (code) => {
        const dialog = root.querySelector(`dialog[data-master-dialog="${CSS.escape(code)}"]`);
        if (!dialog || typeof dialog.showModal !== 'function') return;
        root.querySelectorAll('dialog[open]').forEach((open) => open.close());
        dialog.showModal();
    };

    return {
        init() {
            cards.forEach((card) => {
                const code = card.dataset.masterCourse;
                card.addEventListener('mouseenter', () => highlight(code));
                card.addEventListener('mouseleave', clear);
                card.addEventListener('focus', () => highlight(code));
                card.addEventListener('blur', clear);
                card.addEventListener('click', () => openCourse(code));
            });

            root.querySelectorAll('dialog[data-master-dialog]').forEach((dialog) => {
                // A click on the backdrop (outside the panel) closes the dialog.
                dialog.addEventListener('click', (event) => {
                    if (event.target === dialog) dialog.close();
                });
                dialog.addEventListener('close', () => {
                    const card = cards.find((item) => item.dataset.masterCourse === dialog.dataset.masterDialog);
                    if (card && !root.querySelector('dialog[open]')) card.focus({ preventScroll: true });
                });
            });

            root.querySelectorAll('[data-master-close]').forEach((button) => {
                button.addEventListener('click', () => button.closest('dialog')?.close());
            });

            root.querySelectorAll('[data-master-open]').forEach((button) => {
                button.addEventListener('click', () => openCourse(button.dataset.masterOpen));
            });

            const redraw = () => drawEdges(root, cards);
            redraw();

            if (typeof ResizeObserver === 'function') {
                const board = root.querySelector('[data-master-board]');
                if (board) new ResizeObserver(redraw).observe(board);
            } else {
                window.addEventListener('resize', redraw);
            }

            document.fonts?.ready?.then(redraw);
        },
    };
}

export function initMasterProgramPages() {
    document.querySelectorAll('[data-master-program]').forEach((root) => {
        createMasterProgramPage(root).init();
    });
}
