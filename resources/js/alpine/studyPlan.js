const TYPE_STYLES = {
    university: { shortLabelEn: 'UNIV', shortLabelAr: 'جامعي' },
    faculty: { shortLabelEn: 'FAC', shortLabelAr: 'كلية' },
    specialization: { shortLabelEn: 'SPEC', shortLabelAr: 'تخصص' },
    elective: { shortLabelEn: 'ELEC', shortLabelAr: 'اختياري' },
};

const FAN_STEP = 10;
const MOBILE_QUERY = '(max-width: 767px)';
const DRAG_THRESHOLD = 6;
const VIEW_STATE_VERSION = 'v2';

export function modalFocusTarget(focusable, activeElement, shiftKey) {
    if (focusable.length === 0) return null;

    const activeIndex = focusable.indexOf(activeElement);
    if (activeIndex === -1) return focusable[0];
    if (shiftKey && activeIndex === 0) return focusable[focusable.length - 1];
    if (!shiftKey && activeIndex === focusable.length - 1) return focusable[0];

    return null;
}

// The graph is always laid out left to right (term 1 on the left), also in Arabic, so the
// arrow keys follow the screen direction and are not mirrored.
export function studyPlanKeyboardAction(key) {
    return {
        ArrowLeft: { panX: 40, panY: 0, zoom: 0 },
        ArrowRight: { panX: -40, panY: 0, zoom: 0 },
        ArrowUp: { panX: 0, panY: 40, zoom: 0 },
        ArrowDown: { panX: 0, panY: -40, zoom: 0 },
        '+': { panX: 0, panY: 0, zoom: -0.2 },
        '=': { panX: 0, panY: 0, zoom: -0.2 },
        '-': { panX: 0, panY: 0, zoom: 0.2 },
    }[key] || null;
}

export function bindCourseCardInteractions(card, { highlight, clear, open }) {
    const courseId = card.dataset.courseId || '';

    card.addEventListener('mouseenter', () => highlight(courseId));
    card.addEventListener('mouseleave', clear);
    card.addEventListener('focus', () => highlight(courseId));
    card.addEventListener('blur', clear);
    card.addEventListener('click', () => open(courseId, card));
}

export function localizedStudyPlanText(item, key, isAr) {
    const suffix = isAr ? 'Ar' : 'En';

    return item?.[key] || item?.[`${key}${suffix}`] || item?.[`${key}En`] || item?.[`${key}Ar`] || '';
}

function flattenCourses(department) {
    return (department?.terms || []).flatMap((term) => term.courses || []);
}

export function transitiveReduction(courses) {
    const adj = new Map();
    const edges = [];

    courses.forEach((course) => {
        if (!adj.has(course.id)) adj.set(course.id, new Set());
        (course.prerequisites || []).forEach((prereqId) => {
            if (!adj.has(prereqId)) adj.set(prereqId, new Set());
            adj.get(prereqId).add(course.id);
            edges.push({ source: prereqId, target: course.id });
        });
    });

    const reduced = [];

    edges.forEach((edge) => {
        adj.get(edge.source).delete(edge.target);

        const visited = new Set();
        const queue = [edge.source];
        let reachable = false;

        while (queue.length) {
            const current = queue.shift();
            if (current === edge.target) {
                reachable = true;
                break;
            }
            if (visited.has(current)) continue;
            visited.add(current);
            (adj.get(current) || []).forEach((next) => {
                if (!visited.has(next)) queue.push(next);
            });
        }

        if (!reachable) {
            adj.get(edge.source).add(edge.target);
            reduced.push(edge);
        }
    });

    return reduced;
}

function fanOffset(index, count, height) {
    const raw = (index * FAN_STEP) - (((count - 1) * FAN_STEP) / 2);
    const limit = Math.max(0, (height / 2) - 8);

    return Math.max(-limit, Math.min(limit, raw));
}

/**
 * SVG path for one prerequisite edge, from the right edge of the source card to the left
 * edge of the target card. Edges that leave or enter the same card are fanned out so they
 * do not share one anchor point. Returns null when the target is not to the right of the
 * source (same term or earlier term), because there is no forward link to draw.
 */
