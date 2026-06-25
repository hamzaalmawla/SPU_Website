const TOTAL_TERMS = 10;

const planLayout = {
    columnWidth: 150,
    columnGap: 64,
    rowHeight: 80,
    cardHeight: 100,
    cardGap: 18,
    headerHeight: 36,
    headerGap: 24,
    bottomPadding: 28,
    sidePadding: 28,
};

planLayout.boardWidth = (TOTAL_TERMS * planLayout.columnWidth) + ((TOTAL_TERMS - 1) * planLayout.columnGap) + (2 * planLayout.sidePadding);

const TYPE_STYLES = {
    university: { shortLabelEn: 'UNIV', shortLabelAr: 'جامعي' },
    faculty: { shortLabelEn: 'FAC', shortLabelAr: 'كلية' },
    specialization: { shortLabelEn: 'SPEC', shortLabelAr: 'تخصص' },
    elective: { shortLabelEn: 'ELEC', shortLabelAr: 'اختياري' },
};

function flattenCourses(department) {
    return (department?.terms || []).flatMap((term) => term.courses || []);
}

function transitiveReduction(courses) {
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
    const labels = payload.labels || {};
    const legend = payload.legend || [];
    const plan = payload.plan || {};
    const departments = plan.departments || [];
    const urlParams = new URLSearchParams(window.location.search);

    const state = {
        activeDepartmentId: urlParams.get('department') || departments[0]?.id || '',
        hoveredCourseId: '',
        hoveredPathIds: [],
        modalCourseId: '',
        scale: 1,
        panX: 0,
        panY: 0,
        isDragging: false,
        lastMouseX: 0,
        lastMouseY: 0,
        minScale: 0.2,
        maxScale: 3,
    };

    const viewport = root.querySelector('[data-study-plan-viewport]');
    const world = root.querySelector('[data-study-plan-world]');
    const board = root.querySelector('[data-study-plan-board]');
    const svg = root.querySelector('[data-study-plan-svg]');
    const courseCards = Array.from(root.querySelectorAll('[data-course-card]'));
    let basePaths = [];
    let activePaths = [];
    let glowPaths = [];
    const modal = root.querySelector('[data-course-modal]');

    function label(key) {
        const suffix = isAr ? 'Ar' : 'En';
        return labels?.[`${key}${suffix}`] || labels?.[`${key}En`] || '';
    }

    function text(item, key) {
        const suffix = isAr ? 'Ar' : 'En';
        return item?.[`${key}${suffix}`] || item?.[`${key}En`] || item?.[`${key}Ar`] || item?.[key] || '';
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

        return `/${locale}/facilities/${facultySlug}/study-plan/course?${params.toString()}`;
    }

    function typeLabel(course) {
        if (!course) return '';
        if (!course.required) return label('elective');
        const item = legend.find((legendItem) => legendItem.id === course.type);

        return text(item, 'label') || label('required');
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

        courseCards.forEach((card) => {
            const courseId = card.dataset.courseId || '';
            const inPath = state.hoveredPathIds.includes(courseId);
            const isHovered = state.hoveredCourseId === courseId;

            card.classList.toggle('opacity-25', hasHover && !inPath);
            card.classList.toggle('scale-[0.97]', hasHover && !inPath);
            card.classList.toggle('blur-[0.4px]', hasHover && !inPath);
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

    function cardGeometryMap() {
        const map = new Map();

        courseCards.forEach((card) => {
            if (!board) return;

            const offset = elementOffsetWithin(card, board);
            const left = offset.left;
            const top = offset.top;
            map.set(card.dataset.courseId || '', {
                left,
                right: left + card.offsetWidth,
                centerY: top + (card.offsetHeight / 2),
            });
        });

        return map;
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

    function dependencyPaths() {
        const map = cardGeometryMap();
        const reducedEdges = transitiveReduction(courses());
        const incomingCount = new Map();

        reducedEdges.forEach((edge) => {
            incomingCount.set(edge.target, (incomingCount.get(edge.target) || 0) + 1);
        });

        const incomingIndex = new Map();

        return reducedEdges.map((edge) => {
            const source = map.get(edge.source);
            const target = map.get(edge.target);
            if (!source || !target) return null;

            const index = incomingIndex.get(edge.target) || 0;
            incomingIndex.set(edge.target, index + 1);

            const count = incomingCount.get(edge.target) || 1;
            const yOffset = (index * 14) - (((count - 1) * 14) / 2);
            const startX = source.right;
            const startY = source.centerY;
            const endX = target.left;
            const endY = target.centerY + yOffset;
            const horizontalGap = endX - startX;
            const curve = Math.min(Math.max(horizontalGap * 0.22, 14), horizontalGap * 0.32);

            return {
                sourceId: edge.source,
                targetId: edge.target,
                path: `M ${startX} ${startY} C ${startX + curve} ${startY} ${endX - curve} ${endY} ${endX} ${endY}`,
            };
        }).filter(Boolean);
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

        svg.querySelectorAll('[data-generated-path]').forEach((path) => path.remove());

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
                style: 'transition: all 0.3s;',
            }));

            svg.appendChild(createSvgPath({
                ...common,
                'data-path-glow': '1',
                stroke: plan.accent || '#202759',
                'stroke-width': '6',
                'stroke-opacity': '0.25',
                class: 'sp-path-glow hidden',
            }));

            svg.appendChild(createSvgPath({
                ...common,
                'data-path-active': '1',
                stroke: plan.accent || '#202759',
                'stroke-width': '2.5',
                'marker-end': 'url(#sp-arrow-path)',
                class: 'sp-path-animated hidden',
                style: 'transition: all 0.3s;',
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

    function openModal(courseId) {
        const course = courseById(courseId);
        if (!course || !modal) return;

        state.modalCourseId = courseId;
        setHoveredCourse(courseId);

        modal.querySelector('[data-modal-code]').textContent = course.code || '';
        modal.querySelector('[data-modal-credits]').textContent = `${course.credits || 0}cr`;
        modal.querySelector('[data-modal-type]').textContent = typeShortLabel(course);
        modal.querySelector('[data-modal-type]').className = `text-[10px] font-bold uppercase tracking-wider ${typeKey(course) === 'elective' ? 'text-slate-400' : 'text-spu-blue/60'}`;
        modal.querySelector('[data-modal-title]').textContent = text(course, 'title');
        modal.querySelector('[data-modal-details]').setAttribute('href', courseHref(course.id));

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
        document.body.classList.add('overflow-hidden');
    }

    function closeModal() {
        state.modalCourseId = '';
        modal?.classList.add('hidden');
        modal?.classList.remove('flex');
        document.body.classList.remove('overflow-hidden');
        setHoveredCourse('');
    }

    function viewStateKey() {
        return `sp-zoom-${facultySlug}-${state.activeDepartmentId}`;
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
            if (!saved) return;
            const data = JSON.parse(saved);
            state.scale = Math.max(state.minScale, Math.min(state.maxScale, data.scale ?? 1));
            state.panX = data.panX ?? 0;
            state.panY = data.panY ?? 0;
        } catch (error) {
            // Ignore invalid storage.
        }
    }

    function boardHeightValue() {
        const values = (activeDepartment()?.terms || []).map((term) => {
            const count = (term.courses || []).length;
            return planLayout.headerHeight + planLayout.headerGap + (count * planLayout.cardHeight) + (Math.max(0, count - 1) * planLayout.cardGap) + planLayout.bottomPadding;
        });

        return Math.max(...values, planLayout.headerHeight + planLayout.bottomPadding);
    }

    function transformStyle() {
        return `translate3d(${state.panX}px, ${state.panY}px, 0) scale(${state.scale})`;
    }

    function applyTransform() {
        if (!world) return;
        world.style.transform = transformStyle();
    }

    function fitToScreen() {
        if (!viewport) return;
        const vw = viewport.clientWidth;
        const vh = viewport.clientHeight;
        const fitScale = Math.min(vw / planLayout.boardWidth, vh / boardHeightValue(), 1);
        state.scale = Math.max(state.minScale, fitScale);
        state.panX = (vw - planLayout.boardWidth * state.scale) / 2;
        state.panY = (vh - boardHeightValue() * state.scale) / 2;
        applyTransform();
        saveViewState();
    }

    function zoomToPoint(delta, centerX, centerY) {
        const newScale = Math.max(state.minScale, Math.min(state.maxScale, state.scale * (1 - delta)));
        if (newScale === state.scale) return;
        const ratio = newScale / state.scale;
        state.panX = centerX - (centerX - state.panX) * ratio;
        state.panY = centerY - (centerY - state.panY) * ratio;
        state.scale = newScale;
        applyTransform();
        saveViewState();
    }

    function startPan(event) {
        const touch = event.touches?.[0];
        const clientX = touch?.clientX ?? event.clientX;
        const clientY = touch?.clientY ?? event.clientY;

        if (event.button !== undefined && event.button !== 0) return;
        if (event.target.closest('button, a')) return;

        event.preventDefault();
        state.isDragging = true;
        state.lastMouseX = clientX;
        state.lastMouseY = clientY;
        world?.classList.add('cursor-grabbing');
        world?.classList.remove('cursor-grab');
    }

    function panBy(event) {
        if (!state.isDragging) return;
        if (event.touches) event.preventDefault();

        const touch = event.touches?.[0];
        const clientX = touch?.clientX ?? event.clientX;
        const clientY = touch?.clientY ?? event.clientY;

        state.panX += clientX - state.lastMouseX;
        state.panY += clientY - state.lastMouseY;
        state.lastMouseX = clientX;
        state.lastMouseY = clientY;
        applyTransform();
    }

    function endPan() {
        if (!state.isDragging) return;
        state.isDragging = false;
        world?.classList.add('cursor-grab');
        world?.classList.remove('cursor-grabbing');
        saveViewState();
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
            card.addEventListener('mouseenter', () => setHoveredCourse(card.dataset.courseId || ''));
            card.addEventListener('mouseleave', () => {
                if (!state.modalCourseId) setHoveredCourse('');
            });
            card.addEventListener('click', () => openModal(card.dataset.courseId || ''));
        });

        root.querySelector('[data-study-plan-download]')?.addEventListener('click', downloadHoursSummary);
        root.querySelector('[data-study-plan-print]')?.addEventListener('click', () => window.print());
        root.querySelector('[data-study-plan-zoom-in]')?.addEventListener('click', () => {
            const rect = viewport.getBoundingClientRect();
            zoomToPoint(-0.2, rect.width / 2, rect.height / 2);
        });
        root.querySelector('[data-study-plan-zoom-out]')?.addEventListener('click', () => {
            const rect = viewport.getBoundingClientRect();
            zoomToPoint(0.2, rect.width / 2, rect.height / 2);
        });
        root.querySelector('[data-study-plan-fit]')?.addEventListener('click', fitToScreen);

        viewport?.addEventListener('mousedown', startPan);
        viewport?.addEventListener('mousemove', panBy);
        viewport?.addEventListener('mouseup', endPan);
        viewport?.addEventListener('mouseleave', endPan);
        viewport?.addEventListener('touchstart', startPan, { passive: false });
        viewport?.addEventListener('touchmove', panBy, { passive: false });
        viewport?.addEventListener('touchend', endPan);

        modal?.addEventListener('click', (event) => {
            if (event.target === modal || event.target.closest('[data-modal-close]')) {
                closeModal();
            }
        });
        document.addEventListener('keydown', (event) => {
            if (event.key === 'Escape') closeModal();
        });
    }

    function initZoom() {
        restoreViewState();
        if (state.scale === 1 && state.panX === 0 && state.panY === 0) {
            fitToScreen();
            return;
        }
        applyTransform();
    }

    return {
        init() {
            bindEvents();
            requestAnimationFrame(() => {
                renderDependencyPaths();
                initZoom();
            });
            window.addEventListener('resize', () => {
                renderDependencyPaths();
                applyTransform();
            });
        },
    };
}

export function initStudyPlanPages() {
    document.querySelectorAll('[data-study-plan]').forEach((root) => {
        createStudyPlanPage(root).init();
    });
}
