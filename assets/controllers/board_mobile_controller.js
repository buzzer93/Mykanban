import { Controller } from '@hotwired/stimulus';

const SWIPE_THRESHOLD_PX = 45;
const TAP_MAX_MOVE_PX = 10;
const DOUBLE_TAP_WINDOW_MS = 320;
const HORIZONTAL_LOCK_PX = 10;

export default class extends Controller {
    static targets = ['column', 'counter', 'hint', 'task'];

    connect() {
        this.activeIndex = 0;
        this.resetTouchState();
        this.lastTap = { taskId: null, at: 0 };

        this.boundTouchStart = (event) => this.onTouchStart(event);
        this.boundTouchMove = (event) => this.onTouchMove(event);
        this.boundTouchEnd = (event) => this.onTouchEnd(event);
        this.boundTouchCancel = () => this.resetTouchState();
        this.boundColumnAdvance = (event) => this.onColumnAdvance(event);
        this.boundResize = () => this.applyLayout();

        this.element.addEventListener('touchstart', this.boundTouchStart, { passive: true });
        this.element.addEventListener('touchmove', this.boundTouchMove, { passive: false });
        this.element.addEventListener('touchend', this.boundTouchEnd);
        this.element.addEventListener('touchcancel', this.boundTouchCancel);
        this.element.addEventListener('board:column-advance', this.boundColumnAdvance);
        window.addEventListener('resize', this.boundResize);

        this.applyLayout();
    }

    disconnect() {
        this.element.removeEventListener('touchstart', this.boundTouchStart);
        this.element.removeEventListener('touchmove', this.boundTouchMove);
        this.element.removeEventListener('touchend', this.boundTouchEnd);
        this.element.removeEventListener('touchcancel', this.boundTouchCancel);
        this.element.removeEventListener('board:column-advance', this.boundColumnAdvance);
        window.removeEventListener('resize', this.boundResize);
    }

    onTouchStart(event) {
        if (!this.isMobile() || event.touches.length > 1) {
            this.resetTouchState();
            return;
        }

        const touch = event.changedTouches[0];
        if (!touch) {
            return;
        }

        this.touchStartX = touch.clientX;
        this.touchStartY = touch.clientY;
        this.touchDeltaX = 0;
        this.touchDeltaY = 0;
        this.touchIsHorizontal = false;

        const taskElement = event.target.closest('.board-task-card');
        this.touchTaskId = taskElement?.dataset.taskId ?? null;
        this.touchTaskElement = taskElement ?? null;
        this.touchOnInteractive = Boolean(event.target.closest('a, button, input, textarea, select, label, form'));
    }

    onTouchMove(event) {
        if (!this.isMobile() || this.touchStartX === null) {
            return;
        }

        const touch = event.changedTouches[0];
        if (!touch) {
            return;
        }

        this.touchDeltaX = touch.clientX - this.touchStartX;
        this.touchDeltaY = touch.clientY - this.touchStartY;

        if (
            !this.touchIsHorizontal
            && Math.abs(this.touchDeltaX) > HORIZONTAL_LOCK_PX
            && Math.abs(this.touchDeltaX) > Math.abs(this.touchDeltaY)
        ) {
            this.touchIsHorizontal = true;
        }

        if (this.touchIsHorizontal && event.cancelable) {
            event.preventDefault();
        }
    }

    onTouchEnd(event) {
        if (!this.isMobile() || this.touchStartX === null) {
            this.resetTouchState();
            return;
        }

        const isSwipe = Math.abs(this.touchDeltaX) >= SWIPE_THRESHOLD_PX
            && Math.abs(this.touchDeltaX) > Math.abs(this.touchDeltaY);

        const isTap = Math.abs(this.touchDeltaX) < TAP_MAX_MOVE_PX
            && Math.abs(this.touchDeltaY) < TAP_MAX_MOVE_PX;

        if (isSwipe) {
            if (this.touchDeltaX <= -SWIPE_THRESHOLD_PX) {
                this.goTo(this.activeIndex + 1);
            } else if (this.touchDeltaX >= SWIPE_THRESHOLD_PX) {
                this.goTo(this.activeIndex - 1);
            }
        } else if (isTap && this.touchTaskId !== null && !this.touchOnInteractive) {
            const now = Date.now();
            const isDoubleTap = this.lastTap.taskId === this.touchTaskId
                && (now - this.lastTap.at) <= DOUBLE_TAP_WINDOW_MS;

            if (isDoubleTap) {
                if (event.cancelable) {
                    event.preventDefault();
                }
                this.element.dispatchEvent(new CustomEvent('board:move-next-column', {
                    bubbles: true,
                    detail: { taskId: this.touchTaskId },
                }));
                this.lastTap = { taskId: null, at: 0 };
            } else {
                this.lastTap = { taskId: this.touchTaskId, at: now };
            }
        }

        this.resetTouchState();
    }

    onColumnAdvance(event) {
        if (!this.isMobile()) {
            return;
        }
        const direction = event.detail?.direction;
        if (direction === 'next') {
            this.goTo(this.activeIndex + 1);
        } else if (direction === 'prev') {
            this.goTo(this.activeIndex - 1);
        }
    }

    goTo(index) {
        const boundedIndex = Math.max(0, Math.min(index, this.columnTargets.length - 1));
        if (boundedIndex === this.activeIndex) {
            return;
        }
        this.activeIndex = boundedIndex;
        this.applyLayout();
    }

    applyLayout() {
        const mobile = this.isMobile();

        this.element.classList.toggle('board-mobile-mode', mobile);

        this.columnTargets.forEach((column, index) => {
            const isActive = index === this.activeIndex;
            column.classList.toggle('board-column-mobile-active', mobile && isActive);
            column.classList.toggle('board-column-mobile-hidden', mobile && !isActive);
        });

        if (this.hasCounterTarget) {
            this.counterTarget.textContent = `${this.activeIndex + 1}/${this.columnTargets.length}`;
        }

        if (this.hasHintTarget) {
            this.hintTarget.classList.toggle('hidden', !mobile);
        }
    }

    resetTouchState() {
        this.touchStartX = null;
        this.touchStartY = null;
        this.touchDeltaX = 0;
        this.touchDeltaY = 0;
        this.touchIsHorizontal = false;
        this.touchTaskId = null;
        this.touchTaskElement = null;
        this.touchOnInteractive = false;
    }

    isMobile() {
        return window.matchMedia('(max-width: 1023px), (pointer: coarse) and (max-width: 1279px)').matches;
    }
}
