import { Controller } from '@hotwired/stimulus';

const MOBILE_MEDIA_QUERY = '(max-width: 1023px), (pointer: coarse) and (max-width: 1279px)';
const MIN_SWIPE_PX = 140;
const MIN_SWIPE_RATIO = 0.45;
const HORIZONTAL_RATIO = 1.2;
const SWIPE_COOLDOWN_MS = 260;

export default class extends Controller {
    static targets = ['column', 'track'];

    connect() {
        this.activeIndex = 0;
        this.touchSession = null;
        this.nextSwipeAllowedAt = 0;
        this.boundResize = () => this.applyLayout();
        this.boundTouchStart = (event) => this.onTouchStart(event);
        this.boundTouchMove = (event) => this.onTouchMove(event);
        this.boundTouchEnd = (event) => this.onTouchEnd(event);
        this.boundTouchCancel = () => this.onTouchCancel();

        window.addEventListener('resize', this.boundResize);
        if (this.hasTrackTarget && this.isMobile()) {
            this.trackTarget.addEventListener('touchstart', this.boundTouchStart, { passive: true });
            this.trackTarget.addEventListener('touchmove', this.boundTouchMove, { passive: false });
            this.trackTarget.addEventListener('touchend', this.boundTouchEnd, { passive: true });
            this.trackTarget.addEventListener('touchcancel', this.boundTouchCancel, { passive: true });
        }

        this.activeIndex = this.findInitialIndex();

        this.applyLayout();
    }

    disconnect() {
        window.removeEventListener('resize', this.boundResize);
        if (this.hasTrackTarget) {
            this.trackTarget.removeEventListener('touchstart', this.boundTouchStart);
            this.trackTarget.removeEventListener('touchmove', this.boundTouchMove);
            this.trackTarget.removeEventListener('touchend', this.boundTouchEnd);
            this.trackTarget.removeEventListener('touchcancel', this.boundTouchCancel);
        }
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

    applyLayout() {
        const mobile = this.isMobile();
        const maxIndex = Math.max(this.columnTargets.length - 1, 0);
        this.activeIndex = Math.min(this.activeIndex, maxIndex);

        this.element.classList.toggle('board-mobile-mode', mobile);

        if (this.hasTrackTarget) {
            if (mobile) {
                this.trackTarget.style.transform = `translate3d(-${this.getOffsetPx(this.activeIndex)}px, 0, 0)`;
            } else {
                this.trackTarget.style.transform = '';
            }
        }
    }

    onTouchStart(event) {
        if (!this.isMobile() || !this.hasTrackTarget || event.touches.length !== 1) {
            this.touchSession = null;
            return;
        }

        if (Date.now() < this.nextSwipeAllowedAt) {
            this.touchSession = null;
            return;
        }

        const touchTarget = event.target;
        if (touchTarget instanceof HTMLElement && touchTarget.closest('a, button, input, textarea, select')) {
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
            startIndex: this.activeIndex,
        };
    }

    onTouchMove(event) {
        if (!this.touchSession || !this.isMobile() || event.touches.length !== 1) {
            return;
        }

        const touch = event.touches[0];
        this.touchSession.currentX = touch.clientX;
        this.touchSession.currentY = touch.clientY;

        const deltaX = this.touchSession.currentX - this.touchSession.startX;
        const deltaY = this.touchSession.currentY - this.touchSession.startY;
        const absX = Math.abs(deltaX);
        const absY = Math.abs(deltaY);

        if (!this.touchSession.lockedHorizontal && absX > absY * HORIZONTAL_RATIO) {
            this.touchSession.lockedHorizontal = true;
        }

        if (this.touchSession.lockedHorizontal) {
            event.preventDefault();
        }
    }

    onTouchEnd(event) {
        if (!this.touchSession || !this.isMobile()) {
            this.touchSession = null;
            return;
        }

        const changedTouch = event.changedTouches?.[0];
        if (changedTouch) {
            this.touchSession.currentX = changedTouch.clientX;
            this.touchSession.currentY = changedTouch.clientY;
        }

        const deltaX = this.touchSession.startX - this.touchSession.currentX;
        const deltaY = this.touchSession.startY - this.touchSession.currentY;
        const absX = Math.abs(deltaX);
        const absY = Math.abs(deltaY);
        const viewportWidth = Math.max(this.hasTrackTarget ? this.trackTarget.clientWidth : 0, 1);
        const threshold = Math.max(MIN_SWIPE_PX, Math.round(viewportWidth * MIN_SWIPE_RATIO));

        let nextIndex = this.touchSession.startIndex;
        if (absX >= threshold && absX > absY * HORIZONTAL_RATIO) {
            nextIndex += deltaX > 0 ? 1 : -1;
        }

        const changedIndex = nextIndex !== this.touchSession.startIndex;
        this.goTo(nextIndex);
        if (changedIndex) {
            this.nextSwipeAllowedAt = Date.now() + SWIPE_COOLDOWN_MS;
        }
        this.touchSession = null;
    }

    onTouchCancel() {
        this.touchSession = null;
    }

    goTo(index) {
        if (!this.hasTrackTarget || this.columnTargets.length === 0) {
            return;
        }

        const bounded = Math.max(0, Math.min(index, this.columnTargets.length - 1));
        if (bounded === this.activeIndex) {
            this.applyLayout();
            return;
        }

        this.activeIndex = bounded;
        this.applyLayout();
    }

    getOffsetPx(index) {
        if (!this.hasTrackTarget || this.columnTargets.length === 0) {
            return 0;
        }

        const bounded = Math.max(0, Math.min(index, this.columnTargets.length - 1));
        const targetColumn = this.columnTargets[bounded];
        return targetColumn.offsetLeft;
    }

    isMobile() {
        return window.matchMedia(MOBILE_MEDIA_QUERY).matches;
    }
}
