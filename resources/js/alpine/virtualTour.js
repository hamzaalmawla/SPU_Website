const clamp = (value, min, max) => Math.min(max, Math.max(min, value));

export function createVirtualTour() {
    return {
        scenes: [],
        activeIndex: 0,
        zoom: 1,
        panX: 0,
        panY: 0,
        dragging: false,
        pointerId: null,
        pointerX: 0,
        pointerY: 0,
        autoplay: false,
        autoplayTimer: null,
        reducedMotion: false,
        fallbackFullscreen: false,
        nativeFullscreen: false,
        announcement: '',
        playLabel: '',
        pauseLabel: '',
        enterFullscreenLabel: '',
        exitFullscreenLabel: '',
        interval: 6000,

        init() {
            try {
                this.scenes = JSON.parse(this.$refs.sceneData?.textContent || '[]');
            } catch {
                this.scenes = [];
            }
            this.scenes = this.scenes.map((scene) => ({
                ...scene,
                hotspots: Array.isArray(scene.hotspots) ? scene.hotspots.map((hotspot) => ({
                    ...hotspot,
                    style: `left:${clamp(Number(hotspot.x) || 50, 0, 100)}%;top:${clamp(Number(hotspot.y) || 50, 0, 100)}%`,
                })) : [],
            }));
            this.interval = clamp(Number(this.$el.dataset.autoplayInterval) || 6000, 3000, 20000);
            this.playLabel = this.$el.dataset.playLabel || 'Play';
            this.pauseLabel = this.$el.dataset.pauseLabel || 'Pause';
            this.enterFullscreenLabel = this.$el.dataset.fullscreenLabel || 'Fullscreen';
            this.exitFullscreenLabel = this.$el.dataset.exitFullscreenLabel || 'Exit fullscreen';
            this.reducedMotion = window.matchMedia?.('(prefers-reduced-motion: reduce)').matches === true;
            document.addEventListener('fullscreenchange', () => {
                this.nativeFullscreen = Boolean(document.fullscreenElement);
                if (!document.fullscreenElement) this.fallbackFullscreen = false;
            });
        },

        destroy() {
            this.pauseAutoplay();
        },

        get activeScene() { return this.scenes[this.activeIndex] || {}; },
        get activeImage() { return this.activeScene.image || ''; },
        get activeImageAlt() { return this.activeScene.imageAlt || ''; },
        get activeTitle() { return this.activeScene.title || ''; },
        get activeSummary() { return this.activeScene.summary || ''; },
        get activeHotspots() { return this.activeScene.hotspots || []; },
        get autoplayLabel() { return this.autoplay ? this.pauseLabel : this.playLabel; },
        get fullscreenLabel() { return this.nativeFullscreen || this.fallbackFullscreen ? this.exitFullscreenLabel : this.enterFullscreenLabel; },
        get zoomLabel() { return `${Math.round(this.zoom * 100)}%`; },
        get fullscreenClass() { return this.fallbackFullscreen ? 'fixed inset-0 z-[200] rounded-none' : ''; },
        get imageTransform() { return `transform:translate3d(${this.panX}%,${this.panY}%,0) scale(${this.zoom});transition:${this.dragging || this.reducedMotion ? 'none' : 'transform 180ms ease'}`; },

        selectScene(event) { this.goTo(Number(event.currentTarget?.dataset?.sceneIndex)); },
        goTo(index) {
            if (!this.scenes.length || !Number.isInteger(index)) return;
            this.activeIndex = (index + this.scenes.length) % this.scenes.length;
            this.resetView();
            this.announcement = this.activeTitle;
            if (this.autoplay) this.startTimer();
        },
        previous() { this.goTo(this.activeIndex - 1); },
        next() { this.goTo(this.activeIndex + 1); },
        zoomIn() { this.setZoom(this.zoom + 0.25); },
        zoomOut() { this.setZoom(this.zoom - 0.25); },
        setZoom(value) {
            this.zoom = clamp(value, 1, 3);
            this.boundPan();
        },
        resetView() { this.zoom = 1; this.panX = 0; this.panY = 0; },
        boundPan() {
            const limit = (this.zoom - 1) * 25;
            this.panX = clamp(this.panX, -limit, limit);
            this.panY = clamp(this.panY, -limit, limit);
        },
        startPan(event) {
            if (event.pointerType === 'mouse' && event.button !== 0) return;
            this.dragging = true;
            this.pointerId = event.pointerId;
            this.pointerX = event.clientX;
            this.pointerY = event.clientY;
            event.currentTarget?.setPointerCapture?.(event.pointerId);
            this.pauseAutoplay();
        },
        movePan(event) {
            if (!this.dragging || event.pointerId !== this.pointerId || this.zoom <= 1) return;
            const width = event.currentTarget?.clientWidth || 1;
            const height = event.currentTarget?.clientHeight || 1;
            this.panX += ((event.clientX - this.pointerX) / width) * 100;
            this.panY += ((event.clientY - this.pointerY) / height) * 100;
            this.pointerX = event.clientX;
            this.pointerY = event.clientY;
            this.boundPan();
        },
        endPan(event) {
            if (event.pointerId !== this.pointerId) return;
            this.dragging = false;
            this.pointerId = null;
        },
        handleKey(event) {
            const key = event.key;
            if (['ArrowLeft', 'ArrowRight', 'ArrowUp', 'ArrowDown', '+', '=', '-', '0'].includes(key)) event.preventDefault();
            if (key === '+' || key === '=') return this.zoomIn();
            if (key === '-') return this.zoomOut();
            if (key === '0') return this.resetView();
            if (key === 'Escape' && this.fallbackFullscreen) { this.fallbackFullscreen = false; return; }
            const step = 5;
            if (key === 'ArrowLeft') this.panX -= step;
            if (key === 'ArrowRight') this.panX += step;
            if (key === 'ArrowUp') this.panY -= step;
            if (key === 'ArrowDown') this.panY += step;
            this.boundPan();
        },
        activateHotspot(event) {
            const targetId = event.currentTarget?.dataset?.targetScene;
            if (!targetId) return;
            const index = this.scenes.findIndex((scene) => scene.id === targetId);
            if (index >= 0) this.goTo(index);
        },
        toggleAutoplay() { this.autoplay ? this.pauseAutoplay() : this.startAutoplay(); },
        startAutoplay() {
            if (this.reducedMotion || this.scenes.length < 2) return;
            this.autoplay = true;
            this.startTimer();
        },
        pauseAutoplay() {
            this.autoplay = false;
            if (this.autoplayTimer) window.clearInterval(this.autoplayTimer);
            this.autoplayTimer = null;
        },
        startTimer() {
            if (this.autoplayTimer) window.clearInterval(this.autoplayTimer);
            this.autoplayTimer = window.setInterval(() => this.next(), this.interval);
        },
        async toggleFullscreen() {
            const viewer = this.$refs.viewer;
            if (document.fullscreenElement) return document.exitFullscreen();
            if (this.fallbackFullscreen) { this.fallbackFullscreen = false; return; }
            try {
                if (viewer?.requestFullscreen) await viewer.requestFullscreen();
                else this.fallbackFullscreen = true;
            } catch {
                this.fallbackFullscreen = true;
            }
        },
    };
}
