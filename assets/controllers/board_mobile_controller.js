import { Controller } from '@hotwired/stimulus';

const MOBILE_MEDIA_QUERY = '(max-width: 1023px), (pointer: coarse) and (max-width: 1279px)';

export default class extends Controller {
    static targets = ['column', 'track'];

    connect() {
        this.boundResize = () => this.applyLayout();
        window.addEventListener('resize', this.boundResize);

        this.applyLayout();
    }

    disconnect() {
        window.removeEventListener('resize', this.boundResize);
    }

    applyLayout() {
        const mobile = this.isMobile();
        this.element.classList.toggle('board-mobile-mode', mobile);

        if (!mobile || !this.hasTrackTarget || this.columnTargets.length === 0) {
            return;
        }

        const initialIndex = this.findInitialIndex();
        const targetColumn = this.columnTargets[initialIndex];
        this.trackTarget.scrollTo({
            left: targetColumn.offsetLeft,
            behavior: 'auto',
        });
    }

    findInitialIndex() {
        const firstNonEmptyIndex = this.columnTargets.findIndex((column) => column.querySelector('[data-task-id]'));
        if (firstNonEmptyIndex >= 0) {
            return firstNonEmptyIndex;
        }

        return 0;
    }

    isMobile() {
        return window.matchMedia(MOBILE_MEDIA_QUERY).matches;
    }
}
