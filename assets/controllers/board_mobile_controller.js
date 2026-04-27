import { Controller } from '@hotwired/stimulus';

const MOBILE_MEDIA_QUERY = '(max-width: 1023px), (pointer: coarse) and (max-width: 1279px)';

export default class extends Controller {
    static targets = ['column', 'track'];

    connect() {
        this.activeIndex = 0;
        this.boundResize = () => this.applyLayout();
        this.boundTrackScroll = () => this.onTrackScroll();

        window.addEventListener('resize', this.boundResize);
        if (this.hasTrackTarget) {
            this.trackTarget.addEventListener('scroll', this.boundTrackScroll, { passive: true });
        }

        this.activeIndex = this.findInitialIndex();

        this.applyLayout();
    }

    disconnect() {
        window.removeEventListener('resize', this.boundResize);
        if (this.hasTrackTarget) {
            this.trackTarget.removeEventListener('scroll', this.boundTrackScroll);
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
        if (!this.isMobile() || this.columnTargets.length === 0 || !this.hasTrackTarget) {
            return;
        }

        const viewportWidth = Math.max(this.trackTarget.clientWidth, 1);
        const nextIndex = Math.round(this.trackTarget.scrollLeft / viewportWidth);
        this.activeIndex = Math.max(0, Math.min(nextIndex, this.columnTargets.length - 1));
    }

    scrollToIndex(index) {
        if (!this.hasTrackTarget || this.columnTargets.length === 0) {
            return;
        }

        const bounded = Math.max(0, Math.min(index, this.columnTargets.length - 1));
        const targetColumn = this.columnTargets[bounded];
        const left = targetColumn.offsetLeft;
        this.trackTarget.scrollTo({ left, behavior: 'auto' });
        this.activeIndex = bounded;
    }

    isMobile() {
        return window.matchMedia(MOBILE_MEDIA_QUERY).matches;
    }
}
