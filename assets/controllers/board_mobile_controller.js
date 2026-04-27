import { Controller } from '@hotwired/stimulus';

const SWIPE_THRESHOLD_PX = 30;
const SWIPE_DIRECTION_RATIO = 1.05;
const MOBILE_MEDIA_QUERY = '(max-width: 1023px), (pointer: coarse) and (max-width: 1279px)';

export default class extends Controller {
    static targets = ['column', 'track'];

    connect() {
        this.activeIndex = 0;
        this.touchSession = null;

        this.boundResize = () => this.applyLayout();
        this.boundTouchStart = (event) => this.onTouchStart(event);
        this.boundTouchMove = (event) => this.onTouchMove(event);
        this.boundTouchEnd = (event) => this.onTouchEnd(event);

        this.element.addEventListener('touchstart', this.boundTouchStart, { passive: true });
        this.element.addEventListener('touchmove', this.boundTouchMove, { passive: false });
        this.element.addEventListener('touchend', this.boundTouchEnd, { passive: true });
        window.addEventListener('resize', this.boundResize);

        this.activeIndex = this.findInitialIndex();

        this.applyLayout({ animate: false });
    }

    disconnect() {
        this.element.removeEventListener('touchstart', this.boundTouchStart);
        this.element.removeEventListener('touchmove', this.boundTouchMove);
        this.element.removeEventListener('touchend', this.boundTouchEnd);
        window.removeEventListener('resize', this.boundResize);
    }

    goTo(index) {
        const bounded = Math.max(0, Math.min(index, this.columnTargets.length - 1));
        if (bounded === this.activeIndex) {
            return;
        }
        this.activeIndex = bounded;
        this.applyLayout({ animate: true });
    }

    onTouchStart(event) {
        if (!this.isMobile() || event.touches.length !== 1) {
            this.touchSession = null;
            return;
        }

        const touchTarget = event.target;
        if (!(touchTarget instanceof HTMLElement)) {
            this.touchSession = null;
            return;
        }

        // Ignore native interactive controls so tap/click keeps working as expected.
        if (touchTarget.closest('a, button, input, textarea, select')) {
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
        if (!this.touchSession || event.touches.length !== 1) {
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

    onTouchEnd(event) {
        if (!this.touchSession) {
            this.touchSession = null;
            return;
        }

        const changedTouch = event.changedTouches?.[0];
        if (changedTouch) {
            this.touchSession.currentX = changedTouch.clientX;
            this.touchSession.currentY = changedTouch.clientY;
        }

        const deltaX = this.touchSession.currentX - this.touchSession.startX;
        const deltaY = this.touchSession.currentY - this.touchSession.startY;
        const absX = Math.abs(deltaX);
        const absY = Math.abs(deltaY);

        if (absX >= SWIPE_THRESHOLD_PX && absX > absY * SWIPE_DIRECTION_RATIO) {
            if (deltaX < 0) {
                this.goTo(this.activeIndex + 1);
            } else {
                this.goTo(this.activeIndex - 1);
            }
        }

        this.touchSession = null;
    }

    findInitialIndex() {
        if (!this.isMobile()) {
            return 0;
        }

        const firstNonEmptyIndex = this.columnTargets.findIndex((column) => column.querySelector('[data-task-id]'));
        if (firstNonEmptyIndex >= 0) {
            return firstNonEmptyIndex;
        }

        return 0;
    }

    applyLayout(options = {}) {
        const animate = options.animate ?? true;
        const mobile = this.isMobile();
        const maxIndex = Math.max(this.columnTargets.length - 1, 0);
        this.activeIndex = Math.min(this.activeIndex, maxIndex);

        this.element.classList.toggle('board-mobile-mode', mobile);

        if (this.hasTrackTarget) {
            if (mobile) {
                const translation = `translate3d(-${this.getOffsetPx()}px, 0, 0)`;
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

    getOffsetPx() {
        if (this.columnTargets.length === 0) {
            return 0;
        }

        const firstColumn = this.columnTargets[0];
        const columnWidth = Math.max(firstColumn.getBoundingClientRect().width, 1);
        return this.activeIndex * columnWidth;
    }

    isMobile() {
        return window.matchMedia(MOBILE_MEDIA_QUERY).matches;
    }
}
