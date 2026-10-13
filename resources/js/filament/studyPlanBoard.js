import { planEdgePaths } from '../alpine/studyPlan.js';

const SVG_NS = 'http://www.w3.org/2000/svg';

/**
 * Alpine component for the visual study plan editor in the admin panel. The board itself is
 * rendered by Livewire; this only draws the prerequisite lines over it (re-drawn whenever
 * Livewire changes the DOM) and turns card drops into a Livewire call.
 */
export function createStudyPlanBoard() {
    return {
        observer: null,
        frame: 0,
        dragId: '',

        init() {
            this.draw();
            this.observer = new MutationObserver(() => this.schedule());
            this.observer.observe(this.$refs.inner, {
                childList: true,
                subtree: true,
                attributes: true,
                attributeFilter: ['data-prereqs', 'class'],
            });
            this.onResize = () => this.schedule();
            window.addEventListener('resize', this.onResize);
        },

        destroy() {
            this.observer?.disconnect();
            window.removeEventListener('resize', this.onResize);
            cancelAnimationFrame(this.frame);
        },

        schedule() {
            cancelAnimationFrame(this.frame);
            this.frame = requestAnimationFrame(() => this.draw());
        },

        draw() {
            const inner = this.$refs.inner;
            const svg = this.$refs.lines;
            if (!inner || !svg) return;

            const origin = inner.getBoundingClientRect();
            const cards = Array.from(inner.querySelectorAll('[data-board-card]'));
            const geometry = new Map();
            const courses = [];

            cards.forEach((card) => {
                const rect = card.getBoundingClientRect();
                const id = card.dataset.courseId;
                let prerequisites = [];

                try {
                    prerequisites = JSON.parse(card.dataset.prereqs || '[]');
                } catch (error) {
                    prerequisites = [];
                }

                geometry.set(id, {
                    left: rect.left - origin.left,
                    right: rect.right - origin.left,
                    centerY: rect.top - origin.top + (rect.height / 2),
                    height: rect.height,
                });
                courses.push({ id, prerequisites });
            });

            svg.setAttribute('width', String(inner.scrollWidth));
            svg.setAttribute('height', String(inner.scrollHeight));
            svg.replaceChildren();

            planEdgePaths(geometry, courses).forEach((edge) => {
                const path = document.createElementNS(SVG_NS, 'path');
                path.setAttribute('d', edge.path);
                path.setAttribute('fill', 'none');
                path.setAttribute('stroke', 'currentColor');
                path.setAttribute('stroke-width', '1.4');
                path.setAttribute('stroke-opacity', '0.45');
                path.setAttribute('stroke-linecap', 'round');
                path.dataset.sourceId = edge.sourceId;
                path.dataset.targetId = edge.targetId;
                svg.appendChild(path);
            });
        },

        highlight(courseId) {
            this.$refs.lines?.querySelectorAll('path').forEach((path) => {
                const related = courseId !== '' && (path.dataset.sourceId === courseId || path.dataset.targetId === courseId);
                path.setAttribute('stroke-opacity', courseId === '' ? '0.45' : (related ? '1' : '0.12'));
                path.setAttribute('stroke-width', related ? '2.4' : '1.4');
            });
        },

        dragStart(event, courseId) {
            this.dragId = courseId;
            event.dataTransfer.effectAllowed = 'move';
            event.dataTransfer.setData('text/plain', courseId);
        },

        dragOver(event) {
            event.preventDefault();
            event.currentTarget.classList.add('is-drop-target');
        },

        dragLeave(event) {
            event.currentTarget.classList.remove('is-drop-target');
        },

        drop(event, termId) {
            event.preventDefault();
            event.currentTarget.classList.remove('is-drop-target');
            const courseId = this.dragId || event.dataTransfer.getData('text/plain');
            this.dragId = '';

            if (courseId) {
                this.$wire.moveBoardCourse(courseId, termId);
            }
        },
    };
}
