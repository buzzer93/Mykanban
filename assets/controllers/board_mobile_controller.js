import { Controller } from '@hotwired/stimulus';

const MOBILE_MEDIA_QUERY = '(max-width: 1023px), (pointer: coarse) and (max-width: 1279px)';
const MIN_SWIPE_PX = 120;
const MIN_SWIPE_RATIO = 0.4;
const POST_SWIPE_LOCK_MS = 180;

export default class extends Controller {
    static targets = ['column', 'track'];

    connect() {
        this.activeIndex = 0;
        this.touchInteraction = null;
        this.swipeLockIndex = null;
        this.swipeLockTimer = null;
        this.boundResize = () => this.applyLayout();
        this.boundTrackScroll = () => this.onTrackScroll();
        this.boundTrackTouchStart = (event) => this.onTrackTouchStart(event);
        this.boundTrackTouchEnd = (event) => this.onTrackTouchEnd(event);
        this.boundTrackTouchCancel = () => this.onTrackTouchCancel();

        window.addEventListener('resize', this.boundResize);
        if (this.hasTrackTarget) {
            this.trackTarget.addEventListener('scroll', this.boundTrackScroll, { passive: true });
            this.trackTarget.addEventListener('touchstart', this.boundTrackTouchStart, { passive: true });
            this.trackTarget.addEventListener('touchend', this.boundTrackTouchEnd, { passive: true });
            this.trackTarget.addEventListener('touchcancel', this.boundTrackTouchCancel, { passive: true });
        }

        this.activeIndex = this.findInitialIndex();

        this.applyLayout();
    }

    disconnect() {
        this.clearSwipeLock();
        window.removeEventListener('resize', this.boundResize);
        if (this.hasTrackTarget) {
            this.trackTarget.removeEventListener('scroll', this.boundTrackScroll);
            this.trackTarget.removeEventListener('touchstart', this.boundTrackTouchStart);
            this.trackTarget.removeEventListener('touchend', this.boundTrackTouchEnd);
            this.trackTarget.removeEventListener('touchcancel', this.boundTrackTouchCancel);
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
                this.scrollToIndex(this.activeIndex);
            } else {
                this.trackTarget.scrollLeft = 0;
            }
        }
    }

    onTrackScroll() {
        if (!this.isMobile() || this.columnTargets.length === 0 || !this.hasTrackTarget || this.touchInteraction) {
            return;
        }

        if (this.swipeLockIndex !== null) {
            this.scrollToIndex(this.swipeLockIndex, false);
            return;
        }

        const viewportWidth = Math.max(this.trackTarget.clientWidth, 1);
        const nextIndex = Math.round(this.trackTarget.scrollLeft / viewportWidth);
        this.activeIndex = Math.max(0, Math.min(nextIndex, this.columnTargets.length - 1));
    }

    onTrackTouchStart(event) {
        if (!this.isMobile() || !this.hasTrackTarget || event.touches.length !== 1) {
            this.touchInteraction = null;
            return;
        }

        const touchTarget = event.target;
        if (touchTarget instanceof HTMLElement && touchTarget.closest('a, button, input, textarea, select')) {
            this.touchInteraction = null;
            return;
        }

        const startIndex = this.getCurrentScrollIndex();
        this.scrollToIndex(startIndex, false);

        this.touchInteraction = {
            startX: event.touches[0].clientX,
            startIndex,
        };

        this.trackTarget.classList.add('board-mobile-no-snap');
    }

    onTrackTouchEnd(event) {
        if (!this.touchInteraction || !this.hasTrackTarget) {
            this.touchInteraction = null;
            return;
        }

        const changedTouch = event.changedTouches?.[0];
        const endX = changedTouch ? changedTouch.clientX : this.touchInteraction.startX;
        const deltaX = this.touchInteraction.startX - endX;
        const viewportWidth = Math.max(this.trackTarget.clientWidth, 1);
        const threshold = Math.max(MIN_SWIPE_PX, Math.round(viewportWidth * MIN_SWIPE_RATIO));

        let nextIndex = this.touchInteraction.startIndex;

        if (Math.abs(deltaX) >= threshold) {
            nextIndex += deltaX > 0 ? 1 : -1;
        }

        this.trackTarget.classList.remove('board-mobile-no-snap');
        this.scrollToIndex(nextIndex, false);
        this.lockSwipeToIndex(nextIndex);
        this.touchInteraction = null;
    }

    onTrackTouchCancel() {
        if (!this.hasTrackTarget) {
            this.touchInteraction = null;
            return;
        }

        this.trackTarget.classList.remove('board-mobile-no-snap');
        this.scrollToIndex(this.activeIndex, false);
        this.lockSwipeToIndex(this.activeIndex);
        this.touchInteraction = null;
    }

    lockSwipeToIndex(index) {
        this.clearSwipeLock();
        this.swipeLockIndex = Math.max(0, Math.min(index, this.columnTargets.length - 1));
        this.swipeLockTimer = window.setTimeout(() => {
            this.clearSwipeLock();
        }, POST_SWIPE_LOCK_MS);
    }

    clearSwipeLock() {
        if (this.swipeLockTimer !== null) {
            window.clearTimeout(this.swipeLockTimer);
            this.swipeLockTimer = null;
        }
        this.swipeLockIndex = null;
    }

    scrollToIndex(index, smooth = false) {
        if (!this.hasTrackTarget || this.columnTargets.length === 0) {
            return;
        }

        const bounded = Math.max(0, Math.min(index, this.columnTargets.length - 1));
        const targetColumn = this.columnTargets[bounded];
        const left = targetColumn.offsetLeft;
        this.trackTarget.scrollTo({ left, behavior: smooth ? 'smooth' : 'auto' });
        this.activeIndex = bounded;
    }

    getCurrentScrollIndex() {
        if (!this.hasTrackTarget || this.columnTargets.length === 0) {
            return 0;
        }

        const viewportWidth = Math.max(this.trackTarget.clientWidth, 1);
        const index = Math.round(this.trackTarget.scrollLeft / viewportWidth);
        return Math.max(0, Math.min(index, this.columnTargets.length - 1));
    }

    isMobile() {
        return window.matchMedia(MOBILE_MEDIA_QUERY).matches;
    }
}
