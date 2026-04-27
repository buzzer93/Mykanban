import { Controller } from '@hotwired/stimulus';

const SWIPE_THRESHOLD_PX = 45;
const SWIPE_DIRECTION_RATIO = 1.15;

export default class extends Controller {
    static targets = ['column', 'track'];

    connect() {
        this.activeIndex = 0;
        this.dragging = false;
        this.touchSession = null;

        this.boundColumnAdvance = (event) => this.onColumnAdvance(event);
        this.boundResize = () => this.applyLayout();
        this.boundTouchStart = (event) => this.onTouchStart(event);
        this.boundTouchMove = (event) => this.onTouchMove(event);
        this.boundTouchEnd = (event) => this.onTouchEnd(event);
        this.boundDragStart = () => {
            this.dragging = true;
            this.touchSession = null;
        };
        this.boundDragEnd = () => {
            this.dragging = false;
            this.touchSession = null;
        };

        this.element.addEventListener('board:column-advance', this.boundColumnAdvance);
        this.element.addEventListener('board:drag-start', this.boundDragStart);
        this.element.addEventListener('board:drag-end', this.boundDragEnd);
        this.element.addEventListener('touchstart', this.boundTouchStart, { passive: true });
        this.element.addEventListener('touchmove', this.boundTouchMove, { passive: false });
        this.element.addEventListener('touchend', this.boundTouchEnd, { passive: true });
        window.addEventListener('resize', this.boundResize);

        this.applyLayout({ animate: false });
    }

    disconnect() {
        this.element.removeEventListener('board:column-advance', this.boundColumnAdvance);
        this.element.removeEventListener('board:drag-start', this.boundDragStart);
        this.element.removeEventListener('board:drag-end', this.boundDragEnd);
        this.element.removeEventListener('touchstart', this.boundTouchStart);
        this.element.removeEventListener('touchmove', this.boundTouchMove);
        this.element.removeEventListener('touchend', this.boundTouchEnd);
        window.removeEventListener('resize', this.boundResize);
    }

    previous() {
        this.goTo(this.activeIndex - 1);
    }

    next() {
        this.goTo(this.activeIndex + 1);
    }

    onColumnAdvance(event) {
        if (!this.isMobile()) {
            return;
        }
        const direction = event.detail?.direction;
        if (direction === 'next') {
            this.next();
        } else if (direction === 'prev') {
            this.previous();
        }
    }

    goTo(index) {
        const bounded = Math.max(0, Math.min(index, this.columnTargets.length - 1));
        if (bounded === this.activeIndex) {
            return;
        }
        this.activeIndex = bounded;
        this.applyLayout({ animate: true });

        this.element.dispatchEvent(new CustomEvent('board:column-changed', {
            bubbles: true,
            detail: { index: bounded },
        }));
    }

    onTouchStart(event) {
        if (!this.isMobile() || this.dragging || event.touches.length !== 1) {
            this.touchSession = null;
            return;
        }

        const touchTarget = event.target;
        if (!(touchTarget instanceof HTMLElement)) {
            this.touchSession = null;
            return;
        }

        // Ignore native interactive controls so tap/click keeps working as expected.
        if (touchTarget.closest('a, button, input, textarea, select, label, form')) {
            this.touchSession = null;
            return;
        }

        const touch = event.touches[0];
        this.touchSession = {
            startX: touch.clientX,
            startY: touch.clientY,
            currentX: touch.clientX,
            currentY: touch.clientY,
            lockedHorizontal: false,
        };
    }

    onTouchMove(event) {
        if (!this.touchSession || this.dragging || event.touches.length !== 1) {
            return;
        }

        const touch = event.touches[0];
        this.touchSession.currentX = touch.clientX;
        this.touchSession.currentY = touch.clientY;

        const deltaX = this.touchSession.currentX - this.touchSession.startX;
        const deltaY = this.touchSession.currentY - this.touchSession.startY;

        if (!this.touchSession.lockedHorizontal) {
            const absX = Math.abs(deltaX);
            const absY = Math.abs(deltaY);
            if (absX >= SWIPE_THRESHOLD_PX * 0.5 && absX > absY * SWIPE_DIRECTION_RATIO) {
                this.touchSession.lockedHorizontal = true;
            }
        }

        if (this.touchSession.lockedHorizontal) {
            event.preventDefault();
        }
    }

    onTouchEnd() {
        if (!this.touchSession || this.dragging) {
            this.touchSession = null;
            return;
        }

        const deltaX = this.touchSession.currentX - this.touchSession.startX;
        const deltaY = this.touchSession.currentY - this.touchSession.startY;
        const absX = Math.abs(deltaX);
        const absY = Math.abs(deltaY);

        if (absX >= SWIPE_THRESHOLD_PX && absX > absY * SWIPE_DIRECTION_RATIO) {
            if (deltaX < 0) {
                this.next();
            } else {
                this.previous();
            }
        }

        this.touchSession = null;
    }

    applyLayout(options = {}) {
        const animate = options.animate ?? true;
        const mobile = this.isMobile();
        const maxIndex = Math.max(this.columnTargets.length - 1, 0);
        this.activeIndex = Math.min(this.activeIndex, maxIndex);

        this.element.classList.toggle('board-mobile-mode', mobile);

        if (this.hasTrackTarget) {
            if (mobile) {
                const translation = `translate3d(-${this.activeIndex * 100}%, 0, 0)`;
                if (!animate) {
                    this.trackTarget.classList.add('board-mobile-no-transition');
                    this.trackTarget.style.transform = translation;
                    // Force reflow so the next mutation re-enables the transition cleanly.
                    void this.trackTarget.offsetWidth;
                    this.trackTarget.classList.remove('board-mobile-no-transition');
                } else {
                    this.trackTarget.style.transform = translation;
                }
            } else {
                this.trackTarget.style.transform = '';
            }
        }

    }

    isMobile() {
        return window.matchMedia('(max-width: 1023px), (pointer: coarse) and (max-width: 1279px)').matches;
    }
}