export function buildEdgePath(source, target, fan = {}) {
    const { outIndex = 0, outCount = 1, inIndex = 0, inCount = 1 } = fan;
    const startX = source.right;
    const endX = target.left;
    const gap = endX - startX;

    if (gap < 8) return null;

    const startY = source.centerY + fanOffset(outIndex, outCount, source.height || 0);
    const endY = target.centerY + fanOffset(inIndex, inCount, target.height || 0);
    const curve = Math.min(Math.max(gap * 0.22, 14), gap * 0.32);

    return `M ${startX} ${startY} C ${startX + curve} ${startY} ${endX - curve} ${endY} ${endX} ${endY}`;
}

/**
 * Routes every non-redundant prerequisite edge of a plan. `geometry` maps course id to
 * {left, right, centerY, height}; edges are ordered by the vertical position of the card at the
 * other end so fanned-out lines do not cross next to the card. Edges that do not point forward
 * are left out (see buildEdgePath).
 */
export function planEdgePaths(geometry, courses) {
    const edges = transitiveReduction(courses).filter((edge) => geometry.has(edge.source) && geometry.has(edge.target));
    const outgoing = new Map();
    const incoming = new Map();

    edges.forEach((edge) => {
        if (!outgoing.has(edge.source)) outgoing.set(edge.source, []);
        if (!incoming.has(edge.target)) incoming.set(edge.target, []);
        outgoing.get(edge.source).push(edge);
        incoming.get(edge.target).push(edge);
    });
    outgoing.forEach((list) => list.sort((a, b) => geometry.get(a.target).centerY - geometry.get(b.target).centerY));
    incoming.forEach((list) => list.sort((a, b) => geometry.get(a.source).centerY - geometry.get(b.source).centerY));

    return edges.map((edge) => {
        const outList = outgoing.get(edge.source);
        const inList = incoming.get(edge.target);
        const path = buildEdgePath(geometry.get(edge.source), geometry.get(edge.target), {
            outIndex: outList.indexOf(edge),
            outCount: outList.length,
            inIndex: inList.indexOf(edge),
            inCount: inList.length,
        });

        return path ? { sourceId: edge.source, targetId: edge.target, path } : null;
    }).filter(Boolean);
}

/**
 * Keeps at least `margin` px of the board inside the viewport on every side so a drag or
 * zoom can never lose the graph completely.
 */
export function clampPan({ panX, panY, scale }, viewportWidth, viewportHeight, boardWidth, boardHeight, margin = 80) {
    const scaledWidth = boardWidth * scale;
    const scaledHeight = boardHeight * scale;
    const marginX = Math.min(margin, scaledWidth);
    const marginY = Math.min(margin, scaledHeight);

    return {
        panX: Math.max(marginX - scaledWidth, Math.min(viewportWidth - marginX, panX)),
        panY: Math.max(marginY - scaledHeight, Math.min(viewportHeight - marginY, panY)),
    };
}

export function pinchScale(startScale, startDistance, distance, minScale, maxScale) {
    if (!(startDistance > 0) || !(distance > 0)) return startScale;

    return Math.max(minScale, Math.min(maxScale, startScale * (distance / startDistance)));
}

function typeKey(course) {
    if (!course) return 'faculty';
    if (!course.required) return 'elective';
    return TYPE_STYLES[course.type] ? course.type : 'faculty';
}

function escapeHtml(value) {
    return String(value ?? '')
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
}

