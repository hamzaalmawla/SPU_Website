export function clampPercentage(value) {
    return Math.max(0, Math.min(100, Number(value) || 0));
}

export function pointerToFocalPoint(clientX, clientY, bounds) {
    return {
        x: clampPercentage(((clientX - bounds.left) / bounds.width) * 100),
        y: clampPercentage(((clientY - bounds.top) / bounds.height) * 100),
    };
}

export function createFocalPointPicker(config) {
    return {
        x: config.x,
        y: config.y,
        fit: config.fit,
        dragging: false,
        announcement: '',

        init() {
            this.x = clampPercentage(this.x ?? 50);
            this.y = clampPercentage(this.y ?? 50);
            this.fit = this.fit === 'contain' ? 'contain' : 'cover';
        },

        get markerStyle() {
            return `left:${this.x}%;top:${this.y}%;`;
        },

        get previewStyle() {
            return `object-fit:${this.fit};object-position:${this.x}% ${this.y}%;`;
        },

        updateFromPointer(event) {
            const point = pointerToFocalPoint(event.clientX, event.clientY, event.currentTarget.getBoundingClientRect());
            this.x = Math.round(point.x * 100) / 100;
            this.y = Math.round(point.y * 100) / 100;
        },

        startDrag(event) {
            this.dragging = true;
            event.currentTarget.setPointerCapture?.(event.pointerId);
            this.updateFromPointer(event);
        },

        drag(event) {
            if (this.dragging) {
                this.updateFromPointer(event);
            }
        },

        endDrag(event) {
            if (!this.dragging) return;
            this.dragging = false;
            event.currentTarget.releasePointerCapture?.(event.pointerId);
            this.announce();
        },

        handleKeydown(event) {
            const step = event.shiftKey ? 5 : 1;
            const deltas = {
                ArrowLeft: [-step, 0],
                ArrowRight: [step, 0],
                ArrowUp: [0, -step],
                ArrowDown: [0, step],
            };
            if (!deltas[event.key]) return;
            event.preventDefault();
            this.x = clampPercentage(Number(this.x) + deltas[event.key][0]);
            this.y = clampPercentage(Number(this.y) + deltas[event.key][1]);
            this.announce();
        },

        center() {
            this.x = 50;
            this.y = 50;
            this.announce();
        },

        announce() {
            this.announcement = `Focus ${Math.round(this.x)} percent across and ${Math.round(this.y)} percent down`;
        },
    };
}
