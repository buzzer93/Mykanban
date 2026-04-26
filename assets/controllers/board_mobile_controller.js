import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['column', 'counter', 'hint', 'task'];

    connect() {
        this.activeIndex = 0;
        this.touchStartX = null;
        this.touchDeltaX = 0;
        this.lastTap = {
            taskId: null,
            at: 0,
        };

        this.boundTouchStart = (event) => this.onTouchStart(event);
        this.boundTouchMove = (event) => this.onTouchMove(event);
        this.boundTouchEnd = (event) => this.onTouchEnd(event);
        this.boundClick = (event) => this.onTaskTap(event);
        this.boundResize = () => this.applyLayout();

        this.element.addEventListener('touchstart', this.boundTouchStart, { passive: true });
        this.element.addEventListener('touchmove', this.boundTouchMove, { passive: true });
        this.element.addEventListener('touchend', this.boundTouchEnd);
        this.element.addEventListener('click', this.boundClick);
        window.addEventListener('resize', this.boundResize);

        this.applyLayout();
    }

    disconnect() {
        this.element.removeEventListener('touchstart', this.boundTouchStart);
        this.element.removeEventListener('touchmove', this.boundTouchMove);
        this.element.removeEventListener('touchend', this.boundTouchEnd);
        this.element.removeEventListener('click', this.boundClick);
        window.removeEventListener('resize', this.boundResize);
    }

    onTouchStart(event) {
        if (!this.isMobile()) {
            return;
        }

        this.touchStartX = event.changedTouches[0]?.clientX ?? null;
        this.touchDeltaX = 0;
    }

    onTouchMove(event) {
        if (!this.isMobile() || this.touchStartX === null) {
            return;
        }

        const currentX = event.changedTouches[0]?.clientX ?? this.touchStartX;
        this.touchDeltaX = currentX - this.touchStartX;
    }

    onTouchEnd() {
        if (!this.isMobile() || this.touchStartX === null) {
            return;
        }

        const threshold = 45;
        if (this.touchDeltaX <= -threshold) {
            this.goTo(this.activeIndex + 1);
        } else if (this.touchDeltaX >= threshold) {
            this.goTo(this.activeIndex - 1);
        }

        this.touchStartX = null;
        this.touchDeltaX = 0;
    }

    onTaskTap(event) {
        if (!this.isMobile()) {
            return;
        }

        const taskElement = event.target.closest('.board-task-card');
        if (!taskElement) {
            return;
        }

        // Ignore interactive controls inside a card.
        if (event.target.closest('a, button, input, textarea, select, label, form')) {
            return;
        }

        const taskId = taskElement.dataset.taskId ?? null;
        const now = Date.now();
        const isDoubleTap = this.lastTap.taskId === taskId && now - this.lastTap.at <= 320;

        this.lastTap = {
            taskId,
            at: now,
        };

        if (!isDoubleTap) {
            return;
        }

        this.element.dispatchEvent(new CustomEvent('board:move-next-column', {
            bubbles: true,
            detail: {
                taskId,
            },
        }));
    }

    goTo(index) {
        const boundedIndex = Math.max(0, Math.min(index, this.columnTargets.length - 1));
        this.activeIndex = boundedIndex;
        this.applyLayout();
    }

    applyLayout() {
        const mobile = this.isMobile();

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

    isMobile() {
        return window.matchMedia('(max-width: 1023px)').matches;
    }
}