function createStudyPlanPage(root) {
    const dataElement = root.querySelector('[data-study-plan-payload]');
    const payload = JSON.parse(dataElement?.textContent || '{}');
    const locale = root.dataset.locale || 'en';
    const isAr = locale === 'ar';
    const facultySlug = root.dataset.facultySlug || '';
    const plan = payload.plan || {};
    const departments = plan.departments || [];
    const urlParams = new URLSearchParams(window.location.search);
    const reducedMotion = window.matchMedia?.('(prefers-reduced-motion: reduce)').matches === true;
    const mobileQuery = window.matchMedia?.(MOBILE_QUERY);

    const state = {
        activeDepartmentId: urlParams.get('department') || departments[0]?.id || '',
        hoveredCourseId: '',
        hoveredPathIds: [],
        modalCourseId: '',
        typeFilter: '',
        scale: 1,
        panX: 0,
        panY: 0,
        lastMouseX: 0,
        lastMouseY: 0,
        minScale: 0.1,
        maxScale: 3,
    };

    const viewport = root.querySelector('[data-study-plan-viewport]');
    const world = root.querySelector('[data-study-plan-world]');
    const board = root.querySelector('[data-study-plan-board]');
    const svg = root.querySelector('[data-study-plan-svg]');
    const svgTop = root.querySelector('[data-study-plan-svg-top]');
    const courseCards = Array.from(root.querySelectorAll('[data-course-card]'));
    const listCards = Array.from(root.querySelectorAll('[data-list-card]'));
    const legendButtons = Array.from(root.querySelectorAll('[data-legend-type]'));
    const viewToggles = Array.from(root.querySelectorAll('[data-study-plan-view-toggle]'));
    const modal = root.querySelector('[data-course-modal]');
    let basePaths = [];
    let activePaths = [];
    let glowPaths = [];
    let modalTrigger = null;
    let graphReady = false;

    const pointers = new Map();
    let pinch = null;
    let dragMoved = false;
    let suppressClick = false;

    function text(item, key) {
        return localizedStudyPlanText(item, key, isAr);
    }

    function activeDepartment() {
        return departments.find((department) => department.id === state.activeDepartmentId) || departments[0] || null;
    }

    function courses() {
        return flattenCourses(activeDepartment());
    }

    function courseById(courseId) {
        return courses().find((course) => course.id === courseId) || null;
    }

    function prerequisites(course) {
        return (course?.prerequisites || []).map((courseId) => courseById(courseId)).filter(Boolean);
    }

    function openerCourses(course) {
        return courses().filter((item) => (item.prerequisites || []).includes(course?.id));
    }

    function computePath(courseId) {
        const allCourses = courses();
        const path = new Set([courseId]);

        const addPrereqs = (id) => {
            const course = allCourses.find((item) => item.id === id);
            if (!course) return;

            (course.prerequisites || []).forEach((prereqId) => {
                if (!path.has(prereqId)) {
                    path.add(prereqId);
                    addPrereqs(prereqId);
                }
            });
        };

        const addDependents = (id) => {
            allCourses.forEach((course) => {
                if ((course.prerequisites || []).includes(id) && !path.has(course.id)) {
                    path.add(course.id);
                    addDependents(course.id);
                }
            });
        };

        addPrereqs(courseId);
        addDependents(courseId);

        return Array.from(path);
    }

    function courseHref(courseId) {
        const params = new URLSearchParams();
        params.set('department', state.activeDepartmentId);
        params.set('course', courseId);

        return `/${locale}/faculties/${facultySlug}/study-plan/course?${params.toString()}`;
    }

    function typeShortLabel(course) {
        const style = TYPE_STYLES[typeKey(course)] || TYPE_STYLES.faculty;

        return isAr ? style.shortLabelAr : style.shortLabelEn;
    }

    function setHoveredCourse(courseId) {
        state.hoveredCourseId = courseId;
        state.hoveredPathIds = courseId ? computePath(courseId) : [];
        updateCourseAndPathState();
    }

    function updateCourseAndPathState() {
        const hasHover = state.hoveredCourseId !== '';
        const hasFilter = state.typeFilter !== '';

        courseCards.forEach((card) => {
            const courseId = card.dataset.courseId || '';
            const inPath = state.hoveredPathIds.includes(courseId);
            const isHovered = state.hoveredCourseId === courseId;
            const dimByHover = hasHover && !inPath;
            const dimByFilter = !hasHover && hasFilter && card.dataset.courseType !== state.typeFilter;

            card.classList.toggle('opacity-25', dimByHover || dimByFilter);
            card.classList.toggle('scale-[0.97]', dimByHover);
            card.classList.toggle('blur-[0.4px]', dimByHover);
            card.classList.toggle('ring-2', isHovered);
            card.classList.toggle('ring-indigo-400', isHovered);
            card.classList.toggle('z-50', isHovered);
            card.classList.toggle('scale-[1.03]', isHovered);
            card.classList.toggle('ring-1', !isHovered && inPath && hasHover);
            card.classList.toggle('ring-indigo-300/40', !isHovered && inPath && hasHover);
            card.classList.toggle('z-40', !isHovered && inPath && hasHover);
        });

        basePaths.forEach((path) => {
            const sourceId = path.dataset.sourceId || '';
            const targetId = path.dataset.targetId || '';
            const inPath = state.hoveredPathIds.includes(sourceId) && state.hoveredPathIds.includes(targetId);
            path.style.opacity = hasHover && !inPath ? '0.12' : '1';
            path.style.filter = hasHover && !inPath ? 'blur(0.5px)' : '';
        });

        [...activePaths, ...glowPaths].forEach((path) => {
            const sourceId = path.dataset.sourceId || '';
            const targetId = path.dataset.targetId || '';
            const inPath = state.hoveredPathIds.includes(sourceId) && state.hoveredPathIds.includes(targetId);
            path.classList.toggle('hidden', !inPath);
        });
    }

    function applyTypeFilter(type) {
        state.typeFilter = state.typeFilter === type ? '' : type;

        legendButtons.forEach((button) => {
            button.setAttribute('aria-pressed', button.dataset.legendType === state.typeFilter ? 'true' : 'false');
        });
        listCards.forEach((card) => {
            const dim = state.typeFilter !== '' && card.dataset.courseType !== state.typeFilter;
            card.classList.toggle('opacity-30', dim);
        });
        updateCourseAndPathState();
    }

    function elementOffsetWithin(element, ancestor) {
        let left = 0;
        let top = 0;
        let current = element;

        while (current && current !== ancestor) {
            left += current.offsetLeft;
            top += current.offsetTop;
            current = current.offsetParent;
        }

        return { left, top };
    }

    function cardGeometryMap() {
        const map = new Map();

        courseCards.forEach((card) => {
            if (!board) return;

            const offset = elementOffsetWithin(card, board);
            map.set(card.dataset.courseId || '', {
                left: offset.left,
                right: offset.left + card.offsetWidth,
                centerY: offset.top + (card.offsetHeight / 2),
                height: card.offsetHeight,
            });
        });

        return map;
    }

    function dependencyPaths() {
        return planEdgePaths(cardGeometryMap(), courses());
    }

    function createSvgPath(attributes) {
        const path = document.createElementNS('http://www.w3.org/2000/svg', 'path');
        Object.entries(attributes).forEach(([key, value]) => {
            path.setAttribute(key, value);
        });

        return path;
    }

    function renderDependencyPaths() {
        if (!svg) return;

        [svg, svgTop].forEach((layer) => layer?.querySelectorAll('[data-generated-path]').forEach((path) => path.remove()));

        const accent = /^#[0-9a-fA-F]{6}$/.test(plan.accent || '') ? plan.accent : '#202759';
        const highlightLayer = svgTop || svg;

        dependencyPaths().forEach((connector) => {
            const common = {
                'data-generated-path': '1',
                'data-source-id': connector.sourceId,
                'data-target-id': connector.targetId,
                d: connector.path,
                fill: 'none',
                'stroke-linecap': 'round',
                'stroke-linejoin': 'round',
            };

            svg.appendChild(createSvgPath({
                ...common,
                'data-path-base': '1',
                stroke: '#94a3b8',
                'stroke-width': '1.2',
                'marker-end': 'url(#sp-arrow)',
                style: reducedMotion ? '' : 'transition: all 0.3s;',
            }));

            // Highlighted lines live in a layer above the cards so a selected path stays
            // visible where it crosses other cards.
            highlightLayer.appendChild(createSvgPath({
                ...common,
                'data-path-glow': '1',
                stroke: accent,
                'stroke-width': '6',
                'stroke-opacity': '0.25',
                class: 'sp-path-glow hidden',
            }));

            highlightLayer.appendChild(createSvgPath({
                ...common,
                'data-path-active': '1',
                stroke: accent,
                'stroke-width': '2.5',
                'marker-end': 'url(#sp-arrow-path)',
                class: `${reducedMotion ? '' : 'sp-path-animated'} hidden`,
                style: reducedMotion ? '' : 'transition: all 0.3s;',
            }));
        });

        basePaths = Array.from(root.querySelectorAll('[data-path-base]'));
        activePaths = Array.from(root.querySelectorAll('[data-path-active]'));
        glowPaths = Array.from(root.querySelectorAll('[data-path-glow]'));
        updateCourseAndPathState();
    }

    function modalHtmlList(items) {
        return items.map((item) => (
            `<span class="text-[11px] font-bold text-spu-blue bg-spu-blue/5 px-2.5 py-1 rounded-md border border-spu-blue/10">${escapeHtml(item.code)} - ${escapeHtml(text(item, 'title'))}</span>`
        )).join('');
    }

    function openModal(courseId, trigger = null) {
        const course = courseById(courseId);
        if (!course || !modal) return;

        state.modalCourseId = courseId;
        modalTrigger = trigger || (document.activeElement instanceof HTMLElement ? document.activeElement : null);
        setHoveredCourse(courseId);

        modal.querySelector('[data-modal-code]').textContent = course.code || '';
        modal.querySelector('[data-modal-credits]').textContent = `${course.credits || 0}cr`;
        modal.querySelector('[data-modal-type]').textContent = typeShortLabel(course);
        modal.querySelector('[data-modal-type]').className = `text-[10px] font-bold uppercase tracking-wider ${typeKey(course) === 'elective' ? 'text-slate-400' : 'text-spu-blue/60'}`;
        modal.querySelector('[data-modal-title]').textContent = text(course, 'title');
        modal.querySelector('[data-modal-description]').textContent = text(course, 'description');
        modal.querySelector('[data-modal-details]').setAttribute('href', courseHref(course.id));

        const creditRule = modal.querySelector('[data-modal-credit-rule]');
        const requiredCredits = Number(course.prerequisiteCredits) || 0;
        if (creditRule) {
            creditRule.textContent = requiredCredits > 0
                ? (isAr ? `يتطلب إنجاز ${requiredCredits} ساعة معتمدة` : `Requires ${requiredCredits} completed credits`)
                : '';
            creditRule.classList.toggle('hidden', requiredCredits === 0);
        }

        const prereqItems = prerequisites(course);
        const openerItems = openerCourses(course);
        const prereqWrap = modal.querySelector('[data-modal-prerequisites-wrap]');
        const openerWrap = modal.querySelector('[data-modal-openers-wrap]');
        modal.querySelector('[data-modal-prerequisites]').innerHTML = modalHtmlList(prereqItems);
        modal.querySelector('[data-modal-openers]').innerHTML = modalHtmlList(openerItems);
        prereqWrap.classList.toggle('hidden', prereqItems.length === 0);
        openerWrap.classList.toggle('hidden', openerItems.length === 0);

        modal.classList.remove('hidden');
        modal.classList.add('flex');
        modal.setAttribute('aria-hidden', 'false');
        courseCards.forEach((card) => card.setAttribute('aria-expanded', card.dataset.courseId === courseId ? 'true' : 'false'));
        document.body.classList.add('overflow-hidden');
        requestAnimationFrame(() => modal.querySelector('[data-modal-initial-focus]')?.focus());
    }

    function closeModal() {
        if (!modal || modal.classList.contains('hidden')) return;

        state.modalCourseId = '';
        modal.classList.add('hidden');
        modal.classList.remove('flex');
        modal.setAttribute('aria-hidden', 'true');
        courseCards.forEach((card) => card.setAttribute('aria-expanded', 'false'));
        document.body.classList.remove('overflow-hidden');
        setHoveredCourse('');
        modalTrigger?.focus();
        modalTrigger = null;
    }

    function containModalFocus(event) {
        if (event.key !== 'Tab' || !modal || modal.classList.contains('hidden')) return;

        const focusable = Array.from(modal.querySelectorAll('a[href], button:not([disabled]), [tabindex]:not([tabindex="-1"])'))
            .filter((element) => !element.hidden && element.getAttribute('aria-hidden') !== 'true');
        if (focusable.length === 0) {
            event.preventDefault();
            return;
        }

        const target = modalFocusTarget(focusable, document.activeElement, event.shiftKey);
        if (target) {
            event.preventDefault();
            target.focus();
        }
    }

    function viewStateKey() {
        return `sp-zoom-${VIEW_STATE_VERSION}-${facultySlug}-${state.activeDepartmentId}`;
    }

    function saveViewState() {
        try {
            localStorage.setItem(viewStateKey(), JSON.stringify({ scale: state.scale, panX: state.panX, panY: state.panY }));
        } catch (error) {
            // Ignore unavailable storage.
        }
    }

    function restoreViewState() {
        try {
            const saved = localStorage.getItem(viewStateKey());
            if (!saved) return false;
            const data = JSON.parse(saved);
            if (![data.scale, data.panX, data.panY].every(Number.isFinite)) return false;
            state.scale = Math.max(state.minScale, Math.min(state.maxScale, data.scale));
            state.panX = data.panX;
            state.panY = data.panY;

            return true;
        } catch (error) {
            return false;
        }
    }

    function boardSize() {
        return { width: board?.offsetWidth || 1, height: board?.offsetHeight || 1 };
    }

    function applyTransform() {
        if (!world) return;
        world.style.transform = `translate3d(${state.panX}px, ${state.panY}px, 0) scale(${state.scale})`;
    }

    function clampView() {
        if (!viewport || !board) return;

        const size = boardSize();
        const clamped = clampPan(state, viewport.clientWidth, viewport.clientHeight, size.width, size.height);
        state.panX = clamped.panX;
        state.panY = clamped.panY;
    }

    function commitView() {
        clampView();
        applyTransform();
        saveViewState();
    }

    function fitToScreen() {
        if (!viewport) return;
        const size = boardSize();
        const vw = viewport.clientWidth;
        const vh = viewport.clientHeight;
        state.scale = Math.max(state.minScale, Math.min(vw / size.width, vh / size.height, 1));
        state.panX = (vw - size.width * state.scale) / 2;
        state.panY = (vh - size.height * state.scale) / 2;
        commitView();
    }

    // On a phone a full fit would shrink the cards to an unreadable size, so the first view
    // starts at a readable scale at the first term. The fit button still shows everything.
    function initialView() {
        if (mobileQuery?.matches) {
            state.scale = 0.75;
            state.panX = 8;
            state.panY = 8;
            commitView();

            return;
        }

        fitToScreen();
    }

    function setScaleAt(newScale, centerX, centerY) {
        const scale = Math.max(state.minScale, Math.min(state.maxScale, newScale));
        if (scale === state.scale) return;

        const ratio = scale / state.scale;
        state.panX = centerX - (centerX - state.panX) * ratio;
        state.panY = centerY - (centerY - state.panY) * ratio;
        state.scale = scale;
        commitView();
    }

    function zoomToPoint(delta, centerX, centerY) {
        setScaleAt(state.scale * (1 - delta), centerX, centerY);
    }

    function pointerDistance() {
        const [a, b] = Array.from(pointers.values());

        return a && b ? Math.hypot(a.x - b.x, a.y - b.y) : 0;
    }

    function pointerMidpoint() {
        const [a, b] = Array.from(pointers.values());

        return a && b ? { x: (a.x + b.x) / 2, y: (a.y + b.y) / 2 } : { x: 0, y: 0 };
    }

    function onPointerDown(event) {
        if (event.pointerType === 'mouse' && event.button !== 0) return;
        if (event.target.closest('[data-study-plan-controls]')) return;

        pointers.set(event.pointerId, { x: event.clientX, y: event.clientY });

        if (pointers.size === 1) {
            state.lastMouseX = event.clientX;
            state.lastMouseY = event.clientY;
            dragMoved = false;
        } else if (pointers.size === 2) {
            pinch = { startDistance: pointerDistance(), startScale: state.scale };
            dragMoved = true;
        }
    }

    function onPointerMove(event) {
        const pointer = pointers.get(event.pointerId);
        if (!pointer) return;

        pointer.x = event.clientX;
        pointer.y = event.clientY;

        if (pointers.size >= 2 && pinch) {
            const rect = viewport.getBoundingClientRect();
            const midpoint = pointerMidpoint();
            setScaleAt(
                pinchScale(pinch.startScale, pinch.startDistance, pointerDistance(), state.minScale, state.maxScale),
                midpoint.x - rect.left,
                midpoint.y - rect.top,
            );

            return;
        }

        const dx = event.clientX - state.lastMouseX;
        const dy = event.clientY - state.lastMouseY;

        if (!dragMoved) {
            if (Math.hypot(dx, dy) < DRAG_THRESHOLD) return;

            // Capture only once the gesture is a real drag, so a plain tap still reaches the card.
            dragMoved = true;
            try {
                viewport.setPointerCapture(event.pointerId);
            } catch (error) {
                // Pointer capture is optional.
            }
            world?.classList.add('cursor-grabbing');
            world?.classList.remove('cursor-grab');
        }

        state.panX += dx;
        state.panY += dy;
        state.lastMouseX = event.clientX;
        state.lastMouseY = event.clientY;
        clampView();
        applyTransform();
    }

    function onPointerEnd(event) {
        if (!pointers.delete(event.pointerId)) return;

        if (pointers.size < 2) pinch = null;

        const remaining = Array.from(pointers.values())[0];
        if (remaining) {
            state.lastMouseX = remaining.x;
            state.lastMouseY = remaining.y;
        }

        if (pointers.size === 0) {
            world?.classList.add('cursor-grab');
            world?.classList.remove('cursor-grabbing');

            if (dragMoved) {
                suppressClick = true;
                setTimeout(() => { suppressClick = false; }, 0);
                saveViewState();
            }
        }
    }

    function downloadHoursSummary() {
        const department = activeDepartment();
        const lines = [];

        lines.push('='.repeat(50));
        lines.push(isAr ? 'ملخص الساعات والمتطلبات' : 'Hours & Requirements Summary');
        lines.push('='.repeat(50));
        lines.push('');
        lines.push(`${isAr ? 'الكلية' : 'Faculty'}: ${text(plan, 'faculty')}`);
        lines.push(`${isAr ? 'القسم' : 'Department'}: ${text(department, 'name')}`);
        lines.push('');

        (department?.electivePools || []).forEach((pool) => {
            lines.push(`${pool.id}: ${pool.requiredHours} ${isAr ? 'ساعة' : 'hours'}`);
            lines.push(`  ${text(pool, 'description')}`);
        });

        lines.push('');
        (department?.promotionRequirements || []).forEach((req) => {
            lines.push(`${isAr ? 'السنة' : 'Year'} ${req.fromYear} -> ${req.toYear}: ${req.requiredCredits} ${isAr ? 'ساعة معتمدة' : 'credits'}`);
        });

        lines.push('');
        lines.push(`${isAr ? 'إجمالي الساعات المعتمدة' : 'Total Credits'}: ${department?.totalCredits || 0}`);
        lines.push('='.repeat(50));

        const blob = new Blob([lines.join('\n')], { type: 'text/plain;charset=utf-8' });
        const url = URL.createObjectURL(blob);
        const link = document.createElement('a');
        link.href = url;
        link.download = `${text(department, 'name').replace(/\s+/g, '_')}_${isAr ? 'الساعات' : 'Hours'}.txt`;
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
        URL.revokeObjectURL(url);
    }

    function effectiveView() {
        return root.dataset.view || (mobileQuery?.matches ? 'list' : 'graph');
    }

    function syncViewToggles() {
        const view = effectiveView();
        viewToggles.forEach((button) => {
            button.setAttribute('aria-pressed', button.dataset.studyPlanViewToggle === view ? 'true' : 'false');
        });
    }

    // The graph can only be measured while it is displayed, so layout work is deferred until
    // it is visible (it is hidden on phones until the visitor switches to it).
    function ensureGraph() {
        if (!viewport || viewport.clientWidth === 0) return;

        renderDependencyPaths();

        if (!graphReady) {
            graphReady = true;
            if (restoreViewState()) {
                commitView();
            } else {
                initialView();
            }

            return;
        }

        commitView();
    }

    function setView(view) {
        root.dataset.view = view;
        syncViewToggles();
        if (view === 'graph') requestAnimationFrame(ensureGraph);
    }

    function bindEvents() {
        root.querySelectorAll('[data-department-tab]').forEach((button) => {
            button.addEventListener('click', () => {
                const params = new URLSearchParams(window.location.search);
                params.set('department', button.dataset.departmentTab || '');
                params.delete('course');
                window.location.assign(`${window.location.pathname}?${params.toString()}`);
            });
        });

        courseCards.forEach((card) => {
            bindCourseCardInteractions(card, {
                highlight: setHoveredCourse,
                clear: () => {
                    if (!state.modalCourseId) setHoveredCourse('');
                },
                open: openModal,
            });
        });

        listCards.forEach((card) => {
            card.addEventListener('click', () => openModal(card.dataset.courseId || '', card));
        });

        legendButtons.forEach((button) => {
            button.addEventListener('click', () => applyTypeFilter(button.dataset.legendType || ''));
        });

        viewToggles.forEach((button) => {
            button.addEventListener('click', () => setView(button.dataset.studyPlanViewToggle || 'graph'));
        });
        mobileQuery?.addEventListener?.('change', () => {
            syncViewToggles();
            requestAnimationFrame(ensureGraph);
        });

        root.querySelector('[data-study-plan-download]')?.addEventListener('click', downloadHoursSummary);
        root.querySelector('[data-study-plan-zoom-in]')?.addEventListener('click', () => {
            zoomToPoint(-0.2, viewport.clientWidth / 2, viewport.clientHeight / 2);
        });
        root.querySelector('[data-study-plan-zoom-out]')?.addEventListener('click', () => {
            zoomToPoint(0.2, viewport.clientWidth / 2, viewport.clientHeight / 2);
        });
        root.querySelector('[data-study-plan-fit]')?.addEventListener('click', fitToScreen);

        viewport?.addEventListener('pointerdown', onPointerDown);
        viewport?.addEventListener('pointermove', onPointerMove);
        viewport?.addEventListener('pointerup', onPointerEnd);
        viewport?.addEventListener('pointercancel', onPointerEnd);
        // A drag that ends on a card must not also open it.
        viewport?.addEventListener('click', (event) => {
            if (!suppressClick) return;
            event.preventDefault();
            event.stopPropagation();
        }, true);
        // Ctrl/Cmd + wheel (and trackpad pinch) zooms; a plain wheel keeps scrolling the page.
        viewport?.addEventListener('wheel', (event) => {
            if (!event.ctrlKey && !event.metaKey) return;

            event.preventDefault();
            const rect = viewport.getBoundingClientRect();
            zoomToPoint(Math.max(-0.5, Math.min(0.5, event.deltaY * 0.002)), event.clientX - rect.left, event.clientY - rect.top);
        }, { passive: false });
        viewport?.addEventListener('keydown', (event) => {
            if (event.target.closest('button, a')) return;

            const action = studyPlanKeyboardAction(event.key);
            if (!action) return;

            event.preventDefault();
            state.panX += action.panX;
            state.panY += action.panY;
            if (action.zoom !== 0) {
                zoomToPoint(action.zoom, viewport.clientWidth / 2, viewport.clientHeight / 2);
                return;
            }
            commitView();
        });

        modal?.addEventListener('click', (event) => {
            if (event.target === modal || event.target.closest('[data-modal-close]')) {
                closeModal();
            }
        });
        document.addEventListener('keydown', (event) => {
            if (event.key === 'Escape') {
                closeModal();
                return;
            }

            containModalFocus(event);
        });

        let resizeTimer = 0;
        window.addEventListener('resize', () => {
            clearTimeout(resizeTimer);
            resizeTimer = setTimeout(ensureGraph, 120);
        });
    }

    return {
        init() {
            syncViewToggles();
            bindEvents();
            requestAnimationFrame(ensureGraph);
        },
    };
}

export function initStudyPlanPages() {
    document.querySelectorAll('[data-study-plan]').forEach((root) => {
        createStudyPlanPage(root).init();
    });
}
